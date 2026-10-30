<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use Nordkirche\Ndk\Domain\Model\Geocode;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Applies the filters of plugin settings (flexform) to NDK queries.
 * Stateless, usable in controllers, services, CLI and backend previews.
 */
final class QueryFilter
{
    /**
     * @param object $query Query with setGeocode() (InstitutionQuery, PersonQuery, EventQuery, ...)
     */
    public static function geo(object $query, mixed $geosearch, mixed $latitude, mixed $longitude, mixed $radius): void
    {
        if ((int)$geosearch !== 1) {
            return;
        }
        $latitude = (float)$latitude;
        $longitude = (float)$longitude;
        $radius = (float)$radius;
        if ($latitude != 0 && $longitude != 0 && $radius != 0) {
            $query->setGeocode(new Geocode($latitude, $longitude, (int)round($radius)));
        }
    }

    /**
     * @param object $query InstitutionQuery
     * @param string $selectOption CHILDREN, CHILDREN_RECURSIVE, BOTH, BOTH_RECURSIVE or empty (= selected only)
     */
    public static function institutions(object $query, mixed $filter, mixed $selectOption = ''): void
    {
        $resources = self::toList($filter);
        if ($resources === []) {
            return;
        }
        match ((string)$selectOption) {
            'CHILDREN' => $query->setParentInstitutions($resources),
            'CHILDREN_RECURSIVE' => $query->setAncestorInstitutions($resources),
            'BOTH' => $query->setParentInstitutionsOrSelf($resources),
            'BOTH_RECURSIVE' => $query->setAncestorInstitutionsOrSelf($resources),
            default => $query->setInstitutions($resources),
        };
    }

    public static function institutionTypes(object $query, mixed $filter): void
    {
        if (($list = self::toList($filter)) !== []) {
            $query->setInstitutionType($list);
        }
    }

    /**
     * Institutions of persons (PersonQuery)
     */
    public static function personInstitutions(object $query, mixed $filter): void
    {
        if (($list = self::toList($filter)) !== []) {
            $query->setInstitutions($list);
        }
    }

    public static function functionTypes(object $query, mixed $filter): void
    {
        if (($list = self::toList($filter)) !== []) {
            $query->setFunctions($list);
        }
    }

    public static function persons(object $query, mixed $filter): void
    {
        if (($list = self::toList($filter)) !== []) {
            $query->setPersons($list);
        }
    }

    public static function availableFunctions(object $query, mixed $filter): void
    {
        if (($list = self::toList($filter)) !== []) {
            $query->setAvailableFunctions($list);
        }
    }

    public static function categories(object $query, mixed $filter): void
    {
        $list = array_values(array_filter(GeneralUtility::intExplode(',', (string)$filter, true), static fn(int $id): bool => $id > 0));
        if ($list !== []) {
            $query->setCategories($list);
        }
    }

    /**
     * NAPI ids of a list of ids or NAPI URLs ("12, napi:resource/people/13").
     * Values without id (e.g. unresolved placeholders like {rootline:...}) are left out.
     *
     * @return list<string>
     */
    public static function ids(mixed $value): array
    {
        $ids = [];
        foreach (self::toList($value) as $item) {
            if (preg_match('/(\d+)$/', $item, $matches) && (int)$matches[1] > 0) {
                $ids[] = $matches[1];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Institutions of the site (settings.siteInstitutions)
     *
     * @return list<string>
     */
    public static function siteInstitutions(mixed $value): array
    {
        return self::ids($value);
    }

    /**
     * @return string[]
     */
    public static function toList(mixed $filter): array
    {
        if (is_array($filter)) {
            return array_values(array_filter(array_map('strval', $filter), static fn(string $value): bool => $value !== ''));
        }
        return $filter ? GeneralUtility::trimExplode(',', (string)$filter, true) : [];
    }
}
