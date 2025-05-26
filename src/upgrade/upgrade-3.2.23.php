<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_2_23($module)
{
    $installer = new PacklinkInstaller($module);
    $installer->addController('ManualRefreshService');

    return true;
}