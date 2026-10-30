<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Command;

use Nordkirche\NkcBase\MapCache\Exception\FrontendRequiredException;
use Nordkirche\NkcBase\MapCache\MapMarkerCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Builds the marker cache of all map content elements.
 *
 * The content elements are found automatically: every element whose type is
 * handled by a registered map marker provider is warmed up.
 */
#[AsCommand(
    name: 'nkc_base:map_cache',
    description: 'Baut den Marker-Cache aller Karten-Inhaltselemente auf',
)]
class MapCacheCommandController extends Command
{
    private const DEFAULT_MAX_AGE = 3600;

    /**
     * Arguments of the former HTTP based warm-up, accepted to keep existing
     * scheduler tasks working
     */
    private const LEGACY_ARGUMENTS = ['baseUrl', 'forceRefresh', 'frontendMode', 'typeNum', 'httpUser', 'httpPass'];

    public function __construct(
        private readonly MapMarkerCache $mapMarkerCache,
        private readonly ConnectionPool $connectionPool,
        private readonly SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(
                'Ohne Optionen werden alle Inhaltselemente aufgewärmt, für die ein Karten-Provider registriert ist ' .
                '(z. B. aus nkc_address oder nkc_event). Einträge, die jünger als --max-age Sekunden sind, bleiben erhalten.'
            )
            ->addOption('content', 'c', InputOption::VALUE_REQUIRED, 'Nur diese Content-Element-IDs (durch Komma getrennt)')
            ->addOption('site', 's', InputOption::VALUE_REQUIRED, 'Nur Inhaltselemente dieser Site (Identifier)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Cache neu aufbauen, auch wenn er noch aktuell ist')
            ->addOption('max-age', null, InputOption::VALUE_REQUIRED, 'Einträge älter als diese Anzahl Sekunden werden neu aufgebaut', (string)self::DEFAULT_MAX_AGE)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Gefundene Inhaltselemente nur auflisten')
            ->addArgument('contentUids', InputArgument::OPTIONAL, 'Veraltet, bitte --content verwenden');

        foreach (self::LEGACY_ARGUMENTS as $name) {
            $this->addArgument($name, InputArgument::OPTIONAL, 'Veraltet, wird ignoriert');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Karten-Cache aufwärmen');

        $pluginTypes = $this->mapMarkerCache->getSupportedPluginTypes();
        if ($pluginTypes === []) {
            $io->warning('Es ist kein Karten-Provider registriert (z. B. aus nkc_address oder nkc_event).');
            return Command::SUCCESS;
        }

        $this->warnAboutLegacyArguments($input, $io);

        $contentUids = GeneralUtility::intExplode(',', (string)($input->getOption('content') ?? $input->getArgument('contentUids') ?? ''), true);
        $siteIdentifier = (string)($input->getOption('site') ?? '');
        $force = (bool)$input->getOption('force') || (int)$input->getArgument('forceRefresh') === 1;
        $maxAge = max(0, (int)$input->getOption('max-age'));

        $elements = $this->findContentElements($pluginTypes, $contentUids, $siteIdentifier, $io);
        if ($elements === []) {
            $io->note('Keine passenden Inhaltselemente gefunden.');
            return Command::SUCCESS;
        }

        if ($input->getOption('dry-run')) {
            $io->table(
                ['UID', 'Seite', 'Site', 'Typ', 'Cache aktuell'],
                array_map(fn(array $element): array => [
                    $element['row']['uid'],
                    $element['row']['pid'],
                    $element['site']->getIdentifier(),
                    MapMarkerCache::getPluginType($element['row']),
                    $this->mapMarkerCache->has($element['row'], $maxAge) ? 'ja' : 'nein',
                ], $elements)
            );
            return Command::SUCCESS;
        }

        $results = [];
        $errors = 0;
        $io->progressStart(count($elements));
        foreach ($elements as ['row' => $row, 'site' => $site]) {
            try {
                $isFresh = !$force && $this->mapMarkerCache->has($row, $maxAge);
                $entry = $this->mapMarkerCache->get($row, $this->getSettings($row), $site, $maxAge, $force);
                $status = $isFresh ? 'aktuell' : 'neu aufgebaut';
                $markers = count($entry['data']);
            } catch (FrontendRequiredException $e) {
                $status = 'übersprungen: ' . $e->getMessage();
                $markers = '-';
            } catch (\Throwable $e) {
                $errors++;
                $status = 'Fehler: ' . $e->getMessage();
                $markers = '-';
            }
            $results[] = [$row['uid'], $row['pid'], MapMarkerCache::getPluginType($row), $markers, $status];
            $io->progressAdvance();
        }
        $io->progressFinish();
        $io->table(['UID', 'Seite', 'Typ', 'Marker', 'Status'], $results);

        if ($errors > 0) {
            $io->error(sprintf('%d von %d Inhaltselementen konnten nicht aufgebaut werden.', $errors, count($elements)));
            return Command::FAILURE;
        }

        $io->success(sprintf('%d Inhaltselemente verarbeitet.', count($elements)));
        return Command::SUCCESS;
    }

    /**
     * Visible content elements of the given types on pages that belong to a site
     *
     * @param string[] $pluginTypes
     * @param int[] $contentUids
     * @return list<array{row: array<string, mixed>, site: Site}>
     */
    private function findContentElements(array $pluginTypes, array $contentUids, string $siteIdentifier, SymfonyStyle $io): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        $queryBuilder->getRestrictions()->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, 0));

        $typeConstraint = $queryBuilder->expr()->in('c.CType', $queryBuilder->createNamedParameter($pluginTypes, Connection::PARAM_STR_ARRAY));

        // Plugins of type "list" only exist up to TYPO3 v13 (v14 has no column list_type)
        if (isset($GLOBALS['TCA']['tt_content']['columns']['list_type'])) {
            $typeConstraint = $queryBuilder->expr()->or(
                $typeConstraint,
                $queryBuilder->expr()->and(
                    $queryBuilder->expr()->eq('c.CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->in('c.list_type', $queryBuilder->createNamedParameter($pluginTypes, Connection::PARAM_STR_ARRAY))
                )
            );
        }

        $queryBuilder
            ->select('c.*')
            ->from('tt_content', 'c')
            ->join('c', 'pages', 'p', $queryBuilder->expr()->eq('p.uid', $queryBuilder->quoteIdentifier('c.pid')))
            ->where($typeConstraint)
            ->orderBy('c.pid')
            ->addOrderBy('c.sorting');

        if ($contentUids !== []) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->in('c.uid', $queryBuilder->createNamedParameter($contentUids, Connection::PARAM_INT_ARRAY))
            );
        }

        $elements = [];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            try {
                $site = $this->siteFinder->getSiteByPageId((int)$row['pid']);
            } catch (\Throwable) {
                $io->note(sprintf('Inhaltselement %d übersprungen: Seite %d gehört zu keiner Site.', $row['uid'], $row['pid']));
                continue;
            }
            if ($siteIdentifier !== '' && $site->getIdentifier() !== $siteIdentifier) {
                continue;
            }
            $elements[] = ['row' => $row, 'site' => $site];
        }
        return $elements;
    }

    /**
     * The "settings" part of the plugin flexform, flagged for the backend context
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function getSettings(array $row): array
    {
        $flexForm = (string)($row['pi_flexform'] ?? '');
        $data = $flexForm === '' ? [] : $this->convertFlexForm($flexForm);
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $settings['backendContext'] = 1;
        return $settings;
    }

    private function convertFlexForm(string $flexForm): array
    {
        // TYPO3 v14 merged the FlexFormService into the FlexFormTools
        $flexFormTools = GeneralUtility::makeInstance(FlexFormTools::class);
        if (method_exists($flexFormTools, 'convertFlexFormContentToArray')) {
            return $flexFormTools->convertFlexFormContentToArray($flexForm);
        }
        return GeneralUtility::makeInstance(FlexFormService::class)->convertFlexFormContentToArray($flexForm);
    }

    private function warnAboutLegacyArguments(InputInterface $input, SymfonyStyle $io): void
    {
        if ($input->getArgument('contentUids') !== null) {
            $io->note('Das Argument "contentUids" ist veraltet, bitte die Option --content verwenden.');
        }
        $legacy = array_filter(self::LEGACY_ARGUMENTS, static fn(string $name): bool => $input->getArgument($name) !== null);
        if ($legacy !== []) {
            $io->note(sprintf(
                'Die Argumente %s werden nicht mehr benötigt und ignoriert. Der Cache wird direkt aufgebaut, ohne HTTP-Requests.',
                implode(', ', $legacy)
            ));
        }
    }
}
