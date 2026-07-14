<?php

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
    $previousShopId = Shop::getContextShopID();
    $previousGroupId = Shop::getContextShopGroupID(true);
    Shop::setContext(Shop::CONTEXT_ALL);

    Bootstrap::init();

    packlinkMigrateModelNameChanges();

    $module->uninstallOverrides();

    $module->enable();

    if ($previousShopContext === Shop::CONTEXT_SHOP) {
        Shop::setContext(Shop::CONTEXT_SHOP, $previousShopId);
    } elseif ($previousShopContext === Shop::CONTEXT_GROUP) {
        Shop::setContext(Shop::CONTEXT_GROUP, $previousGroupId);
    } else {
        Shop::setContext(Shop::CONTEXT_ALL);
    }

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

    if (!$data) {
        return;
    }

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
