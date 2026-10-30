<?php

declare(strict_types=1);

namespace Nordkirche\NkcBase\CustomField;

use Nordkirche\Ndk\Service\NapiService;
use Nordkirche\NkcBase\Service\ApiService;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * FormEngine element "napiItemSelector": selects NAPI objects (persons, institutions, ...)
 * by their resource URI. Works like the core "group" element, with a NAPI suggest
 * search and a search wizard.
 *
 * TCA / flexform configuration:
 *   'config' => [
 *       'type' => 'user',
 *       'renderType' => 'napiItemSelector',
 *       'allowed' => 'institution,person',
 *       'maxitems' => 10,
 *       'size' => 5,
 *   ]
 */
class Selector extends AbstractFormElement
{
    public const HMAC_SECRET = 'wizard_napi';

    public function __construct(
        private readonly IconFactory $iconFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly HashService $hashService,
    ) {
        // TYPO3 14.2+ renders field descriptions next to the label
        if ((new Typo3Version())->getMajorVersion() < 14) {
            $this->defaultFieldInformation = ['tcaDescription' => ['renderType' => 'tcaDescription']];
        }
    }

    public function render(): array
    {
        $languageService = $this->getLanguageService();
        $resultArray = $this->initializeResultArray();

        $row = $this->data['databaseRow'];
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];
        $elementName = $parameterArray['itemFormElName'];

        $allowed = (string)($config['allowed'] ?? '');
        $maxItems = (int)($config['maxitems'] ?? 0);
        $isMultiple = $maxItems !== 1;

        $selectedItems = $this->getObjects(GeneralUtility::trimExplode(',', (string)$parameterArray['itemFormElValue'], true));

        $size = (int)($config['size'] ?? 5);
        $autoSizeMax = (int)($config['autoSizeMax'] ?? 0);
        if ($autoSizeMax > 0) {
            $size = MathUtility::forceIntegerInRange($size, 1);
            $size = MathUtility::forceIntegerInRange(count($selectedItems) + 1, $size, $autoSizeMax);
        }

        $fieldId = StringUtility::getUniqueId('tceforms-multiselect-');

