<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Controller;

use Nordkirche\Ndk\Configuration;
use Nordkirche\NkcBase\Napi\Health\HealthState;
use Nordkirche\NkcBase\Napi\Health\NapiProbe;
use Nordkirche\NkcBase\Napi\Traffic\TrafficStats;
use Nordkirche\NkcBase\Service\NapiSettings;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\JsonResponse;

/**
 * Backend module "NAPI-Status" (System > NAPI-Status)
 */
#[AsController]
final readonly class NapiStatusController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private NapiSettings $settings,
        private NapiProbe $probe,
        private HealthState $state,
        private TrafficStats $trafficStats,
    ) {}

    public function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $lastReport = $this->state->getLastReport();

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle('NAPI-Status');
        $view->assignMultiple([
            'configuration' => [
                'baseUri' => $this->settings->getMissing() === [] ? $this->settings->getBaseUri() : '',
                'host' => $this->settings->get('NDK_NAPI_HOST'),
                'version' => $this->settings->get('NDK_NAPI_VERSION'),
                'userId' => $this->settings->get('NDK_NAPI_USER_ID'),
                'tokenSet' => $this->settings->get('NDK_NAPI_ACCESS_TOKEN') !== '',
                'timeout' => $this->settings->getTimeout(),
                'slowThreshold' => $this->settings->getSlowThreshold(),
                'endpoints' => implode(', ', $this->settings->getHealthEndpoints()),
                'alertRecipients' => implode(', ', $this->settings->getAlertRecipients()),
                'alertAfter' => $this->settings->getAlertAfter(),
            ],
            'missingSettings' => $this->settings->getMissing(),
            'trafficSupported' => method_exists(Configuration::class, 'addMiddleware'),
            'traffic' => [
                'hour' => $this->trafficStats->getSummary(1),
                'day' => $this->trafficStats->getSummary(24),
            ],
            'lastReport' => $lastReport !== null ? json_decode((string)json_encode($lastReport), true) : null,
            'failuresInRow' => $this->state->getFailuresInRow(),
            'alertOpen' => $this->state->isAlertOpen(),
        ]);
        return $view->renderResponse('NapiStatus/Index');
    }

    /**
     * AJAX route "NkcBase::napiHealth": runs the live check
     */
    public function checkAction(ServerRequestInterface $request): ResponseInterface
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || !$backendUser->isAdmin()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }
        return new JsonResponse($this->probe->run()->jsonSerialize());
    }
}
