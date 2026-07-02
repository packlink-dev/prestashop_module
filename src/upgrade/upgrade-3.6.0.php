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

/**
 * Registers the CR-SET-62 / CR-SET-65 admin controllers (Subscription, ShipmentDocuments)
 * and the storefront displayOrderDetail hook. Idempotent: safe to run even if the tabs/hook
 * already exist (guards against duplicate tabs).
 *
 * @param \Packlink $module
 *
 * @return bool
 */
function upgrade_module_3_6_0($module)
{
    \Packlink\PrestaShop\Classes\Bootstrap::init();

    $installer = new PacklinkInstaller($module);

    // registerHook is idempotent in PrestaShop (no duplicate registration).
    $result = $module->registerHook('displayOrderDetail');

    foreach (array('Subscription', 'ShipmentDocuments') as $controller) {
        if (!(int)\Tab::getIdFromClassName($controller)) {
            $result = $result && $installer->addController($controller);
        }
    }

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.6.0 has started.'), 'Integration');

    return $result;
}
