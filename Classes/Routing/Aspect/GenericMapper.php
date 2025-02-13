<?php
declare(strict_types = 1);

namespace Nordkirche\NkcBase\Routing\Aspect;

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Context\ContextAwareInterface;
use TYPO3\CMS\Core\Context\ContextAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Routing\Aspect\AspectTrait;
use TYPO3\CMS\Core\Routing\Aspect\PersistedMappableAspectInterface;
use TYPO3\CMS\Core\Routing\Aspect\SiteAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\SiteLanguageAccessorTrait;
use TYPO3\CMS\Core\Routing\Aspect\StaticMappableAspectInterface;
use TYPO3\CMS\Core\Site\SiteAwareInterface;
use TYPO3\CMS\Core\Site\SiteLanguageAwareInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;


/**
 * Classic usage when using a "URL segment" (e.g. slug) field without external data.
 *
 * Example:
 *   routeEnhancers:
 *     EventsPlugin:
 *       type: Extbase
 *       extension: NkcEvents
 *       plugin: Main
 *       routes:
 *         - { routePath: '/events/{event}', _controller: 'Event::show', _arguments: {'event': 'event_name'}}
 *       defaultController: 'Events2::list'
 *       aspects:
 *         event_name:
 *           type: GenericMapper
 *           mapper: 'nkc_base',
 *           object: 'event'
 *           routeFieldResult: '{title}'
 */

class GenericMapper implements PersistedMappableAspectInterface, StaticMappableAspectInterface, ContextAwareInterface, SiteLanguageAwareInterface, SiteAwareInterface
{
    use AspectTrait;
    use SiteLanguageAccessorTrait;
    use SiteAccessorTrait;
    use ContextAwareTrait;

    protected const PATTERN_RESULT = '#\{(?P<fieldName>[^}]+)\}#';

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var string
     */
    protected $mapper;

    /**
     * @var string
     */
    protected $mapperKey;

    /**
     * @var string
     */
    protected $object;

    /**
     * @var string
     */
    protected $tableName = 'tx_slug_mapping';

    /**
     * @var bool
     */
    protected $prependSlashInSlug = false;

    /**
     * @var string
     */
    protected $routeFieldResult;

    /**
     * @var string[]
     */
    protected $routeFieldResultNames;


    /**
     * @param array $settings
     * @throws \InvalidArgumentException
     */
    public function __construct(array $settings)
    {
        $this->settings = $settings;

        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository'][$this->settings['mapper']])) {
            $this->mapper = $GLOBALS['TYPO3_CONF_VARS']['EXT']['slug']['repository'][$this->settings['mapper']];
            $this->mapperKey = $this->settings['mapper'];
        } else {
            throw new \InvalidArgumentException(
                'Unknown mapper: '.$this->settings['mapper'],
                1537277133
            );
        }
        if (!is_string($this->settings['object'])) {
            throw new \InvalidArgumentException(
                'object must be string',
                1537277133
            );
        } else {
            $this->object = $this->settings['object'];
        }
        if (isset($this->settings['prependSlashInSlug']) && is_string($this->settings['prependSlashInSlug'])) {
            $this->prependSlashInSlug = (strtolower($this->settings['prependSlashInSlug']) == 'true');
        }
        if (isset($this->settings['routeFieldResult']) && !is_string($this->settings['routeFieldResult'])) {
            throw new \InvalidArgumentException('routeFieldResult must be string', 1537277175);
        }

