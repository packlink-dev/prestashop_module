<?php

namespace Packlink\PrestaShop\Tests\Repositories;

use Logeecom\Tests\Infrastructure\ORM\AbstractGenericStudentRepositoryTest;

class PrestashopGenericBaseRepositoryTest extends AbstractGenericStudentRepositoryTest
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->createTestTable();
    }

    /**
     * @return string
     */
    public function getStudentEntityRepositoryClass()
    {
        return TestRepository::getClassName();
    }

    /**
     * Cleans up all storage services used by repositories
     */
    public function cleanUpStorage()
    {
        \Db::getInstance()->execute('DROP TABLE IF EXISTS ' . _DB_PREFIX_ . 'packlink_test');
    }

    /**
     * Creates a table for testing purposes.
     */
    private function createTestTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'packlink_test
            (
             `id` INT NOT NULL AUTO_INCREMENT,
             `type` VARCHAR(128) NOT NULL,
             `index_1` VARCHAR(128),
             `index_2` VARCHAR(128),
             `index_3` VARCHAR(128),
             `index_4` VARCHAR(128),
             `index_5` VARCHAR(128),
             `index_6` VARCHAR(128),
             `index_7` VARCHAR(128),
             `data` LONGTEXT NOT NULL,
              PRIMARY KEY(`id`)
            )
            ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';

        \Db::getInstance()->execute($sql);
    }
}
