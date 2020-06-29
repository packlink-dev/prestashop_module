<?php

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 2.2.4.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @noinspection PhpUnused
 */
function upgrade_module_2_2_4($module)
{
    $installer = new PacklinkInstaller($module);

    if (!$installer->initializePlugin()) {
        return false;
    }

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v2.2.4 has started.'), 'Integration');

    return $installer->addAdditionalIndex();
}
