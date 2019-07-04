<?php

namespace Packlink\PrestaShop\Tests\Repositories;

use Logeecom\Tests\Infrastructure\ORM\AbstractGenericQueueItemRepositoryTest;
use Packlink\PrestaShop\Classes\Bootstrap;

class PrestashopGenericQueueItemRepositoryTest extends AbstractGenericQueueItemRepositoryTest
{
    /**
     * @inheritdoc
     */
    public static function tearDownAfterClass()
    {
        $packlinkTestTableUninstallScript = 'DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'packlink_test';
        \Db::getInstance()->execute($packlinkTestTableUninstallScript);
    }

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        Bootstrap::init();
        parent::setUp();
        $this->createTestTable();
    }

    /**
     * @return string
     */
    public function getQueueItemEntityRepositoryClass()
    {
        return TestQueueItemRepository::getClassName();
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        return null;
    }

    /**
     * Creates a table for testing purposes.
     */
    private function createTestTable()
    {
        $packlinkTestTableInstallScript =
            'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'packlink_test
            (
             `id` INT NOT NULL AUTO_INCREMENT,
             `type` VARCHAR(128) NOT NULL,
             `index_1` VARCHAR(255),
             `index_2` VARCHAR(255),
             `index_3` VARCHAR(255),
             `index_4` VARCHAR(255),
             `index_5` VARCHAR(255),
             `index_6` VARCHAR(255),
             `index_7` VARCHAR(255),
             `data` LONGTEXT NOT NULL,
              PRIMARY KEY(`id`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        \Db::getInstance()->execute($packlinkTestTableInstallScript);
    }
}
