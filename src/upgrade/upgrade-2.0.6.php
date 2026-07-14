<?php

use Packlink\PrestaShop\Classes\Bootstrap;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 2.0.6.
 *
 * Historically this migration (re)scheduled the periodic UpdateShipmentDataTask. Core V2 removed
 * that task — shipment tracking data is no longer refreshed through a self-scheduled Core task — so
 * this step is now a no-op kept only to preserve the upgrade chain. Stale schedules persisted for the
 * removed task are purged by the dedicated in-flight migration (see upgrade to the core-V2 version).
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \PrestaShopException
 */
function upgrade_module_2_0_6($module)
{
    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    $previousGroupId = \Shop::getContextShopGroupID(true);
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    $module->enable();

    if ($previousShopContext === \Shop::CONTEXT_SHOP) {
        \Shop::setContext(\Shop::CONTEXT_SHOP, $previousShopId);
    } elseif ($previousShopContext === \Shop::CONTEXT_GROUP) {
        \Shop::setContext(\Shop::CONTEXT_GROUP, $previousGroupId);
    } else {
        \Shop::setContext(\Shop::CONTEXT_ALL);
    }

    return true;
}