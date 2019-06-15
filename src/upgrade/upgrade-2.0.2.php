<?php
/**
 * 2019 Packlink
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
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use Packlink\PrestaShop\Classes\Bootstrap;

/**
 * Upgrades module to version 2.0.2.
 *
 * @param \Packlink $module
 *
 * @return bool
 * @throws \PrestaShopException
 */
function upgrade_module_2_0_2($module)
{
    $previousShopContext = Shop::getContext();
    Shop::setContext(Shop::CONTEXT_ALL);

    Bootstrap::init();

    packlinkMigrateModelNameChanges();

    $module->uninstallOverrides();

    if (version_compare(_PS_VERSION_, '1.7', '<')) {
        // v1.7+ installs overrides on enable and 1.6 does not.
        $module->installOverrides();
    }

    $module->enable();

    Shop::setContext($previousShopContext);

    return true;
}

/**
 * Migrates changes in name of the OrderShipmentDetails model from ShopOrderDetails.
 *
 * @throws \PrestaShopDatabaseException
 * @throws \PrestaShopException
 */
function packlinkMigrateModelNameChanges()
{
    $query = new \DbQuery();
    $query->select('*')
        ->from(bqSQL(\Packlink\PrestaShop\Classes\Repositories\BaseRepository::TABLE_NAME))
        ->where('`type`= \'' . pSQL('ShopOrderDetails') . '\'');

    $data = \Db::getInstance()->executeS($query);

    foreach ($data as $record) {
        $id = (int)$record['id'];
        $record['type'] = 'OrderShipmentDetails';
        $jsonEntity = json_decode($record['data'], true);
        $jsonEntity['reference'] = $jsonEntity['shipmentReference'];
        $jsonEntity['shippingCost'] = $jsonEntity['packlinkShippingPrice'];
        unset($jsonEntity['shipmentReference'], $jsonEntity['packlinkShippingPrice']);

        $record['data'] = json_encode($jsonEntity);
        \Db::getInstance()->update(
            \Packlink\PrestaShop\Classes\Repositories\BaseRepository::TABLE_NAME,
            $record,
            "id = $id"
        );
    }
}
