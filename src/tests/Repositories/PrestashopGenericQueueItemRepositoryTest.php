<?php

namespace Packlink\PrestaShop\Tests\Repositories;

use Logeecom\Tests\Infrastructure\ORM\AbstractGenericQueueItemRepositoryTest;
use Packlink\PrestaShop\Classes\Bootstrap;

class PrestashopGenericQueueItemRepositoryTest extends AbstractGenericQueueItemRepositoryTest
{
    /**
     * Uses the annotation-based @before hook (not setUp()) so the fixture signature stays compatible
     * across PHPUnit 4/9/11 without a : void return type. Mirrors the core base test's own pattern.
     *
     * @before
     */
    protected function before()
    {
        Bootstrap::init();
        parent::before();
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
     * Cleans up all storage services used by repositories. The DROP lives here (called by the core
     * base test's @after hook) rather than in a static tearDownAfterClass(), which has no
     * cross-PHPUnit-version-safe shim.
     */
    public function cleanUpStorage()
    {
        \Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'packlink_test');
    }

    /**
     * Creates a table for testing purposes. Mirrors the V2 packlink_entity shape (index_1..index_8).
     */
    private function createTestTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'packlink_test
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
             `index_8` VARCHAR(255),
             `data` LONGTEXT NOT NULL,
              PRIMARY KEY(`id`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        \Db::getInstance()->execute($sql);
    }
}
