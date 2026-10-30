<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Routing\Aspect;

use Nordkirche\NkcBase\Domain\Repository\GenericRepositoryInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\SiteLanguageAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Site\SiteLanguageAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Maps NAPI object ids to readable URL segments. Generated slugs are stored in
 * the table tx_slug_mapping, so the NAPI is only asked once per object.
 *
 * Example:
 *   routeEnhancers:
 *     EventsPlugin:
 *       type: Extbase
 *       extension: NkcEvent
 *       plugin: Main
 *       routes:
 *         - { routePath: '/events/{event}', _controller: 'Event::show', _arguments: {'event': 'uid'} }
 *       aspects:
 *         event:
 *           type: GenericMapper
 *           mapper: 'nkc_base'
 *           object: 'event'
 *           routeFieldResult: '{title}'
 */
class GenericMapper implements PersistedMappableAspectInterface, StaticMappableAspectInterface, SiteLanguageAwareInterface, SiteAwareInterface
{
    use SiteLanguageAccessorTrait;
    use SiteAccessorTrait;

    protected const PATTERN_RESULT = '#\{(?P<fieldName>[^}]+)\}#';
    protected const TABLE_NAME = 'tx_slug_mapping';

    /**
     * Safety limit when appending a counter to make a slug unique
     */
    protected const MAX_UNIFY_ATTEMPTS = 1000;

    /**
     * @var class-string<GenericRepositoryInterface>
     */
    protected string $mapper;
    protected string $mapperKey;
    protected string $object;
    protected bool $prependSlashInSlug = false;
    protected string $fallbackCharacter = '-';
    protected string $routeFieldResult = '';

    /**
     * @var string[]
     */
    protected array $routeFieldResultNames = [];

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(protected array $settings)
    {
        $mapperKey = $settings['mapper'] ?? null;
        $mapper = is_string($mapperKey) ? ($GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository'][$mapperKey] ?? null) : null;
        if (!is_string($mapper) || $mapper === '') {
            throw new \InvalidArgumentException('Unknown mapper: ' . (is_string($mapperKey) ? $mapperKey : ''), 1537277133);
        }
        $this->mapper = $mapper;
        $this->mapperKey = $mapperKey;

        if (!is_string($settings['object'] ?? null)) {
            throw new \InvalidArgumentException('object must be string', 1537277134);
        }
        $this->object = $settings['object'];

        if (isset($settings['prependSlashInSlug'])) {
            $this->prependSlashInSlug = filter_var($settings['prependSlashInSlug'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($settings['fallbackCharacter']) && is_string($settings['fallbackCharacter'])) {
            $this->fallbackCharacter = $settings['fallbackCharacter'];
        }

        if (isset($settings['routeFieldResult'])) {
            if (!is_string($settings['routeFieldResult'])) {
                throw new \InvalidArgumentException('routeFieldResult must be string', 1537277175);
            }
            if (!preg_match_all(static::PATTERN_RESULT, $settings['routeFieldResult'], $routeFieldResultNames)) {
                throw new \InvalidArgumentException('routeFieldResult must contain substitutable field names', 1537962752);
            }
            $this->routeFieldResult = $settings['routeFieldResult'];
            $this->routeFieldResultNames = $routeFieldResultNames['fieldName'];
        }
    }

    public function generate(string $value): ?string
    {
        $mapping = $this->findByIdentifier($value);
        if ($mapping !== null) {
            return $mapping['slug'];
        }

        // Unknown object: ask the NAPI once and remember the slug
        $label = $this->mapper::getRouteFieldResult($this->object, $value, $this->routeFieldResultNames, $this->routeFieldResult);
        if ($label === '') {
            return $value;
        }

        $slug = $this->unify($this->sanitize($label));
        if ($slug === '') {
            return $value;
        }

        $this->persist($value, $slug);
        return $slug;
    }

    public function resolve(string $value): ?string
    {
        return $this->findByRouteFieldValue($value)['object_id'] ?? null;
    }

    protected function createQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(static::TABLE_NAME);
    }

    protected function findByRouteFieldValue(string $value): ?array
    {
        return $this->findOneBy('slug', $value);
    }

    protected function findByIdentifier(string $value): ?array
    {
        return $this->findOneBy('object_id', $value);
    }

    protected function findOneBy(string $field, string $value): ?array
    {
        $queryBuilder = $this->createQueryBuilder();
        $row = $queryBuilder
            ->select('*')
            ->from(static::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq('object_name', $queryBuilder->createNamedParameter($this->getObjectName(), Connection::PARAM_STR)),
                $queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, Connection::PARAM_STR))
            )
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        return $row !== false ? $row : null;
    }

    protected function persist(string $identifier, string $slug): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(static::TABLE_NAME)
            ->insert(static::TABLE_NAME, [
                'object_name' => $this->getObjectName(),
                'object_id' => $identifier,
                'slug' => $slug,
                'crdate' => time(),
                'tstamp' => time(),
            ]);
    }

    /**
     * Cleans a value so it can be used directly in the path segment of a URL.
     * Follows the rules of the core SlugHelper.
     */
    protected function sanitize(string $slug): string
    {
        $fallbackCharacter = $this->fallbackCharacter;

        $slug = mb_strtolower($slug, 'utf-8');
        $slug = strip_tags($slug);
        $slug = str_replace('/', $fallbackCharacter, $slug);

        // Convert some special tokens (space, "_" and "-") to the fallback character
        $slug = (string)preg_replace('/[ \t\x{00A0}\-+_]+/u', $fallbackCharacter, $slug);

        // Convert extended letters to ascii equivalents ("€" becomes "EUR")
        $slug = GeneralUtility::makeInstance(CharsetConverter::class)->utf8_char_mapping($slug);

        // Remove all invalid characters
        $slug = (string)preg_replace('/[^\p{L}\p{M}0-9' . preg_quote($fallbackCharacter, '/') . ']/u', '', $slug);

        if ($fallbackCharacter !== '') {
            // Merge multiple fallback characters and trim them
            $slug = (string)preg_replace('/' . preg_quote($fallbackCharacter, '/') . '{2,}/', $fallbackCharacter, $slug);
            $slug = trim($slug, $fallbackCharacter);
        }

        $slug = mb_strtolower($slug, 'utf-8');

        if ($slug !== '' && $this->prependSlashInSlug) {
            $slug = '/' . $slug;
        }
        return $slug;
    }

    /**
     * Appends -1, -2, ... until the slug is not used by another object of the same type
     */
    protected function unify(string $slug): string
    {
        $candidate = $slug;
        for ($count = 1; $this->findByRouteFieldValue($candidate) !== null; $count++) {
            if ($count > static::MAX_UNIFY_ATTEMPTS) {
                return '';
            }
            $candidate = $slug . '-' . $count;
        }
        return $candidate;
    }

    protected function getObjectName(): string
    {
        return $this->mapperKey . '_' . $this->object;
    }
}
