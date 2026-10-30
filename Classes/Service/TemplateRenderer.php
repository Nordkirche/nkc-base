<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * Renders a Fluid template outside of an Extbase action, e.g. the info windows
 * of map markers. Works with and without a frontend request.
 */
class TemplateRenderer
{
    public function __construct(private readonly ViewFactoryInterface $viewFactory) {}

    /**
     * @param array{templateRootPaths?: string[], partialRootPaths?: string[], layoutRootPaths?: string[]} $paths
     */
    public function render(string $template, array $variables, array $paths, ?ServerRequestInterface $request): string
    {
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: $paths['templateRootPaths'] ?? [],
            partialRootPaths: $paths['partialRootPaths'] ?? [],
            layoutRootPaths: $paths['layoutRootPaths'] ?? [],
            request: $request,
        ));
        $view->assignMultiple($variables);
        return $view->render($template);
    }

    /**
     * View paths of an Extbase plugin configuration (view.templateRootPaths, ...)
     */
    public static function pathsFromConfiguration(array $frameworkConfiguration): array
    {
        $paths = [];
        foreach (['templateRootPaths', 'partialRootPaths', 'layoutRootPaths'] as $type) {
            $configured = (array)($frameworkConfiguration['view'][$type] ?? []);
            ksort($configured);
            if ($configured !== []) {
                $paths[$type] = array_values(array_filter($configured));
            }
        }
        return $paths;
    }
}
