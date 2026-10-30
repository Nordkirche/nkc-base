<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Wizard;

use Nordkirche\NkcBase\CustomField\Selector;
use Nordkirche\NkcBase\Service\ApiService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Search wizard of the NAPI item selector, opened in a modal.
 * Selected items are handed to the form via the FormEngine message API.
 */
#[AsController]
final readonly class NapiWizardController
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private HashService $hashService,
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $parameters = (array)($request->getParsedBody()['P'] ?? $request->getQueryParams()['P'] ?? []);
        $allowed = (string)($parameters['allowed'] ?? '');
        $fieldName = (string)($parameters['fieldName'] ?? '');
        $search = trim((string)($request->getParsedBody()['search'] ?? $request->getQueryParams()['search'] ?? ''));

        if (!$this->hashService->validateHmac($allowed, Selector::HMAC_SECRET, (string)($parameters['hmac'] ?? ''))) {
            throw new \InvalidArgumentException('Hmac Validation failed for napi wizard', 1495105254);
        }

        $allowedObjects = GeneralUtility::trimExplode(',', $allowed, true);

        $view = $this->moduleTemplateFactory->create($request);
        $view->assignMultiple([
            'search' => $search,
            'fieldName' => $fieldName,
            'parameters' => $parameters,
            'allowedObjects' => $allowedObjects,
            'searchResult' => $search !== '' ? $this->search($search, $allowedObjects) : [],
        ]);
        return $view->renderResponse('NapiWizard');
    }

    /**
     * @param string[] $allowedObjects
     * @return array<string, iterable>
     */
    private function search(string $search, array $allowedObjects): array
    {
        $result = [];
        foreach ($allowedObjects as $object) {
            $result[$object] = ApiService::search($object, $search, self::PAGE_SIZE);
        }
        return $result;
    }
}
