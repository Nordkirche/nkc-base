<?php

namespace Nordkirche\NkcBase\Controller;

use Nordkirche\NkcBase\Service\ApiService;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AjaxController
{

    /**
     * @param ServerRequestInterface $request
     * @return JsonResponse
     * @throws \Nordkirche\NkcBase\Exception\ApiException
     */
    public function suggest(ServerRequestInterface $request): JsonResponse
    {
        $requestBody = $request->getQueryParams();

        $responseData = [];

        $search = trim($requestBody['search']);

        $allowedObjects = GeneralUtility::trimExplode(',', $requestBody['allowed']);

        $items = [];

        if ($search && count($allowedObjects)) {
            $searchQuery = $this->parseSearchString($search);

            foreach ($allowedObjects as $object) {
                if (trim($object)) {
                    $repository = ApiService::getRepository($object);

                    $query = ApiService::getQuery($object);

                    $items = array_merge($items, $this->getSuggestions($searchQuery, $repository, $query));
                }
            }

            $responseData['query'] = $searchQuery;

            $responseData['items'] = $items;
        }

        return new JsonResponse($responseData, 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    /**
     * @param $search
     * @return string
     */
    private function parseSearchString($search)
    {
        $searchWords = GeneralUtility::trimExplode(' ', $search);

        if (count($searchWords) == 1) {
            return sprintf('*%s*', $searchWords[0]);
        }
        return sprintf('*%s*', implode('* *', $searchWords));
    }

    /**
     * @param string $search
     * @param \Nordkirche\Ndk\Domain\Repository\AbstractRepository $repository
     * @param \Nordkirche\Ndk\Domain\Query\AbstractQuery $query
     * @return array
     */
    private function getSuggestions($search, $repository, $query)
    {
        $query->setQuery($search);

        $collection = $repository->get($query);

        $items = [];

        foreach ($collection as $object) {
            $items[] = [
                'id'	=> $object->getId(),
                'name'	=> htmlspecialchars($object->getLabel()),
                'uri'	=> (string)$object
            ];
        }

        return $items;
    }
}
