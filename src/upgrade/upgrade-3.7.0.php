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
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecutor\Interfaces\TaskExecutorInterface;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\OrderShipmentDetails\Models\OrderShipmentDetails;
use Packlink\BusinessLogic\Scheduler\DTO\ScheduleConfig;
use Packlink\BusinessLogic\Scheduler\Interfaces\SchedulerInterface;
use Packlink\BusinessLogic\ShipmentDraft\Utility\DraftStatus;
use Packlink\BusinessLogic\Tasks\BusinessTasks\UpdateShippingServicesBusinessTask;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CleanupTaskSchedulerService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Tasks\UpgradeShopOrderDetailsTask;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Migrates the module onto core V2 (CR-SET-59).
 *
 * The pre-V2 queue items and schedules serialise task classes that were moved or removed in core V2
 * (e.g. UpdateShipmentDataTask, the old UpdateShippingServicesTask, the relocated ScheduleCheckTask /
 * TaskCleanupTask). Reading them back under V2 would fail to deserialise, so this migration:
 *   1. discards the pre-V2 queue/schedule/process rows (historical execution state);
 *   2. re-establishes the V2 recurring schedules (cleanup + weekly service refresh);
 *   3. backfills OrderShipmentDetails.draftStatus so already-created drafts keep showing as created;
 *   4. re-enqueues the refactored UpgradeShopOrderDetailsTask (business task) via the TaskExecutor
 *      for orders whose shipment tracking data was never populated.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \PrestaShopException
 */
function upgrade_module_3_7_0($module)
{
    $previousShopContext = \Shop::getContext();
    $previousShopId = \Shop::getContextShopID();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    Bootstrap::init();

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.7.0 (core V2) has started.'), 'Integration');

    purgePreV2ExecutionState();
    recreateV2Schedules();
    backfillDraftStatuses();
    enqueueOrderDetailsResync();

    $module->enable();

    restorePreviousShopContext($previousShopContext, $previousShopId);

    return true;
}

/**
 * Restores the shop context captured before the migration.
 *
 * Guards the CONTEXT_SHOP case: in a CLI/console upgrade there is no active shop, so the shop id is
 * null and \Shop::setContext(\Shop::CONTEXT_SHOP) fatals on \Shop::getGroupIdFromShopId()'s strict
 * int type hint. Falls back to CONTEXT_ALL whenever a concrete shop id is unavailable.
 *
 * @param int $context One of the \Shop::CONTEXT_* constants.
 * @param int|null $shopId
 */
function restorePreviousShopContext($context, $shopId)
{
    try {
        if ((int) $context === \Shop::CONTEXT_SHOP && !empty($shopId)) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $shopId);
        } elseif ((int) $context === \Shop::CONTEXT_SHOP) {
            \Shop::setContext(\Shop::CONTEXT_ALL);
        } else {
            \Shop::setContext((int) $context);
        }
    } catch (\Throwable $e) {
        \Shop::setContext(\Shop::CONTEXT_ALL);
    }
}

/**
 * Discards pre-V2 queue items, schedules and async-process rows that serialise moved/removed task
 * classes. Done with raw SQL on the entity type column so no removed class is ever deserialised.
 */
function purgePreV2ExecutionState()
{
    $table = _DB_PREFIX_ . BaseRepository::TABLE_NAME;

    try {
        \Db::getInstance()->execute(
            "DELETE FROM `" . bqSQL($table) . "` WHERE `type` IN ('QueueItem', 'Schedule', 'Process')"
        );
    } catch (\Exception $e) {
        Logger::logWarning(
            TranslationUtility::__('Failed to purge pre-V2 execution state: %s', array($e->getMessage())),
            'Integration'
        );
    }
}

/**
 * Re-creates the recurring schedules using the core V2 scheduler contract.
 */
function recreateV2Schedules()
{
    CleanupTaskSchedulerService::scheduleTaskCleanupTask();

    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $config */
    $config = ServiceRegister::getService(Configuration::CLASS_NAME);

    // Only stores that completed authentication need the recurring service refresh.
    if (!$config->getAuthorizationToken()) {
        return;
    }

    /** @var SchedulerInterface $scheduler */
    $scheduler = ServiceRegister::getService(SchedulerInterface::CLASS_NAME);
    $scheduler->scheduleWeekly(
        new UpdateShippingServicesBusinessTask(),
        new ScheduleConfig(rand(1, 7), rand(0, 5), rand(0, 59))
    );
}

/**
 * Backfills the new draft status for orders whose draft was already created (they carry a shipment
 * reference), so the order list keeps showing the "View on Packlink" link instead of a create button.
 */
function backfillDraftStatuses()
{
    try {
        $repository = RepositoryRegistry::getRepository(OrderShipmentDetails::CLASS_NAME);
    } catch (\Exception $e) {
        return;
    }

    /** @var OrderShipmentDetails[] $allDetails */
    $allDetails = $repository->select();
    foreach ($allDetails as $details) {
        if ($details->getReference() && !$details->getDraftStatus()) {
            $details->setDraftStatus(DraftStatus::COMPLETED);
            try {
                $repository->update($details);
            } catch (\Exception $e) {
                // Non-fatal: a single order failing to backfill must not break the upgrade.
            }
        }
    }
}

/**
 * Re-enqueues the refactored UpgradeShopOrderDetailsTask (business task) via the TaskExecutor for
 * orders that have a Packlink reference but never had their shipment tracking data populated.
 */
function enqueueOrderDetailsResync()
{
    $orders = collectOrdersMissingShipmentData();
    if (empty($orders)) {
        return;
    }

    /** @var TaskExecutorInterface $taskExecutor */
    $taskExecutor = ServiceRegister::getService(TaskExecutorInterface::CLASS_NAME);

    try {
        $taskExecutor->enqueue(new UpgradeShopOrderDetailsTask($orders));
    } catch (\Exception $e) {
        Logger::logError(
            TranslationUtility::__('Cannot enqueue UpgradeShopOrderDetailsTask because: %s', array($e->getMessage())),
            'Integration'
        );
    }
}

/**
 * Collects referenced orders that are missing shipment tracking status, in the shape the
 * UpgradeShopOrderDetailsTask expects.
 *
 * @return array
 */
function collectOrdersMissingShipmentData()
{
    try {
        $repository = RepositoryRegistry::getRepository(OrderShipmentDetails::CLASS_NAME);
    } catch (\Exception $e) {
        return array();
    }

    $orders = array();
    /** @var OrderShipmentDetails[] $allDetails */
    $allDetails = $repository->select();
    foreach ($allDetails as $details) {
        $reference = $details->getReference();
        if (empty($reference) || $details->getStatus()) {
            continue;
        }

        $order = new \Order((int)$details->getOrderId());
        $dateAdd = \Validate::isLoadedObject($order) ? $order->date_add : date('Y-m-d H:i:s');

        $orders[] = array(
            'id_order' => $details->getOrderId(),
            'draft_reference' => $reference,
            'date_add' => $dateAdd,
        );
    }

    return $orders;
}
