<?php

namespace Nordkirche\NkcBase\Command;

/***
 *
 * This file is part of the "NkcBase" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Holger McCloy <mccloy@netzleuchten.com>, netzleuchten GmbH
 *
 ***/
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class MapCacheCommandController extends Command
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setName('Google Karten-Cache Warm-up')
            ->setDescription('Baut den Marker-Cache für Google Karten auf');

        $this->addArgument(
            'contentUids',
            InputArgument::REQUIRED,
            'Eine Liste von Content-Element-IDs, durch Komma separiert'
        );

        $this->addArgument(
            'baseUrl',
            InputArgument::REQUIRED,
            'Die Base Url für einen Request gegen das TYPO3 Frontent (typischerweise Domain der Website)'
        );

        $this->addArgument(
            'forceRefresh',
            InputArgument::OPTIONAL,
            'Soll eine Auffrischung des Caches erzwungen werden?',
            FALSE
        );

        $this->addArgument(
            'frontendMode',
            InputArgument::OPTIONAL,
            'Soll der Cache durch Frontend Aufrufe aufgebaut werden (empfohlen)',
            TRUE
        );

        $this->addArgument(
            'typeNum',
            InputArgument::OPTIONAL,
            'Der Page Type für den Aufruf (siehe ajaxTypeNum)',
            0
        );

        $this->addArgument(
            'httpUser',
            InputArgument::OPTIONAL,
            'Ein optionaler HTTP Username',
            ''
        );

        $this->addArgument(
            'httpPass',
            InputArgument::OPTIONAL,
            'Ein optionales HTTP Passwort',
            ''
        );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $contentUids = $input->getArgument('contentUids');
        $baseUrl  = $input->getArgument('baseUrl');
        $forceRefresh = $input->getArgument('forceRefresh');
        $frontendMode = $input->getArgument('frontendMode');
        $typeNum = $input->getArgument('typeNum');
        $httpUser = $input->getArgument('httpUser');
        $httpPass = $input->getArgument('httpPass');

        $io = new SymfonyStyle($input, $output);
        $io->title('Start map cache warmup');

        $this->objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);
        $persistenceManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);

        if (trim($contentUids)) {
            foreach (GeneralUtility::trimExplode(',', $contentUids) as $uid) {
                if ($frontendMode) {
                    $this->cachePage($uid, $typeNum, $baseUrl, $httpUser, $httpPass, ($forceRefresh == 1) ? 2 : 1);
                } else {
                    $this->createCache($uid, ($forceRefresh == 1) ? 2 : 1);
                }
            }
        } else {
            echo "Error: no content uids given\n";
        }

        // Persist data
        $persistenceManager->persistAll();

        $io->success('Cache warm-up completed.');

        return 0;

    }

    /**
     * @param $uid
     * @param $forceRefresh
     * @throws \TYPO3\CMS\Extbase\Object\Exception
     */
    private function createCache($uid, $forceRefresh)
    {
        if ($content = $this->getContentRecord($uid)) {
            $className = false;

            if ($content['list_type'] == 'nkcaddress_map') {
                /** @var \Nordkirche\NkcAddress\Controller\MapController $controller */
                $className = \Nordkirche\NkcAddress\Controller\MapController::class;
            } elseif ($content['list_type'] == 'nkcevent_map') {
                /** @var \Nordkirche\NkcEvent\Controller\MapController $controller */
                $className = \Nordkirche\NkcEvent\Controller\MapController::class;
            } elseif ($content['list_type'] == 'nkcevent_main') {
                /** @var \Nordkirche\NkcEvent\Controller\EventController $controller */
                $className = \Nordkirche\NkcEvent\Controller\EventController::class;
            }

            if ($className) {
                $controller = $this->objectManager->get($className);
                $flexformSettings = $this->getFlexFormData($content);
                $flexformSettings['settings']['backendContext'] = 1;
                $controller->setSettings($flexformSettings['settings']);
                $controller->initializeAction();
                $controller->buildCache($content, $forceRefresh);
            }
        }
    }

    /**
     * @param $content
     * @return mixed
     */
    private function getFlexFormData($content)
    {
        $ffData = [];

        $flexform = \TYPO3\CMS\Core\Utility\GeneralUtility::xml2array($content['pi_flexform']);

        foreach ($flexform['data'] as $sheet => $sheetData) {
            foreach ($sheetData['lDEF'] as $fieldname => $fieldData) {
                if ($fieldData['vDEF']) {
                    $this->dotNotationToArray($fieldname, $fieldData['vDEF'], $ffData);
                }
            }
        }
        return $ffData;
    }

    /**
     * @param $notation
     * @param $value
     * @param $array
     */
    private function dotNotationToArray($notation, $value, &$array)
    {
        list($key, $notation) = explode('.', $notation, 2);
        if ($notation) {
            $this->dotNotationToArray($notation, $value, $array[$key]);
        } else {
            $array[$key] = $value;
        }
    }

    /**
     * @param $uid
     * @param $typeNum
     * @param $baseUrl
     * @param $httpUser
     * @param $httpPass
     * @param $forceLevel
     * @throws \Exception
     */
    private function cachePage($uid, $typeNum, $baseUrl, $httpUser, $httpPass, $forceLevel = 1)
    {
        if ($content = $this->getContentRecord($uid)) {
            if ($parameter = $this->getParameterNamespace($content)) {
                $requestUrl = sprintf(
                    '%s?id=%s&type=%s&uid=%s&%s[action]=data&%s[forceReload]=%s&no_cache=1',
                    $baseUrl,
                    $content['pid'],
                    $typeNum,
                    $content['uid'],
                    $parameter,
                    $parameter,
                    $forceLevel
                );
                $this->doPageRequest($requestUrl, $httpUser, $httpPass);
            }
        }
    }

    /**
     * @param $content
     * @return bool|string
     */
    private function getParameterNamespace($content)
    {
        if ($content['list_type']) {
            return 'tx_' . $content['list_type'];
        }
        return false;
    }

    /**
     * @param $uid
     * @return mixed
     */
    private function getContentRecord($uid)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tt_content');

        $where = 'hidden = 0 AND deleted = 0 AND uid = ' . (int)$uid;
        $res = $connection->executeQuery('SELECT * FROM tt_content WHERE ' . $where);
        if ($res->rowCount()) {
            foreach ($res as $row) {
                return $row;
            }
        }
    }

    /**
     * @param $url
     * @param $httpUser
     * @param $httpPass
     * @throws \Exception
     */
    protected function doPageRequest($url, $httpUser, $httpPass)
    {
        $content = false;

        if (function_exists('curl_init')) {
            echo "Getting $url\n";
            $c = curl_init($url);
            curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($c, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($c, CURLOPT_MAXREDIRS, 5);
            curl_setopt($c, CURLOPT_HEADER, 0);

            if (!empty($httpUser) && !empty($httpPass)) {
                curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($c, CURLOPT_USERPWD, $httpUser . ':' . $httpPass);
            }

            $content = curl_exec($c);
            $info = curl_getinfo($c);
            curl_close($c);
        } else {
            throw new \Exception('cURL not supported. Need PHP with cURL support.');
        }
    }
}