        if (isset($this->settings['routeFieldResult'])) {
            if (!preg_match_all(static::PATTERN_RESULT, $this->settings['routeFieldResult'], $routeFieldResultNames)) {
                throw new \InvalidArgumentException(
                    'routeFieldResult must contain substitutable field names',
                    1537962752
                );
            }

            $this->routeFieldResult = $this->settings['routeFieldResult'];
            $this->routeFieldResultNames = $routeFieldResultNames['fieldName'] ?? [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generate(string $value): ?string
    {
        if ($routeField = $this->findByIdentifier($value)) {
            return $routeField['slug'];
        } else {
            // Retrieve new value
            if ($identifier = $this->mapper::getRouteFieldResult($this->object, $value, $this->routeFieldResultNames, $this->routeFieldResult)) {
                if ($slug = $this->unify($this->sanitize($identifier))) {
                    $this->persist($value, $slug);
                    return $slug;
                } else {
                    return $value;
                }
            } else {
                return $value;
            }
        }
    }


    /**
     * {@inheritdoc}
     */
    public function resolve(string $value): ?string
    {
        if ($routeField = $this->findByRouteFieldValue($value)) {
            return $routeField['object_id'];
        } else {
            return null;
        }
    }


    /**
     * @return QueryBuilder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->tableName)
            ->from($this->tableName);
    }

    /**
     * @param string $value
     * @return array|null
     */
    protected function findByRouteFieldValue(string $value): ?array
    {
        $queryBuilder = $this->createQueryBuilder();
        $result = $queryBuilder
            ->select('*')
            ->where(
                $queryBuilder->expr()->eq(
                'object_name',
                $queryBuilder->createNamedParameter($this->getbObjectName(), \PDO::PARAM_STR)
            ))
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'slug',
                $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR)

            ))
            ->execute()
            ->fetchAssociative();
        return $result !== false ? $result : null;
    }

    /**
     * @param string $value
     * @return array|null
     */
    protected function findByIdentifier(string $value): ?array
    {
        $queryBuilder = $this->createQueryBuilder();
        $result = $queryBuilder
            ->select('*')
            ->where(
                $queryBuilder->expr()->eq(
                    'object_name',
                    $queryBuilder->createNamedParameter($this->getbObjectName(), \PDO::PARAM_STR)
                ))
            ->andWhere($queryBuilder->expr()->eq(
                'object_id',
                $queryBuilder->createNamedParameter($value, \PDO::PARAM_STR)
            ))
            ->execute()
            ->fetchAssociative();
        return $result !== false ? $result : null;
    }


    /**
     * @param $identifier
     * @param $slug
     */
    protected function persist($identifier, $slug) {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder
            ->insert($this->tableName)
            ->values([
                'object_name' => $this->getbObjectName(),
                'object_id' =>  $identifier,
                'slug' => $slug,
                'crdate' => time(),
                'tstamp' => time()
            ])
            ->execute();
    }


    /**
     * Cleans a slug value so it is used directly in the path segment of a URL.
     *
     * @param string $slug
     * @return string
     */
    private function sanitize(string $slug): string
    {
        // Convert to lowercase + remove tags
        $slug = mb_strtolower($slug, 'utf-8');
        $slug = strip_tags($slug);
        $slug = str_replace('/', '-', $slug);

        // Convert some special tokens (space, "_" and "-") to the space character
        $fallbackCharacter = (string)($this->configuration['fallbackCharacter'] ?? '-');
        $slug = preg_replace('/[ \t\x{00A0}\-+_]+/u', $fallbackCharacter, $slug);

        // Convert extended letters to ascii equivalents
        // The specCharsToASCII() converts "€" to "EUR"
        $slug = GeneralUtility::makeInstance(CharsetConverter::class)->specCharsToASCII('utf-8', $slug);

        // Get rid of all invalid characters, but allow slashes
        $slug = preg_replace('/[^\p{L}\p{M}0-9\/' . preg_quote($fallbackCharacter) . ']/u', '', $slug);

        // Convert multiple fallback characters to a single one
        if ($fallbackCharacter !== '') {
            $slug = preg_replace('/' . preg_quote($fallbackCharacter) . '{2,}/', $fallbackCharacter, $slug);
        }

        // Ensure slug is lower cased after all replacement was done
        $slug = mb_strtolower($slug, 'utf-8');
        // Extract slug, thus it does not have wrapping fallback and slash characters
        // Remove trailing and beginning slashes, except if the trailing slash was added, then we'll re-add it
        $appendTrailingSlash = $slug !== '' && substr($slug, -1) === '/';
        $slug = $slug . ($appendTrailingSlash ? '/' : '');
        if ($this->prependSlashInSlug && ($slug[0] ?? '') !== '/') {
            $slug = '/' . $slug;
        }
        return $slug;
    }

    /**
     * @param $slug
     * @return string
     */
    private function unify($slug) {
        $originalSlug = $slug;
        $count = 1;
        while ($check = $this->findByRouteFieldValue($slug)) {
            $slug = $originalSlug.'-'.$count;
            $count++;
        }
        return $slug;
    }

    /**
     * @return string
     */
    private function getbObjectName() {
        return sprintf('%s_%s', $this->mapperKey, $this->object);
    }

}
