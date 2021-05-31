<?php

namespace Nordkirche\NkcBase\Wizard;

use Nordkirche\NkcBase\Service\ApiService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Controller\Wizard\AbstractWizardController;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NapiWizardController extends AbstractWizardController
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
     * @var \Nordkirche\Ndk\Domain\Repository\AbstractRepository
     */
    protected $repository;

    /**
     * ModuleTemplate object
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialises the Class
     *
     * @throws \InvalidArgumentException
     */
    public function init()
    {
        // Setting GET vars (used in frameset script):
        $this->P = GeneralUtility::_GP('P');
        $this->allowed = $this->P['allowed'];
        $this->search = GeneralUtility::_GP('search');
        $this->field = $this->P['field'];

        $hmac_validate = GeneralUtility::hmac('wizard_napi', $this->allowed);
        if (!$this->P['hmac'] || ($this->P['hmac'] !== $hmac_validate)) {
            throw new \InvalidArgumentException('Hmac Validation failed for napi wizard', 1495105254);
        }

        $this->lang = $this->getLanguageService();
    }

    /**
     * Injects the request object for the current request or subrequest
     * As this controller goes only through the main() method, it is rather simple for now
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->moduleTemplate = GeneralUtility::makeInstance(ModuleTemplate::class);

        $this->main();

        $this->moduleTemplate->setContent($this->content);

        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    /**
     * Main...
     */
    private function main()
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();

        $this->moduleTemplate->getPageRenderer()->loadRequireJsModule('jquery');

        $closeButton = $buttonBar->makeLinkButton()
            ->setHref('#')
            ->setTitle('Schließen')
            ->setOnClick('window.close();return true;')
            ->setIcon($this->moduleTemplate->getIconFactory()->getIcon('actions-close', Icon::SIZE_SMALL))
            ->setShowLabelText(true);
        $buttonBar->addButton($closeButton);

        $allowedObjects = GeneralUtility::trimExplode(',', $this->allowed);

        $this->content .= $this->getWizardHeader($allowedObjects);

        $this->content .= sprintf('<form method="post"><div class="form-group">
		<input type="text" name="search" id="search" class="form-crontrol" placeholder="%s" value="' . htmlentities($this->search) . '" style="height: 32px;float:left"/><input type="submit" class="btn btn-default" value="%s" />
		</div></form>
		', $this->lang->sL($this->localLang . 'search.placeholder', 'nkc_base'), $this->lang->sL($this->localLang . 'search.button', 'nkc_base'));

        if ($this->search) {
            // Perform search
            $this->content .= $this->getItems($allowedObjects);
        } else {
            // Give feedback
            $this->content .= sprintf('<p>%s</p>', $this->lang->sL($this->localLang . 'search.hint', 'nkc_base'));
        }

        $this->content .= $this->javascript($this->field);
    }

    /**
     * @param array $allowedObjects
     * @return string
     */
    private function getWizardHeader($allowedObjects)
    {
        $html = '<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>';
        $html = '';
        $html .= sprintf('<h1>%s ', $this->lang->sL($this->localLang . 'header.find', 'nkc_base'));

        if ($this->search) {
            $html .= '"';
            $html .= htmlentities($this->search);
            $html .= '" ';
            $html .= $this->lang->sL($this->localLang . 'header.find_in', 'nkc_base');
        }

        $c = 0;
        foreach ($allowedObjects as $object) {
            $html .= ($c) ? ', ' : ' ';
            $html .= $this->lang->sL($this->localLang . 'napi_objects.' . $object, 'nkc_base');
            $c++;
        }

        $html .= '</h1>';

        return $html;
    }

    /**
     * @param array $allowedObjects
     * @return string
     * @throws \Nordkirche\NkcBase\Exception\ApiException
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

            $c = 0;

            if (count($searchResult)) {
                foreach ($searchResult as $object => $collection) {
                    if ($collection) {
                        if (count($searchResult) > 0) {
                            $html .= '<h3>' . $this->lang->sL($this->localLang . 'napi_objects.' . $object, 'nkc_base') . '</h3>';
                        }
                        $html .= '<p>';
                        foreach ($collection as $item) {
                            $renderMethod = 'render' . ucfirst($object);
                            if (method_exists($this, $renderMethod)) {
                                $html .= $this->$renderMethod($item);
                            } else {
                                $html .= $this->renderDefault($item);
                            }
                            $html .= '<br /><br />';
                            $c++;
                        }
                        $html .= '</p>';
                    }
                }

                if ($c == 0) {
                    $html .= sprintf('<p>%s</p>', $this->lang->sL($this->localLang . 'search.feedback', 'nkc_base'));
                }
            } else {
                $html .= sprintf('<p>%s</p>', $this->lang->sL($this->localLang . 'search.feedback', 'nkc_base'));
            }
        }

        return $html;
    }

    /**
     * @param $item
     * @return string
     */
    private function renderDefault($item)
    {
        $html = '<strong><a href="';
        $html .= (string)$item;
        $html .= '" class="t3js-pageLink">';
        $html .= $item->getLabel();
        $html .= '</a></strong>';
        return $html;
    }

    /**
     * @param $event
     * @return string
     */
    private function renderEvent($event)
    {
        $html = '<strong><a href="';
        $html .= (string)$event;
        $html .= '" class="t3js-pageLink">';
        $html .= $event->getLabel();
        $html .= '</a></strong>';
        $html .= '<span style="color: #aaa">';

        if ($address = $event->getAddress()) {
            $html .= '<br />';
            $html .= $address->getStreet() . ', ';
            $html .= $address->getZipCode() . ' ';
            $html .= $address->getCity();
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * @param $institution
     * @return string
     */
    private function renderInstitution($institution)
    {
        $html = '<strong><a href="';
        $html .= (string)$institution;
        $html .= '" class="t3js-pageLink">';
        $html .= $institution->getName();
        $html .= '</a></strong>';
        $html .= '<span style="color: #aaa">';

        /*
        if ($type = $institution->getInstitutionType()) {
            $html .= '<br />';
            $html .= $type->getName();
        }
        */

        if ($address = $institution->getAddress()) {
            $html .= '<br />';
            $html .= $address->getStreet() . ', ';
            $html .= $address->getZipCode() . ' ';
            $html .= $address->getCity();
        }

        $html .= '</span>';

        return $html;
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
     * @param $field
     * @return string
     */
    private function javascript($field)
    {
        return <<<EON
<script language="JavaScript">
require(['jquery'], function ($) {
    function resolveValidationRules(opener, field) {

        var result = true;
    
        field = opener.$(field);
        rules = field.data('formengine-validation-rules');
    
        $.each(rules, function(k, rule) {
            if ((rule.minItems > 0 ) && (rule.minItems > field.find('option').length)) {
                result = false;
            }
            if ((rule.maxItems > 0 ) && (rule.maxItems < field.find('option').length)) {
                result = false;
            }
        });
        return result;
    };	

    function addListener() {
        $('a.t3js-pageLink').on('click', function(e) {
            e.preventDefault();
            opener = window.opener;
            var element = '<option value="' + $(this).attr('href') + '">' + $(this).text() + '</option>'; 
            if (opener) {
                var valueField = opener.$('#tceforms-multiselect-value-$field');
                var newValue = valueField.val();
                
                opener.$('#tceforms-multiselect-$field').append(element);
                
                if (newValue != '') newValue += ',';
                newValue += $(this).attr('href');
                valueField.val(newValue);
                
                formGroup = opener.$('#tceforms-multiselect-$field').parent('.form-wizards-element').parent('.form-wizards-wrap').parent('.formengine-field-item').parent('.formengine-field-item').parent('.form-group');
        
                if (resolveValidationRules(opener, '#tceforms-multiselect-$field') == true) {
                    formGroup.removeClass('has-error');
                } else {
                    formGroup.addClass('has-error');
                }
                self.close();
            }
        });
    }  
    
    $(document).ready(function() {
        addListener();
    });
});
</script>
EON;
    }

    /**
     * Returns LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
