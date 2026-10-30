<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Dashboard;

use Nordkirche\NkcBase\Napi\Health\HealthState;
use Nordkirche\NkcBase\Napi\Traffic\TrafficStats;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

/**
 * Dashboard widget: result of the last automatic NAPI check and traffic of the last hour.
 * Only registered if EXT:dashboard is installed (see Configuration/Services.php).
 */
class NapiStatusWidget implements WidgetInterface, RequestAwareWidgetInterface, AdditionalCssInterface
{
    private const MODULE = 'nkcbase_napistatus';

    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly BackendViewFactory $backendViewFactory,
        private readonly HealthState $state,
        private readonly TrafficStats $trafficStats,
        private readonly UriBuilder $uriBuilder,
        private readonly ModuleProvider $moduleProvider,
        private readonly array $options = [],
    ) {}

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $report = $this->state->getLastReport();

        $view = $this->backendViewFactory->create($this->request, ['typo3/cms-dashboard', 'nordkirche/nkc-base']);
        $view->assignMultiple([
            'configuration' => $this->configuration,
            'report' => $report !== null ? json_decode((string)json_encode($report), true) : null,
            'traffic' => $this->trafficStats->getSummary(1),
            'moduleUri' => $this->getModuleUri(),
        ]);
        return $view->render('Widget/NapiStatusWidget');
    }

    /**
     * Link to the module "NAPI-Status", only for users with access to it (admins).
     * Editors can see the widget, but the module would deny access.
     */
    public function getModuleUri(): string
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication || !$this->moduleProvider->accessGranted(self::MODULE, $backendUser)) {
            return '';
        }
        return (string)$this->uriBuilder->buildUriFromRoute(self::MODULE);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getCssFiles(): array
    {
        return ['EXT:nkc_base/Resources/Public/Css/napi-status.css'];
    }
}
