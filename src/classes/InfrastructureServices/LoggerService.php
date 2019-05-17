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
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $minLogLevel = $configService->getMinLogLevel();
        $logLevel = $data->getLogLevel();

        if (($logLevel > (int)$minLogLevel) && !$configService->isDebugModeEnabled()) {
            return;
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
