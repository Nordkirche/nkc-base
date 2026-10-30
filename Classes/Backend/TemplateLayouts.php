<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\Backend;

use TYPO3\CMS\Backend\Utility\BackendUtility;

/**
 * itemsProcFunc for template layouts of the plugins, extendable per page TSconfig.
 * The TSconfig path is part of the field configuration:
 *
 *   <config>
 *       <type>select</type>
 *       <itemsProcFunc>Nordkirche\NkcBase\Backend\TemplateLayouts->addItems</itemsProcFunc>
 *       <templateLayouts>tx_nkcevent.templateLayouts</templateLayouts>
 *   </config>
 *
 *   tx_nkcevent.templateLayouts {
 *       NoImage = Ohne Bild
 *   }
 *
 * Layout names are reduced to letters and digits.
 */
class TemplateLayouts
{
    public function addItems(array &$params): void
    {
        $path = (string)($params['config']['templateLayouts'] ?? '');
        if ($path === '') {
            return;
        }
        $pageId = (int)($params['flexParentDatabaseRow']['pid'] ?? $params['row']['pid'] ?? 0);
        // TYPO3 v14: SelectItem objects (ArrayAccess), v13: arrays
        $existing = array_map(
            static fn(array|\ArrayAccess $item): string => (string)($item['value'] ?? $item[1] ?? ''),
            (array)($params['items'] ?? [])
        );
        foreach ($this->getLayouts($pageId, $path) as $value => $label) {
            $value = (string)preg_replace('/[^A-Za-z0-9]/', '', (string)$value);
            if ($value !== '' && is_string($label) && !in_array($value, $existing, true)) {
                $params['items'][] = ['label' => $label, 'value' => $value];
                $existing[] = $value;
            }
        }
    }

    /**
     * @return array<string|int, mixed>
     */
    protected function getLayouts(int $pageId, string $path): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($pageId);
        foreach (explode('.', $path) as $segment) {
            $tsConfig = $tsConfig[$segment . '.'] ?? null;
            if (!is_array($tsConfig)) {
                return [];
            }
        }
        return $tsConfig;
    }
}
