<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_4_0($module)
{
    \Packlink\PrestaShop\Classes\Bootstrap::init();
    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    $previousGroupId = \Shop::getContextShopGroupID(true);
    \Shop::setContext(\Shop::CONTEXT_ALL);

    $installer = new PacklinkInstaller($module);
    $installer->addController('CashOnDelivery');

    if ($previousShopContext === \Shop::CONTEXT_SHOP) {
        \Shop::setContext(\Shop::CONTEXT_SHOP, $previousShopId);
    } elseif ($previousShopContext === \Shop::CONTEXT_GROUP) {
        \Shop::setContext(\Shop::CONTEXT_GROUP, $previousGroupId);
    } else {
        \Shop::setContext(\Shop::CONTEXT_ALL);
    }

    return true;
}