<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_4_0($module)
{
    \Packlink\PrestaShop\Classes\Bootstrap::init();
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    $installer = new PacklinkInstaller($module);
    $installer->addController('CashOnDelivery');

    \Shop::setContext($previousShopContext);

    return true;
}