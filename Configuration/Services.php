<?php

declare(strict_types=1);

use Nordkirche\NkcBase\Dashboard\NapiStatusWidget;
use Nordkirche\NkcBase\MapCache\MapMarkerProviderInterface;
use Nordkirche\NkcBase\MapCache\MarkerInfoProviderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    // Map marker providers of other extensions (nkc_address, nkc_event, ...) are tagged automatically
    $containerBuilder->registerForAutoconfiguration(MapMarkerProviderInterface::class)
        ->addTag(MapMarkerProviderInterface::TAG);
    $containerBuilder->registerForAutoconfiguration(MarkerInfoProviderInterface::class)
        ->addTag(MarkerInfoProviderInterface::TAG);

    $services = $configurator->services();

    // The dashboard widget is only available if EXT:dashboard is installed
    if (interface_exists(WidgetInterface::class)) {
        $services->set('dashboard.widget.nkcbaseNapiStatus')
            ->class(NapiStatusWidget::class)
            ->autowire()
            ->tag('dashboard.widget', [
                'identifier' => 'nkcbaseNapiStatus',
                'groupNames' => 'systemInfo',
                'title' => 'NAPI-Status',
                'description' => 'Ergebnis der letzten automatischen NAPI-Prüfung und Antwortzeiten der letzten Stunde',
                'iconIdentifier' => 'module-nkcbase-napistatus',
                'height' => 'medium',
                'width' => 'small',
            ]);
    }
};
