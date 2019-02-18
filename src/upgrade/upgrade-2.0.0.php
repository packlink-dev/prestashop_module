<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueService;
use Packlink\BusinessLogic\User\UserAccountService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Tasks\UpgradeShopOrderDetailsTask;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Upgrades module to version 2.0.0.
 *
 * @param \Packlink $module
 *
 * @return bool
 * @throws \PrestaShopException
 */
function upgrade_module_2_0_0($module)
{
    $previousShopContext = Shop::getContext();
    Shop::setContext(Shop::CONTEXT_ALL);

    Bootstrap::init();
    $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($module);

    if (!$installer->initializePlugin()) {
        return false;
    }

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v2.0.0 has started.'), 'Integration');

    try {
        removeControllersAndHooks($module);
    } catch (PrestaShopException $e) {
        Logger::logWarning(
            TranslationUtility::__(
                'Failed to remove old controllers and hooks because: %s',
                array($e->getMessage())
            ),
            'Integration'
        );
    }

    try {
        removeObsoleteFiles($module);
    } catch (\Exception $e) {
        Logger::logDebug(
            TranslationUtility::__('Could not delete obsolete files because: %s', array($e->getMessage())),
            'Integration'
        );
    }

    if (!$installer->addControllersAndHooks()) {
        return false;
    }

    $apiKey = Configuration::get('PL_API_KEY');

    if (!empty($apiKey)) {
        Logger::logDebug(TranslationUtility::__('Old api key detected.'), 'Integration');

        /** @var UserAccountService $userAccountService */
        $userAccountService = ServiceRegister::getService(UserAccountService::CLASS_NAME);
        if ($userAccountService->login($apiKey)) {
            Logger::logDebug(
                TranslationUtility::__('Successfully logged in with existing api key.'),
                'Integration'
            );

            transferOrderStatusMappings();
            transferOrderReferences();
        }
    }

    removePreviousData();

    $module->enable();
    Shop::setContext($previousShopContext);

    return true;
}

/**
 * Removes old controllers and hooks.
 *
 * @param \Module $module
 *
 * @throws \PrestaShopException
 */
function removeControllersAndHooks($module)
{
    Logger::logDebug(TranslationUtility::__('Removing old controllers and hooks.'), 'Integration');

    removeController('packlink');
    removeController('AdminTabPacklink');
    removeController('AdminGeneratePdfPl');

    removeHooks($module);
}

/**
 * Deletes old controllers.
 *
 * @param string $name
 *
 * @throws \PrestaShopException
 */
function removeController($name)
{
    $tabs = Tab::getCollectionFromModule($name);
    if (!empty($tabs)) {
        /** @var Tab $tab */
        foreach ($tabs as $tab) {
            $tab->delete();
        }
    }
}

/**
 * Removes old hooks.
 *
 * @param \Module $module
 */
function removeHooks($module)
{
    $registeredHooks = getRegisteredHooks();

    foreach ($registeredHooks as $hook) {
        Hook::unregisterHook($module, $hook);
    }
}

/**
 * Removes obsolete files.
 *
 * @param Module $module
 */
function removeObsoleteFiles($module)
{
    Logger::logDebug(TranslationUtility::__('Removing obsolete files.'), 'Integration');

    $installPath = $module->getLocalPath();
    removeDirectory($installPath . 'ajax');
    removeDirectory($installPath . 'api');
    removeDirectory($installPath . 'pdf');
    removeDirectory($installPath . 'libraries');
    removeDirectory($installPath . 'classes/helper');
    removeDirectory($installPath . 'views/templates/front');

    unlink($installPath . 'classes/PLOrder.php');

    unlink($installPath . 'controllers/admin/AdminGeneratePdfPlController.php');
    unlink($installPath . 'controllers/admin/AdminTabPacklinkController.php');

    unlink($installPath . 'upgrade/Upgrade-1.3.0.php');
    unlink($installPath . 'upgrade/Upgrade-1.4.0.php');
    unlink($installPath . 'upgrade/Upgrade-1.5.0.php');
    unlink($installPath . 'upgrade/Upgrade-1.6.0.php');
    unlink($installPath . 'upgrade/Upgrade-1.6.3.php');

    unlink($installPath . 'views/css/style16.css');
    unlink($installPath . 'views/img/add.gif');
    unlink($installPath . 'views/img/delivery.gif');
    unlink($installPath . 'views/img/down.png');
    unlink($installPath . 'views/img/printer.gif');
    unlink($installPath . 'views/img/search.gif');
    unlink($installPath . 'views/img/up.png');
    unlink($installPath . 'views/js/order_detail.js');
    unlink($installPath . 'views/templates/admin/_pl_action.tpl');
    unlink($installPath . 'views/templates/hook/back.tpl');
    unlink($installPath . 'views/templates/hook/expedition.tpl');
    unlink($installPath . 'views/templates/hook/order_details.tpl');

    unlink($installPath . 'status.php');
}

