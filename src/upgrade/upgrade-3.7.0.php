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
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
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

    Bootstrap::init();

    Logger::logDebug(TranslationUtility::__('Upgrade to plugin v3.7.0 (core V2) has started.'), 'Integration');

    // Heavy, re-runnable read pass first: it only reads and idempotently backfills OrderShipmentDetails,
    // so a memory/time exhaustion here leaves the store untouched and the upgrade safe to re-run.
    $ordersToResync = migrateOrderShipmentDetails();

    // Destructive, irreversible step kept last among the state-mutating steps.
    purgePreV2ExecutionState();

    // A pre-V2 task-runner status survives the purge (it lives in configuration, not the queue). If it
    // reads as "running" or in an incompatible format, the V2 runner never starts, the queue never
    // drains and new drafts hang at "being created". Reset it to idle before re-scheduling.
    resetTaskRunnerStatus();

    // These create rows the purge above removes (Schedule/QueueItem), so they must run after it.
    recreateV2Schedules();
    enqueueOrderDetailsResync($ordersToResync);

    $module->enable();

    registerCustomsPlatform($module);

    restorePreviousShopContext($previousShopContext, $previousShopId, $previousGroupId);

    return true;
}

/**
 * Registers the CR-SET-66 customs platform pieces on an existing store: the Customs admin
 * controller (only if its tab is missing, so no duplicate tab), the product/customer hooks
 * (registerHook is idempotent), and a guarded default customs mapping. Fresh installs get these
 * through PacklinkInstaller::addControllersAndHooks() and the default-configuration step.
 *
 * @param \Packlink $module
 *
 * @return void
 */
