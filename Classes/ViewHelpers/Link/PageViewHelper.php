<?php

namespace Nordkirche\NkcBase\ViewHelpers\Link;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * A view helper for creating links to TYPO3 pages.
 *
 * IMPORTANT: This viewhelper is for CLI / backend context
 *
 * = Examples =
 *
 * <code title="link to the current page">
 * <f:link.page>page link</f:link.page>
 * </code>
 * <output>
 * <a href="index.php?id=123">page link</f:link.action>
 * (depending on the current page and your TS configuration)
 * </output>
 *
 * <code title="query parameters">
 * <f:link.page pageUid="1" additionalParams="{foo: 'bar'}">page link</f:link.page>
 * </code>
 * <output>
 * <a href="index.php?id=1&foo=bar">page link</f:link.action>
 * (depending on your TS configuration)
 * </output>
 *
 * <code title="query parameters for extensions">
 * <f:link.page pageUid="1" additionalParams="{extension_key: {foo: 'bar'}}">page link</f:link.page>
 * </code>
 * <output>
 * <a href="index.php?id=1&extension_key[foo]=bar">page link</f:link.action>
 * (depending on your TS configuration)
 * </output>
 */
class PageViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    /**
     * Arguments initialization
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerUniversalTagAttributes();
        $this->registerTagAttribute('target', 'string', 'Target of link', false);
        $this->registerTagAttribute('rel', 'string', 'Specifies the relationship between the current document and the linked document', false);

        $this->registerArgument('pageUid', 'string', 'target page. See TypoLink destination', false, null);
        $this->registerArgument('additionalParams', 'string', 'query parameters to be attached to the resulting URI', false, []);
        $this->registerArgument('pageType', 'integer', 'type of the target page. See typolink.parameter', false, 0);
        $this->registerArgument('noCache', 'boolean', 'set this to disable caching for the target page. You should not need this.', false, false);
        $this->registerArgument('noCacheHash', 'boolean', 'set this to suppress the cHash query parameter created by TypoLink. You should not need this.', false, false);
        $this->registerArgument('section', 'string', 'the anchor to be added to the URI', false, '');
        $this->registerArgument('linkAccessRestrictedPages', 'boolean', 'If set, links pointing to access restricted pages will still link to the page even though the page cannot be accessed.', false, false);
        $this->registerArgument('absolute', 'boolean', 'If set, the URI of the rendered link is absolute', false, false);
        $this->registerArgument('addQueryString', 'boolean', 'If set, the current query parameters will be kept in the URI', false, false);
        $this->registerArgument('argumentsToBeExcludedFromQueryString', 'array', 'arguments to be removed from the URI. Only active if $addQueryString = TRUE', false, []);
        $this->registerArgument('addQueryStringMethod', 'string', 'Set which parameters will be kept. Only active if $addQueryString = TRUE', false, null);
    }

    /**
     * @return string Rendered page URI
     */
    public function render()
    {
        $pageUid = $this->arguments['pageUid'];
        $additionalParams = $this->arguments['additionalParams'];
        $pageType = $this->arguments['pageType'];
        $noCache = $this->arguments['noCache'];
        $noCacheHash = $this->arguments['noCacheHash'];
        $section = $this->arguments['section'];
        $linkAccessRestrictedPages = $this->arguments['linkAccessRestrictedPages'];
        $absolute = $this->arguments['absolute'];
        $addQueryString  = $this->arguments['addQueryString'];
        $argumentsToBeExcludedFromQueryString = $this->arguments['argumentsToBeExcludedFromQueryString'];
        $addQueryStringMethod = $this->arguments['addQueryStringMethod'];

        $this->initTSFE();
        $uriBuilder = $this->renderingContext->getControllerContext()->getUriBuilder();
        $uri = $uriBuilder->reset()
            ->setTargetPageUid($pageUid)
            ->setTargetPageType($pageType)
            ->setNoCache($noCache)
            ->setUseCacheHash(!$noCacheHash)
            ->setSection($section)
            ->setLinkAccessRestrictedPages($linkAccessRestrictedPages)
            ->setArguments($additionalParams)
            ->setCreateAbsoluteUri($absolute)
            ->setAddQueryString($addQueryString)
            ->setArgumentsToBeExcludedFromQueryString($argumentsToBeExcludedFromQueryString)
            ->setAddQueryStringMethod($addQueryStringMethod)
            ->buildFrontendUri();
        if ((string)$uri !== '') {
            $this->tag->addAttribute('href', $uri);
            $this->tag->setContent($this->renderChildren());
            $result = $this->tag->render();
        } else {
            $result = $this->renderChildren();
        }
        return $result;
    }

    /**
     * See: https://bitbucket.org/reelworx/rx_scheduled_social/src/8a2844d76b2e443e52b23a1eae131a7bbca86109/Classes/Utility/LinkUtility.php
     * @author Johannes Kasberger (Reelworx GmbH)
     * @param int $pageUid
     * @return bool
     */
    private function initTSFE($pageUid = 1) {
        if (isset($GLOBALS['TSFE']) || !$pageUid) {
            return false;
        }
        $pageRendererBackup = GeneralUtility::makeInstance(PageRenderer::class);
        $instances = GeneralUtility::getSingletonInstances();
        unset($instances[PageRenderer::class]);
        GeneralUtility::resetSingletonInstances($instances);

        // simulate a normal FE without any logged-in FE or BE user
        // enable search to root page
        $GLOBALS['TYPO3_CONF_VARS']['FE']['pageNotFound_handling'] = '';
        // $TYPO3_CONF_VARS, $id, $type, $no_cache = '', $cHash = '', $jumpurl = '', $MP = '', $RDCT = ''
        /** @var TypoScriptFrontendController $tsfe */
        $tsfe = GeneralUtility::makeInstance(TypoScriptFrontendController::class, null, $pageUid, 0);
        $GLOBALS['TSFE'] = $tsfe;

        try {
            $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
            $frontendUser->start();
            $frontendUser->unpack_uc();
            // Keep the backwards-compatibility for TYPO3 v9, to have the fe_user within the global TSFE object
            $GLOBALS['TSFE']->fe_user = $frontendUser;

            $tsfe->clear_preview();
            $tsfe->determineId();
            $tsfe->getFromCache();
            $tsfe->getConfigArray();
        } catch (\Exception $e) {
            GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRendererBackup);
            return false;
        }

        // calculate the absolute path prefix
        if (!empty($tsfe->config['config']['socialDomain'])) {
            $absRefPrefix = trim($tsfe->config['config']['socialDomain']);
            if ($absRefPrefix === 'auto') {
                $tsfe->absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
            } else {
                $tsfe->absRefPrefix = $absRefPrefix;
            }
        } else {
            $tsfe->absRefPrefix = '';
        }
        $tsfe->newCObj();

        GeneralUtility::setSingletonInstance(PageRenderer::class, $pageRendererBackup);

        return true;
    }

}
