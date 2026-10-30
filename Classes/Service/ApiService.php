<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use Nordkirche\Ndk\Api;
use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Domain\Repository\AbstractRepository;
use Nordkirche\Ndk\Service\Result;
use Nordkirche\NkcBase\Exception\ApiException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Static facade for the NDK, kept for the NAPI extensions (nkc_address, nkc_event, ...).
 *
 * New code should inject NdkApiFactory instead.
 */
class ApiService
{
    /**
     * @throws ApiException
     */
    public static function get(): Api
    {
        return GeneralUtility::makeInstance(NdkApiFactory::class)->getApi();
    }

    /**
     * Returns the NDK repository for a NAPI object name (event, person, institution, ...)
     *
     * @throws ApiException
     */
    public static function getRepository(string $object): AbstractRepository
    {
        $className = self::resolveClassName($object, 'Repository');
        if ($className === null || !is_subclass_of($className, AbstractRepository::class)) {
            throw new ApiException('Query error - unknown object ' . $object, 1495179007);
        }
        return self::get()->factory($className);
    }

    /**
     * Returns a new NDK query for a NAPI object name (event, person, institution, ...)
     *
     * @throws ApiException
     */
    public static function getQuery(string $object): PageQuery
    {
        $className = self::resolveClassName($object, 'Query');
        if ($className === null || !is_subclass_of($className, PageQuery::class)) {
            throw new ApiException('Configuration error - unknown object ' . $object, 1495179007);
        }
        return new $className();
    }

    /**
     * Fetches all pages of a query and returns the items as one list
     *
     * @param string[] $includes
     */
    public static function getAllItems(AbstractRepository $repository, PageQuery $query, array $includes = [], int $pageSize = 50): array
    {
        $items = [];
        $currentPage = 1;

        $query->setPageSize($pageSize);
        if ($includes !== []) {
            $query->setInclude($includes);
        }

        do {
            $query->setPageNumber($currentPage);
            try {
                /** @var Result $result */
                $result = $repository->get($query);
            } catch (\Exception) {
                return [];
            }
            foreach ($result as $item) {
                $items[] = $item;
            }
            $currentPage++;
        } while ($result->getPageCount() >= $currentPage);

        return $items;
    }

    /**
     * Full text search for one NAPI object type, as used by the backend suggest and wizard
     *
     * @throws ApiException If the object type does not exist or can't be searched
     */
    public static function search(string $object, string $search, int $pageSize = 20): Result
    {
        $query = self::getQuery($object);
        if (!method_exists($query, 'setQuery')) {
            throw new ApiException('Query error - object ' . $object . ' does not support a full text search', 1760889601);
        }
        $query->setQuery(self::buildSearchQuery($search));
        $query->setPageSize($pageSize);
        return self::getRepository($object)->get($query);
    }

    /**
     * Converts user input into a NAPI wildcard search: "foo bar" => "*foo* *bar*"
     */
    public static function buildSearchQuery(string $search): string
    {
        $words = GeneralUtility::trimExplode(' ', $search, true);
        return $words === [] ? '' : '*' . implode('* *', $words) . '*';
    }

    /**
     * Maps a NAPI object name to an NDK class. Only plain names are accepted,
     * as the value may come from a request.
     *
     * @return class-string|null
     */
    private static function resolveClassName(string $object, string $type): ?string
    {
        $object = trim($object);
        if (!preg_match('/^[a-zA-Z]+$/', $object)) {
            return null;
        }
        $className = sprintf('Nordkirche\\Ndk\\Domain\\%s\\%s%s', $type, ucfirst($object), $type);
        return class_exists($className) ? $className : null;
    }
}
