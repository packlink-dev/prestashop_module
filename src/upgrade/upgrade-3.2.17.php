<?php

use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 3.2.17.
 *
 * @param $module
 *
 * @return bool
 */
function upgrade_module_3_2_17($module)
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();
    $installer = new PacklinkInstaller($module);

    if (!$installer->addControllersAndHooks()) {
        return false;
    }

    \Shop::setContext($previousShopContext);

    return true;
}
