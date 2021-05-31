<?php

namespace Nordkirche\NkcBase\Command;

/***
 *
 * This file is part of the "NkcBase" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Holger McCloy &lt;mccloy@netzleuchten.com&gt;, netzleuchten GmbH
 *
 ***/

use Nordkirche\Ndk\Domain\Model\Category;
use Nordkirche\Ndk\Domain\Query\PageQuery;
use Nordkirche\Ndk\Domain\Repository\CategoryRepository;
use Nordkirche\NkcBase\Service\ApiService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class NapiSyncCommandController extends Command
{

    /**
     * @var \TYPO3\CMS\Extbase\Domain\Repository\CategoryRepository
     * @TYPO3\CMS\Extbase\Annotation\Inject
     */
    protected $categoryRepository;

    /**
     * @var \Nordkirche\Ndk\Api
     */
    protected $api;

    /**
     * @var ObjectManager
     */
    protected $objectManager;


    /**
     * Configure the command by defining the name, options and arguments
     */
    protected function configure()
    {
        $this->setName('NAPI Daten Synchronisation (Kategorien)')
            ->setDescription('Synchronisiert die NAPI Kategorien mit der lokalen Datenbank. ACHTUNG: destruktiv!');

        $this->addArgument(
            'pid',
            InputArgument::REQUIRED,
            'Seiten-ID der Kategorien'
        );
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output) {

        $io = new SymfonyStyle($input, $output);
        $io->title('Start map cache warmup');

        $this->objectManager = GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Object\ObjectManager::class);

        $persistenceManager = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);

        $this->categoryRepository = $this->objectManager->get(\TYPO3\CMS\Extbase\Domain\Repository\CategoryRepository::class);

        $pid = $input->getArgument('pid');

        $object = 'category';

        $this->api = ApiService::get();

        $importerName = 'import' . ucfirst($object);

        if (method_exists($this, $importerName)) {
            $this->$importerName($pid);
        }

        $persistenceManager->persistAll();

        $io->success('Data sync completed.');

        return 0;
    }

    /**
     * @param $pid
     */
    private function importCategory($pid)
    {
        $repository = $this->api->factory(CategoryRepository::class);

        $query = $this->api->factory(PageQuery::class);

        $categories = ApiService::getAllItems($repository, $query);

        foreach ($categories as $importCategory) {
            echo $importCategory->getId() . ' - ' . $importCategory->getName() . "\n";

            $category = $this->getCategory($importCategory->getId());

            $update = (bool)count($category);

            $parent = $importCategory->getParent();

            $category['uid'] = $importCategory->getId();
            $category['title'] = $importCategory->getName();
            $category['pid'] = $pid;
            $category['parent'] =  ($parent instanceof Category) ? $parent->getId() : 0;

            if ($update) {
                echo "Updating\n";
                $this->updateCategory($category);
            } else {
                echo "Adding\n";
                $this->createCategory($category);
            }
        }
    }

    /**
     * @param $category
     */
    private function updateCategory($category)
    {
        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_category');
        $connection->update('sys_category', $category, ['uid' => (int)($category['uid'])]);
    }

    /**
     * @param $category
     */
    private function createCategory($category)
    {
        $category['crdate'] = time();
        $category['tstamp'] = time();
        $category['l10n_diffsource'] = '';

        /** @var Connection $connection */
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_category');
        $connection->insert('sys_category', $category);
    }

    /**
     * @param $uid
     * @return array
     */
    private function getCategory($uid)
    {
        $category = $this->categoryRepository->findByUid($uid);
        if ($category instanceof \TYPO3\CMS\Extbase\Domain\Model\Category) {
            return [
                'uid' 			=> $category->getUid(),
                'pid'			=> $category->getPid(),
                'title' 		=> $category->getTitle(),
                'parent' 		=> $category->getParent() ? $category->getParent()->getUid() : 0
            ];
        }
        return [];
    }
}
