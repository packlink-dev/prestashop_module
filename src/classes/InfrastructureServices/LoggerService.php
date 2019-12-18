<?php

namespace Packlink\PrestaShop\Classes\InfrastructureServices;

use Logeecom\Infrastructure\Configuration\Configuration;
use Logeecom\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Logeecom\Infrastructure\Logger\LogData;
use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\Singleton;

/**
 * Class LoggerService.
 *
 * @package Packlink\PrestaShop\Classes\InfrastructureServices
 */
class LoggerService extends Singleton implements ShopLoggerAdapter
{
    /**
     * PrestaShop log severity level codes.
     */
    const PRESTASHOP_INFO = 1;
    const PRESTASHOP_WARNING = 2;
    const PRESTASHOP_ERROR = 3;
    /**
     * Log level names for corresponding log level codes.
     *
     * @var array
     */
    private static $logLevelName = array(
        Logger::ERROR => 'ERROR',
        Logger::WARNING => 'WARNING',
        Logger::INFO => 'INFO',
        Logger::DEBUG => 'DEBUG',
    );
    /**
     * Mappings of Packlink log severity levels to Prestashop log severity levels.
     *
     * @var array
     */
    private static $logMapping = array(
        Logger::ERROR => self::PRESTASHOP_ERROR,
        Logger::WARNING => self::PRESTASHOP_WARNING,
        Logger::INFO => self::PRESTASHOP_INFO,
        Logger::DEBUG => self::PRESTASHOP_INFO,
    );

    /**
     * Log message in system
     *
     * @param LogData $data
     */
    public function logMessage(LogData $data)
    {
        try {
            /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $minLogLevel = $configService->getMinLogLevel();
            $logLevel = $data->getLogLevel();

            if (($logLevel > (int)$minLogLevel) && !$configService->isDebugModeEnabled()) {
                return;
            }
        } catch (\Exception $e) {
            // if we cannot access configuration, log any error directly.
            $logLevel = Logger::ERROR;
        }

        $message = 'PACKLINK LOG:' . ' | '
            . 'Date: ' . date('d/m/Y') . ' | '
            . 'Time: ' . date('H:i:s') . ' | '
            . 'Log level: ' . self::$logLevelName[$logLevel] . ' | '
            . 'Message: ' . $data->getMessage();
        $context = $data->getContext();
        if (!empty($context)) {
            $contextData = array();
            foreach ($context as $item) {
                $contextData[$item->getName()] = print_r($item->getValue(), true);
            }

            $message .= ' | ' . 'Context data: [' . json_encode($contextData) . ']';
        }

        \PrestaShopLogger::addLog($message, self::$logMapping[$logLevel]);
    }
}