function registerCustomsPlatform($module)
{
    try {
        $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($module);

        if (!\Tab::getIdFromClassName('Customs')) {
            $installer->addController('Customs');
        }

        $customsHooks = array(
            'displayAdminProductsExtra',
            'displayAdminProductsShippingStepBottom',
            'actionProductUpdate',
            'actionCustomerFormBuilderModifier',
            'actionAfterCreateCustomerFormHandler',
            'actionAfterUpdateCustomerFormHandler',
        );
        foreach ($customsHooks as $hook) {
            $module->registerHook($hook);
        }

        $installer->addDefaultCustomsMapping();

        // A store configured before the product/country mapping rows existed keeps them empty, which
        // the settings page renders as the blank "not mapped" option. Fill only what is still empty.
        $installer->backfillCustomsMappingSources();
    } catch (\Exception $e) {
        Logger::logWarning(
            TranslationUtility::__('Failed to register customs platform: %s', array($e->getMessage())),
            'Integration'
        );
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
function restorePreviousShopContext($context, $shopId, $groupId = null)
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
 * Resets the task-runner status to idle so the V2 runner can start cleanly after the upgrade,
 * mirroring what PacklinkInstaller::addDefaultPluginConfiguration() does on a fresh install. Without
 * this, a stale pre-V2 runner status can permanently block task execution on the upgraded store.
 */
function resetTaskRunnerStatus()
{
    try {
        /** @var \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerConfigInterface $taskRunnerConfig */
        $taskRunnerConfig = ServiceRegister::getService(
            \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerConfigInterface::CLASS_NAME
        );
        $taskRunnerConfig->setTaskRunnerStatus('', null);
    } catch (\Exception $e) {
        Logger::logWarning(
            TranslationUtility::__('Failed to reset task runner status during upgrade: %s', array($e->getMessage())),
            'Integration'
        );
    }
}

/**
 * Re-creates the recurring schedules using the core V2 scheduler contract.
 */
function recreateV2Schedules()
{
    try {
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

        // A direct multi-version upgrade runs older upgrade steps in the same chain; the purge above
        // removes any service-refresh QueueItem they enqueued. Enqueue one immediate refresh so an
        // authorized store does not wait up to a week for its first service update.
        /** @var TaskExecutorInterface $taskExecutor */
        $taskExecutor = ServiceRegister::getService(TaskExecutorInterface::CLASS_NAME);
        $taskExecutor->enqueue(new UpdateShippingServicesBusinessTask());
    } catch (\Exception $e) {
        Logger::logError(
            TranslationUtility::__('Failed to recreate V2 schedules: %s', array($e->getMessage())),
            'Integration'
        );
    }
}

/**
 * Single paged pass over OrderShipmentDetails that both (1) backfills the new draft status for orders
 * whose draft was already created (they carry a shipment reference), so the order list keeps showing
 * the "View on Packlink" link instead of a create button, and (2) collects referenced orders still
 * missing shipment tracking status so they can be re-synced.
 *
 * Paged with QueryFilter limit/offset (ordered by id for stable paging) so a store with a very large
 * number of rows does not exhaust memory or execution time in the synchronous admin upgrade request.
 *
 * @return array Orders that need a shipment-data re-sync, in the UpgradeShopOrderDetailsTask shape.
 */
function migrateOrderShipmentDetails()
{
    try {
        $repository = RepositoryRegistry::getRepository(OrderShipmentDetails::CLASS_NAME);
    } catch (\Exception $e) {
        return array();
    }

    $batchSize = 1000;
    $offset = 0;
    $referencesByOrderId = array();

    do {
        $filter = new QueryFilter();
        $filter->orderBy('id', QueryFilter::ORDER_ASC);
        $filter->setLimit($batchSize);
        $filter->setOffset($offset);

        try {
            /** @var OrderShipmentDetails[] $batch */
            $batch = $repository->select($filter);
        } catch (\Exception $e) {
            break;
        }

        foreach ($batch as $details) {
            $reference = $details->getReference();
            if (empty($reference)) {
                continue;
            }

            // (1) Backfill draft status for already-created drafts (idempotent, safe to re-run).
            if (!$details->getDraftStatus()) {
                $details->setDraftStatus(DraftStatus::COMPLETED);
                try {
                    $repository->update($details);
                } catch (\Exception $e) {
                    // Non-fatal: a single order failing to backfill must not break the upgrade.
                }
            }

            // (2) Collect orders whose shipment tracking status was never populated.
            if (!$details->getStatus()) {
                $referencesByOrderId[(int) $details->getOrderId()] = $reference;
            }
        }

        $offset += $batchSize;
    } while (count($batch) === $batchSize);

    return buildResyncOrders($referencesByOrderId);
}

/**
 * Builds the UpgradeShopOrderDetailsTask payload for the collected orders, fetching every order's
 * date_add in a single query instead of hydrating one \Order per row.
 *
 * @param array $referencesByOrderId Map of order id => draft reference.
 *
 * @return array
 */
function buildResyncOrders(array $referencesByOrderId)
{
    if (empty($referencesByOrderId)) {
        return array();
    }

    $dateAddById = fetchOrderDateAddMap(array_keys($referencesByOrderId));

    $orders = array();
    foreach ($referencesByOrderId as $orderId => $reference) {
        $orderId = (int) $orderId;
        $orders[] = array(
            'id_order' => $orderId,
            'draft_reference' => $reference,
            'date_add' => isset($dateAddById[$orderId]) ? $dateAddById[$orderId] : date('Y-m-d H:i:s'),
        );
    }

    return $orders;
}

/**
 * Fetches the date_add for the given order ids in a single query.
 *
 * @param int[] $orderIds
 *
 * @return array Map of id_order => date_add.
 */
function fetchOrderDateAddMap(array $orderIds)
{
    $map = array();

    $orderIds = array_map('intval', $orderIds);
    if (empty($orderIds)) {
        return $map;
    }

    try {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_order`, `date_add` FROM `' . _DB_PREFIX_ . 'orders`'
            . ' WHERE `id_order` IN (' . implode(',', $orderIds) . ')'
        );

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row['id_order']] = $row['date_add'];
            }
        }
    } catch (\Exception $e) {
        Logger::logWarning(
            TranslationUtility::__('Failed to fetch order dates during upgrade: %s', array($e->getMessage())),
            'Integration'
        );
    }

    return $map;
}

/**
 * Re-enqueues the refactored UpgradeShopOrderDetailsTask (business task) via the TaskExecutor for
 * orders that have a Packlink reference but never had their shipment tracking data populated.
 *
 * @param array $orders Orders in the UpgradeShopOrderDetailsTask shape.
 */
function enqueueOrderDetailsResync(array $orders)
{
    if (empty($orders)) {
        return;
    }

    /** @var TaskExecutorInterface $taskExecutor */
    $taskExecutor = ServiceRegister::getService(TaskExecutorInterface::CLASS_NAME);

    // Chunk the backlog so each task serialises at most ~1000 orders into its LONGTEXT payload,
    // instead of re-serialising the whole backlog on every progress report.
    foreach (array_chunk($orders, 1000) as $chunk) {
        try {
            $taskExecutor->enqueue(new UpgradeShopOrderDetailsTask($chunk));
        } catch (\Exception $e) {
            Logger::logError(
                TranslationUtility::__(
                    'Cannot enqueue UpgradeShopOrderDetailsTask because: %s',
                    array($e->getMessage())
                ),
                'Integration'
            );
        }
    }
}
