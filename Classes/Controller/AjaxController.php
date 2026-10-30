<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Controller;

use Nordkirche\NkcBase\Service\ApiService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Backend AJAX route "NkcBase::suggest" of the NAPI item selector
 */
#[AsController]
final readonly class AjaxController
{
    private const MAX_ITEMS_PER_OBJECT = 20;

    public function __construct(private LoggerInterface $logger) {}

    public function suggest(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $searchQuery = ApiService::buildSearchQuery((string)($params['search'] ?? ''));
        $allowedObjects = GeneralUtility::trimExplode(',', (string)($params['allowed'] ?? ''), true);

        if ($searchQuery === '' || $allowedObjects === []) {
            return new JsonResponse(['query' => $searchQuery, 'items' => []]);
        }

        $items = [];
        foreach ($allowedObjects as $object) {
            try {
                foreach (ApiService::search($object, (string)$params['search'], self::MAX_ITEMS_PER_OBJECT) as $item) {
                    $items[] = [
                        'id' => $item->getId(),
                        'name' => (string)$item->getLabel(),
                        'uri' => (string)$item,
                        'type' => $object,
                    ];
                }
            } catch (\Throwable $e) {
                $this->logger->warning('NAPI suggest failed: ' . $e->getMessage(), ['object' => $object]);
                return new JsonResponse(['query' => $searchQuery, 'items' => [], 'error' => $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['query' => $searchQuery, 'items' => $items]);
    }
}
