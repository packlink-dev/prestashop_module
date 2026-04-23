<?php
/**
 * 2026 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Apache License 2.0
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://www.apache.org/licenses/LICENSE-2.0.txt
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2026 Packlink Shipping S.L
 * @license   http://www.apache.org/licenses/LICENSE-2.0.txt  Apache License 2.0
 */
use Logeecom\Infrastructure\Logger\Logger;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_3_5_0($module)
{
    \Packlink\PrestaShop\Classes\Bootstrap::init();

    $installer = new PacklinkInstaller($module);

    $result = $installer->updateHooks();

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.5.0 has started.'), 'Integration');

    return $result;
}