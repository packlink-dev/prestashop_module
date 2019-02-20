<?php
/**
 * 2018 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2018 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

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
