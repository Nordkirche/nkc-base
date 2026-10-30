<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Command;

use Nordkirche\Ndk\Domain\Model\Category;
use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Domain\Repository\CategoryRepository;
use Nordkirche\NkcBase\Service\ApiService;
use Nordkirche\NkcBase\Service\NdkApiFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Imports the NAPI categories into sys_category. The uid of a category is the NAPI id.
 */
#[AsCommand(
    name: 'nkc_base:napi_sync',
    description: 'Synchronisiert die NAPI Kategorien mit der lokalen Datenbank. ACHTUNG: überschreibt Kategorien mit gleicher UID!',
)]
class NapiSyncCommandController extends Command
{
    private const TABLE = 'sys_category';

    public function __construct(
        private readonly NdkApiFactory $ndkApiFactory,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('pid', InputArgument::REQUIRED, 'Seiten-ID der Kategorien');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('NAPI Kategorien synchronisieren');

        $pid = (int)$input->getArgument('pid');

        $api = $this->ndkApiFactory->getApi();
        $categories = ApiService::getAllItems($api->factory(CategoryRepository::class), $api->factory(PageQuery::class));

        if ($categories === []) {
            $io->warning('Die NAPI hat keine Kategorien geliefert.');
            return Command::FAILURE;
        }

        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $existingUids = $this->getExistingUids($connection);
        $created = $updated = 0;

        foreach ($categories as $category) {
            $parent = $category->getParent();
            $uid = (int)$category->getId();
            $record = [
                'pid' => $pid,
                'title' => (string)$category->getName(),
                'parent' => $parent instanceof Category ? (int)$parent->getId() : 0,
                'tstamp' => time(),
            ];

            if (isset($existingUids[$uid])) {
                $connection->update(self::TABLE, $record, ['uid' => $uid]);
                $updated++;
            } else {
                $connection->insert(self::TABLE, $record + [
                    'uid' => $uid,
                    'crdate' => time(),
                    'l10n_diffsource' => '',
                ]);
                $created++;
            }
        }

        $io->success(sprintf('NAPI Sync abgeschlossen: %d neu, %d aktualisiert.', $created, $updated));
        return Command::SUCCESS;
    }

    /**
     * All uids of sys_category, including deleted and hidden records
     *
     * @return array<int, true>
     */
    private function getExistingUids(Connection $connection): array
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        $uids = $queryBuilder->select('uid')->from(self::TABLE)->executeQuery()->fetchFirstColumn();
        return array_fill_keys(array_map(intval(...), $uids), true);
    }
}
