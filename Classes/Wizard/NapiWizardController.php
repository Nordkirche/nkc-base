<?php

namespace Nordkirche\NkcBase\Wizard;

use Nordkirche\Ndk\Domain\Repository\AbstractRepository;
use Nordkirche\NkcBase\Exception\ApiException;
use Nordkirche\NkcBase\Service\ApiService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\IconFactory;

// the module template will be initialized in handleRequest()
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[AsController]
final class NapiWizardController
{
    /**
     * @var array
     */
    protected $P = [];

    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var string
     */
    protected $allowed;

    /**
     * @var string
     */
    protected $search;

    /**
     * @var
     */
    protected $lang;

    /**
     * @var string
     */
    protected $localLang = 'LLL:EXT:nkc_base/Resources/Private/Language/locallang.xlf:';

    /**
     * @var AbstractRepository
     */
    protected $repository;

    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param IconFactory $iconFactory
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly IconFactory $iconFactory,
    ) {}

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);

        // Setting GET vars (used in frameset script):
        $this->P = $request->getParsedBody()['P'] ?? $request->getQueryParams()['P'] ?? null;
        $this->allowed = $this->P['allowed'];
        $this->search = $request->getParsedBody()['search'] ?? $request->getQueryParams()['search'] ?? null;
        $this->field = $this->P['field'];

        $hmac_validate = GeneralUtility::hmac('wizard_napi', $this->allowed);
        if (!$this->P['hmac'] || ($this->P['hmac'] !== $hmac_validate)) {
            throw new \InvalidArgumentException('Hmac Validation failed for napi wizard', 1495105254);
        }

        $this->main();

        return new HtmlResponse($this->moduleTemplate->render('NapiWizard'));
    }

    /**
     * Main...
     */
    private function main()
    {
        $allowedObjects = GeneralUtility::trimExplode(',', $this->allowed);

        if ($this->search) {
            // Perform search
            $this->moduleTemplate->assign('searchResult', $this->getItems($allowedObjects));
        }

        $this->moduleTemplate->assignMultiple(
            [
                'search'            => $this->search,
                'fieldId'           => $this->field,
                'allowedObjects'    => $allowedObjects,
            ]
        );
    }

    /**
     * @param array $allowedObjects
     * @return array
     * @throws ApiException
     */
    private function getItems($allowedObjects)
    {
        $html = '';

        $searchResult = [];

        if ($this->search && count($allowedObjects)) {
            $searchQuery = $this->parseSearchString($this->search);

            foreach ($allowedObjects as $object) {
                if (trim($object)) {
                    $repository = ApiService::getRepository($object);

                    $query = ApiService::getQuery($object);

                    $query->setQuery($searchQuery);
                    $query->setPageSize(50);

                    $searchResult[$object] = $repository->get($query);
                }
            }
        }

        return $searchResult;
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
}