        $listOfSelectedValues = [];
        $selectorOptionsHtml = [];
        foreach ($selectedItems as $selectedItem) {
            $uri = (string)$selectedItem;
            $listOfSelectedValues[] = $uri;
            $title = (string)$selectedItem->getLabel();
            if ($title === '') {
                $title = '[' . $languageService->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.no_title') . ']';
            }
            $selectorOptionsHtml[] = '<option value="' . htmlspecialchars($uri) . '" title="' . htmlspecialchars($title) . '">'
                . htmlspecialchars($this->appendValueToLabelInDebugMode(GeneralUtility::fixed_lgd_cs($title, (int)($this->getBackendUser()->uc['titleLen'] ?? 50)), $uri))
                . '</option>';
        }

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $selectHtml = static fn(array $attributes): string => '<select ' . GeneralUtility::implodeAttributes($attributes, true) . '>'
            . implode("\n", $selectorOptionsHtml)
            . '</select>';

        if (!empty($config['readOnly'])) {
            $attributes = ['id' => $fieldId, 'size' => (string)$size, 'disabled' => 'disabled', 'class' => 'form-select'];
            if ($isMultiple && $size !== 1) {
                $attributes['multiple'] = 'multiple';
            }
            $resultArray['html'] = implode("\n", [
                $this->renderLabel($fieldId),
                '<div class="formengine-field-item t3js-formengine-field-item">',
                $fieldInformationResult['html'],
                '<div class="form-wizards-wrap">',
                '<div class="form-wizards-element form-wizards-item-element">' . $selectHtml($attributes) . '</div>',
                '</div>',
                '</div>',
            ]);
            return $resultArray;
        }

        $selectorAttributes = [
            'id' => $fieldId,
            'data-formengine-input-name' => $elementName,
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
            'data-maxitems' => (string)$maxItems,
            'size' => (string)$size,
            'class' => 'form-select',
        ];
        if ($isMultiple && $size !== 1) {
            $selectorAttributes['multiple'] = 'multiple';
        }

        $html = [];
        $html[] = $this->renderLabel($fieldId);
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] = $fieldInformationResult['html'];
        $html[] = '<div class="form-wizards-wrap napi-object-selector">';
        if (empty($config['hideSuggest'])) {
            $html[] = $this->renderSuggest($fieldId, $elementName, $allowed);
        }
        $html[] = '<div class="form-wizards-element form-wizards-item-element">';
        $html[] = '<input type="hidden" data-formengine-input-name="' . htmlspecialchars($elementName) . '" value="' . (!empty($config['multiple']) ? '1' : '') . '" />';
        $html[] = $selectHtml($selectorAttributes);
        $html[] = '</div>';
        $html[] = $this->renderButtons($elementName, (int)$row['uid'], $maxItems, $size, $config);
        $html[] = '<div class="form-wizards-items-aside form-wizards-item-aside">';
        $html[] = '<div class="btn-group-vertical">' . $this->renderWizardButton($elementName, $allowed) . '</div>';
        $html[] = '</div>';
        $html[] = '</div>';
        $html[] = '<input type="hidden" name="' . htmlspecialchars($elementName) . '" value="' . htmlspecialchars(implode(',', $listOfSelectedValues)) . '" />';
        $html[] = '</div>';

        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@typo3/backend/form-engine/element/group-element.js')
            ->instance($fieldId);
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create('@nordkirche/nkc-base/napi-selector.js');
        $resultArray['stylesheetFiles'][] = 'EXT:nkc_base/Resources/Public/Css/napi-selector.css';

        $resultArray['html'] = implode("\n", $html);
        return $resultArray;
    }

    private function renderSuggest(string $fieldId, string $elementName, string $allowed): string
    {
        $placeholder = $this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:labels.findRecord');

        return '<div class="form-wizards-items-top form-wizards-item-top">'
            . '<div class="autocomplete t3-form-suggest-container napi-suggest">'
            . '<div class="input-group">'
            . '<span class="input-group-text">' . $this->iconFactory->getIcon('actions-search', IconSize::SMALL)->render() . '</span>'
            . '<input type="search" autocomplete="off" class="form-control t3js-napi-suggest"'
            . ' placeholder="' . htmlspecialchars($placeholder) . '"'
            . ' data-allowed="' . htmlspecialchars($allowed) . '"'
            . ' data-field-name="' . htmlspecialchars($elementName) . '"'
            . ' data-select-id="' . htmlspecialchars($fieldId) . '"'
            . ' />'
            . '</div>'
            . '<div class="napi-suggest-results t3js-napi-suggest-results" role="listbox"></div>'
            . '</div>'
            . '</div>';
    }

    private function renderButtons(string $elementName, int $uid, int $maxItems, int $size, array $config): string
    {
        $showMoveIcons = empty($config['hideMoveIcons']) && $maxItems !== 1 && $size > 1;
        $showDeleteControl = empty($config['hideDeleteIcon']);

        $buttons = [];
        if ($showMoveIcons) {
            if ($size >= 5) {
                $buttons[] = $this->renderButton('t3js-btn-moveoption-top', 'actions-move-to-top', 'labels.move_to_top', $elementName);
            }
            $buttons[] = $this->renderButton('t3js-btn-moveoption-up', 'actions-move-up', 'labels.move_up', $elementName);
            $buttons[] = $this->renderButton('t3js-btn-moveoption-down', 'actions-move-down', 'labels.move_down', $elementName);
            if ($size >= 5) {
                $buttons[] = $this->renderButton('t3js-btn-moveoption-bottom', 'actions-move-to-bottom', 'labels.move_to_bottom', $elementName);
            }
        }
        if ($showDeleteControl) {
            $buttons[] = $this->renderButton('t3js-btn-removeoption t3js-revert-unique', 'actions-selection-delete', 'labels.remove_selected', $elementName, $uid);
        }

        if ($buttons === []) {
            return '';
        }
        return '<div class="form-wizards-items-aside form-wizards-item-aside form-wizards-item-aside--move">'
            . '<div class="btn-group-vertical">' . implode('', $buttons) . '</div>'
            . '</div>';
    }

    private function renderButton(string $class, string $icon, string $label, string $elementName, ?int $uid = null): string
    {
        $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:core/Resources/Private/Language/locallang_core.xlf:' . $label));
        return '<button type="button" class="btn btn-default t3js-btn-option ' . $class . '"'
            . ' data-fieldname="' . htmlspecialchars($elementName) . '"'
            . ($uid !== null ? ' data-uid="' . $uid . '"' : '')
            . ' title="' . $title . '">'
            . $this->iconFactory->getIcon($icon, IconSize::SMALL)->render()
            . '<span class="visually-hidden">' . $title . '</span>'
            . '</button>';
    }

    private function renderWizardButton(string $elementName, string $allowed): string
    {
        $wizardUrl = (string)$this->uriBuilder->buildUriFromRoute('wizard_napi', [
            'P' => [
                'fieldName' => $elementName,
                'allowed' => $allowed,
                'hmac' => $this->hashService->hmac($allowed, self::HMAC_SECRET),
            ],
        ]);
        $title = htmlspecialchars($this->getLanguageService()->sL('LLL:EXT:nkc_base/Resources/Private/Language/locallang.xlf:wizard.title'));

        return '<button type="button" class="btn btn-default t3js-napi-wizard"'
            . ' data-url="' . htmlspecialchars($wizardUrl) . '"'
            . ' title="' . $title . '">'
            . $this->iconFactory->getIcon('actions-insert-record', IconSize::SMALL)->render()
            . '<span class="visually-hidden">' . $title . '</span>'
            . '</button>';
    }

    /**
     * Resolves the selected NAPI resource URIs to objects
     *
     * @param string[] $uris
     */
    private function getObjects(array $uris): array
    {
        if ($uris === []) {
            return [];
        }
        try {
            /** @var NapiService $napiService */
            $napiService = ApiService::get()->factory(NapiService::class);
            return $napiService->resolveUrls($uris);
        } catch (\Throwable $e) {
            $this->logger?->warning('Could not resolve NAPI objects: ' . $e->getMessage(), ['uris' => $uris]);
            return [];
        }
    }
}
