<?php

use Logeecom\Infrastructure\AutoTest\AutoTestLogger;
use Logeecom\Infrastructure\AutoTest\AutoTestService;
use Logeecom\Infrastructure\Exceptions\StorageNotAccessibleException;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class PacklinkAutoTestController.
 */
class PacklinkAutoConfigureController extends ModuleAdminController
{
    /**
     * PacklinkAutoTestController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Runs the auto-test and returns the queue item ID.
     */
    protected function start()
    {
        $service = new AutoTestService();
        try {
            PacklinkPrestaShopUtility::dieJson(array('success' => true, 'itemId' => $service->startAutoTest()));
        } catch (StorageNotAccessibleException $e) {
            PacklinkPrestaShopUtility::dieJson(
                array(
                    'success' => false,
                    'error' => TranslationUtility::__('Database not accessible.'),
                )
            );
        }
    }

    /**
     * Checks the status of the auto-test task.
     */
    protected function checkStatus()
    {
        $service = new AutoTestService();
        $queueItemId = Tools::getValue('action', 0);

        $status = $service->getAutoTestTaskStatus($queueItemId);

        PacklinkPrestaShopUtility::dieJson(
            array(
                'finished' => in_array($status, array('timeout', QueueItem::COMPLETED, QueueItem::FAILED), true),
                'error' => $status === 'timeout' ? TranslationUtility::__('Task could not be started.') : '',
                'logs' => $this->getLogsArray(),
            )
        );
    }

    /**
     * Exports all logs as a JSON file.
     */
    protected function exportLogs()
    {
        PacklinkPrestaShopUtility::dieFileFromString(json_encode($this->getLogsArray()), 'auto-test-logs.json');
    }

    /**
     * Transforms logs to the plain array.
     *
     * @return array An array of logs.
     */
    private function getLogsArray()
    {
        $logs = AutoTestLogger::getInstance()->getLogs();
        $result = array();
        foreach ($logs as $log) {
            $result[] = $log->toArray();
        }

        return $result;
    }
}
