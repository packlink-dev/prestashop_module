<?php

use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrades module to version 2.0.5.
 *
 * @param \Packlink $module
 *
 * @return bool
 *
 * @throws \PrestaShopException
 * @throws \PrestaShop\PrestaShop\Adapter\CoreException
 */
function upgrade_module_2_0_5($module)
{
    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    $previousGroupId = \Shop::getContextShopGroupID(true);
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    $installer = new PacklinkInstaller($module);
    $installer->addController('PacklinkAutoTest');
    $installer->addController('PacklinkAutoConfigure');

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
