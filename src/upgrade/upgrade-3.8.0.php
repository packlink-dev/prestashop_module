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
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\DDP\DdpBehavior;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

/*
 * Helper names carry a "380" suffix: PrestaShop includes every intermediate upgrade script in a
 * single chained request, and upgrade-3.7.0.php already defines the unsuffixed helper names
 * (e.g. restorePreviousShopContext()) in the global scope — a redeclaration would fatal.
 */

/**
 * Adds DDP / duties-paid support (CR-SET-68).
 *
 * Duties-paid twin carriers are normally created by CarrierService when a shipping method is saved.
 * Without this migration, an upgraded store whose country/service configuration already charges
 * duties (effective DDP behavior other than NONE) would only get the twin carrier the next time
 * each shipping method happens to be saved, leaving the checkout without the duties-paid option
 * until then (UC-P8). Nothing is deactivated; no acknowledgement flag exists in core.
 *
 * No hooks are re-registered: DDP renders through hooks the module already registers, and no new
 * hook was added since 3.7.0.
 *
 * @param \Packlink $module
 *
 * @return boolean
 */
function upgrade_module_3_8_0($module)
{
    // Core V2 classes use PHP 7.0+ syntax and fatal at parse time on older PHP. Guard before
    // Bootstrap::init() autoloads any of them, so a PHP 5.x store gets a clean warning, not a
    // white screen mid-upgrade. PrestaShop does not enforce the composer php constraint at runtime.
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        $module->warning = 'Packlink PRO Shipping requires PHP 7.0 or newer.';

        return false;
    }

    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    $previousGroupId = \Shop::getContextShopGroupID(true);
    \Shop::setContext(\Shop::CONTEXT_ALL);

    try {
        Bootstrap::init();

        Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.8.0 (DDP support) has started.'), 'Integration');

        createDdpCarriersForDutyChargingMethods380();

        $module->enable();
    } catch (\Exception $e) {
        return failUpgrade380($e, $previousShopContext, $previousShopId, $previousGroupId);
    } catch (\Throwable $e) {
        return failUpgrade380($e, $previousShopContext, $previousShopId, $previousGroupId);
    }

    restorePreviousShopContext380($previousShopContext, $previousShopId, $previousGroupId);

    return true;
}

/**
 * Logs the failure and restores the shop context so an unexpected error surfaces as a failed module
 * upgrade instead of a white screen mid-upgrade.
 *
 * @param \Exception|\Throwable $e
 * @param int $context One of the \Shop::CONTEXT_* constants.
 * @param int|null $shopId
 * @param int|null $groupId
 *
 * @return boolean Always FALSE, so the caller can return the result directly.
 */
function failUpgrade380($e, $context, $shopId, $groupId)
{
    Logger::logError(
        TranslationUtility::__('Upgrade to plugin v3.8.0 failed: %s', array($e->getMessage())),
        'Integration'
    );

    restorePreviousShopContext380($context, $shopId, $groupId);

    return false;
}

/**
 * Creates the duties-paid twin carrier for every activated shipping method whose effective DDP
 * behavior already charges duties.
 *
 * Idempotent: CarrierService::update() syncs the twin via syncDdpCarrier(), which creates a carrier
 * only when no DDP mapping exists yet and otherwise just refreshes it, so re-running the upgrade is
 * safe. A failure on one method must not starve the remaining methods of their twin carrier, so
 * each is handled and logged individually.
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 */
function createDdpCarriersForDutyChargingMethods380()
{
    $repository = RepositoryRegistry::getRepository(ShippingMethod::CLASS_NAME);

    $filter = new QueryFilter();
    $filter->where('activated', Operators::EQUALS, true);

    /** @var ShippingMethod[] $methods */
    $methods = $repository->select($filter);

    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
    $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

    foreach ($methods as $method) {
        if ($method->getEffectiveDdpBehavior() === DdpBehavior::NONE) {
            continue;
        }

        try {
            $carrierService->update($method);

            Logger::logInfo(
                TranslationUtility::__(
                    'Synced duties-paid carrier for shipping method %s (%s).',
                    array($method->getId(), $method->getTitle())
                ),
                'Integration'
            );
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__(
                    'Failed to sync duties-paid carrier for shipping method %s (%s): %s',
                    array($method->getId(), $method->getTitle(), $e->getMessage())
                ),
                'Integration'
            );
        }
    }
}

/**
 * Restores the shop context captured before the migration.
 *
 * Guards both scoped contexts: in a CLI/console upgrade there is no active shop or group, so the id
 * is null and \Shop::setContext(\Shop::CONTEXT_SHOP|CONTEXT_GROUP) fatals on the strict int type hint
 * of \Shop::getGroupIdFromShopId(). Restoring CONTEXT_GROUP without an id would also silently select
 * group 0. Falls back to CONTEXT_ALL whenever a concrete id is unavailable.
 *
 * @param int $context One of the \Shop::CONTEXT_* constants.
 * @param int|null $shopId
 * @param int|null $groupId
 */
function restorePreviousShopContext380($context, $shopId, $groupId = null)
{
    try {
        if ((int) $context === \Shop::CONTEXT_SHOP && !empty($shopId)) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $shopId);
        } elseif ((int) $context === \Shop::CONTEXT_GROUP && !empty($groupId)) {
            \Shop::setContext(\Shop::CONTEXT_GROUP, (int) $groupId);
        } elseif ((int) $context === \Shop::CONTEXT_SHOP || (int) $context === \Shop::CONTEXT_GROUP) {
            \Shop::setContext(\Shop::CONTEXT_ALL);
        } else {
            \Shop::setContext((int) $context);
        }
    } catch (\Throwable $e) {
        \Shop::setContext(\Shop::CONTEXT_ALL);
    }
}