/**
 * Removes directory.
 *
 * @param string $name
 */
function removeDirectory($name)
{
    $iterator = new RecursiveDirectoryIterator($name, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }

    rmdir($name);
}

/**
 * Transfers existing order mappings to new module.
 */
function transferOrderStatusMappings()
{
    $mappings = getOrderStatusMappingsMap();

    $orderStatusMappings = array();

    foreach ($mappings as $oldMapping => $newMapping) {
        $value = Configuration::get($oldMapping);
        if (!empty($value)) {
            $orderStatusMappings[$newMapping] = $value;
        }
    }

    getConfigService()->setOrderStatusMappings($orderStatusMappings);
}

/**
 * Transfers old order references to new plugin.
 */
function transferOrderReferences()
{
    $references = getReferenceIds();
    if (!empty($references)) {
        $config = getConfigService();

        /** @var QueueService $queue */
        $queue = ServiceRegister::getService(QueueService::CLASS_NAME);
        try {
            $queue->enqueue($config->getDefaultQueueName(), new UpgradeShopOrderDetailsTask($references));
        } catch (\Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException $e) {
            Logger::logError(
                TranslationUtility::__(
                    'Cannot enqueue UpgradeShopDetailsTask because: %s',
                    array($e->getMessage())
                ),
                'Integration'
            );
        }
    }
}

/**
 * Removes previously used databases.
 */
function removePreviousData()
{
    Logger::logDebug(TranslationUtility::__('Deleting old plugin data.'));

    $db = Db::getInstance();

    $sql = 'DROP TABLE IF EXISTS `' . $db->getPrefix() . 'packlink_orders`';
    $db->execute($sql);

    $sql_wait = 'DROP TABLE IF EXISTS `' . $db->getPrefix() . 'packlink_wait_draft`';
    $db->execute($sql_wait);

    $configs = getConfigurationKeys();
    foreach ($configs as $config) {
        Configuration::deleteByName($config);
    }
}

/**
 * Retrieves registered hooks in old plugin.
 *
 * @return array
 */
function getRegisteredHooks()
{
    return array(
        'actionObjectOrderHistoryAddAfter',
        'actionObjectOrderUpdateAfter',
        'actionOrderStatusPostUpdate',
        'displayOrderDetail',
        'displayBackOfficeHeader',
        'displayAdminOrderContentShip',
        'displayAdminOrderTabShip',
        'displayAdminOrder',
        'displayHeader',
    );
}

/**
 * Returns map of old statuses mapped to new statuses.
 *
 * @return array
 */
function getOrderStatusMappingsMap()
{
    return array(
        'PL_ST_AWAITING' => \Packlink\BusinessLogic\WebHook\Events\ShipmentStatusChangedEvent::STATUS_PENDING,
        'PL_ST_PENDING' => \Packlink\BusinessLogic\WebHook\Events\ShipmentStatusChangedEvent::STATUS_ACCEPTED,
        'PL_ST_READY' => \Packlink\BusinessLogic\WebHook\Events\ShipmentStatusChangedEvent::STATUS_READY,
        'PL_ST_TRANSIT' => \Packlink\BusinessLogic\WebHook\Events\ShipmentStatusChangedEvent::STATUS_IN_TRANSIT,
        'PL_ST_DELIVERED' => \Packlink\BusinessLogic\WebHook\Events\ShipmentStatusChangedEvent::STATUS_DELIVERED,
    );
}

/**
 * Returns list of old configuration keys used by Packlink module before v2.0.0
 *
 * @return array
 */
function getConfigurationKeys()
{
    return array(
        'PL_IMPORT',
        'PL_CREATE_DRAFT_AUTO',
        'PL_API_KEY',
        'PL_API_KG',
        'PL_API_CM',
        'PL_API_VERSION',
        'PL_ST_AWAITING',
        'PL_ST_PENDING',
        'PL_ST_READY',
        'PL_ST_TRANSIT',
        'PL_ST_DELIVERED',
    );
}

/**
 * Returns array of reference ids.
 *
 * @return array
 */
function getReferenceIds()
{
    $db = Db::getInstance();
    $query = new DbQuery();
    $query->select('id_order')
        ->select('draft_reference')
        ->from('packlink_orders');

    try {
        $result = $db->executeS($query);
    } catch (PrestaShopDatabaseException $e) {
        $result = array();
        Logger::logError(
            TranslationUtility::__('Cannot retrieve order references because: %s', array($e->getMessage())),
            'Integration'
        );
    }

    return empty($result) ? array() : $result;
}

/**
 * Retrieves configuration from service register.
 *
 * @return \Packlink\BusinessLogic\Configuration
 */
function getConfigService()
{
    /** @noinspection PhpIncompatibleReturnTypeInspection */
    return ServiceRegister::getService(\Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME);
}
