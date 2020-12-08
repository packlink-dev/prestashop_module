<?php

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 3.1.0.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @noinspection PhpUnused
 */
function upgrade_module_3_1_0($module)
{
    $installer = new PacklinkInstaller($module);

    if (!$installer->initializePlugin()) {
        return false;
    }

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.1.0 has started.'), 'Integration');

    return $installer->updateHooks();
}
