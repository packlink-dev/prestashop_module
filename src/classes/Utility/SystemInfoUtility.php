<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;

class SystemInfoUtility
{
    const PHP_INFO_FILE_NAME = 'phpinfo.html';
    const SYSTEM_INFO_FILE_NAME = 'system-info.txt';
    const LOG_FILE_NAME = 'logs.txt';
    const USER_INFO_FILE_NAME = 'packlink-user-info.txt';
    const QUEUE_INFO_FILE_NAME = 'queue.txt';
    const PARCEL_WAREHOUSE_FILE_NAME = 'parcel-warehouse.txt';
    const SERVICE_INFO_FILE_NAME = 'services.txt';
    const DATABASE = 'MySQL';
    const LOG_NUMBER_DAYS = 7;
    const LIMIT = 10000;

    /**
     * Returns path to zip archive that contains current system information.
     *
     * @return string
     */
    public static function getSystemInfo()
    {
        $file = tempnam(sys_get_temp_dir(), 'packlink_system_info');

        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE);

        $phpInfo = static::getPhpInfo();

        if ($phpInfo !== false) {
            $zip->addFromString(static::PHP_INFO_FILE_NAME, $phpInfo);
        }

        $zip->addFromString(static::SYSTEM_INFO_FILE_NAME, static::getPrestaShopInfo());
        $zip->addFromString(static::LOG_FILE_NAME, static::getLogs());
        $zip->addFromString(static::USER_INFO_FILE_NAME, static::getUserInfo());
        $zip->addFromString(static::QUEUE_INFO_FILE_NAME, static::getQueueStatus());
        $zip->addFromString(static::PARCEL_WAREHOUSE_FILE_NAME, static::getParcelAndWarehouseInfo());
        $zip->addFromString(static::SERVICE_INFO_FILE_NAME, static::getServicesInfo());

        $zip->close();

        return $file;
    }

    /**
     * Retrieves php info.
     *
     * @return false | string
     */
    protected static function getPhpInfo()
    {
        ob_start();
        phpinfo();

        return ob_get_clean();
    }

    /**
     * Returns information about prestashop and plugin.
     *
     * @return string
     */
    protected static function getPrestaShopInfo()
    {
        $result = 'PrestaShop version: ' . _PS_VERSION_;
        $result .= "\ntheme: " . _THEME_NAME_;

        $adminDirectoryPath = explode('/', _PS_ADMIN_DIR_);
        $adminDirectory = $adminDirectoryPath[count($adminDirectoryPath) - 1];

        $result .= "\nbase admin url: " . _PS_BASE_URL_ . '/' . $adminDirectory . '/';
        // PrestaShop only supports MySQL database.
        $result .= "\ndatabase: " . static::DATABASE;
        $result .= "\ndatabase version: " . \Db::getInstance()->getVersion();

        $packlink = new \Packlink();

        $result .= "\nplugin version: " . $packlink->version;

        return $result;
    }

    /**
     * Retrieves logs from PrestaShop.
     *
     * @return string
     */
    protected static function getLogs()
    {
        $result = "\n[";

        try {
            $currentDate = new \DateTime();
            $currentDate->sub(new \DateInterval('P' . static::LOG_NUMBER_DAYS . 'D'));
            $currentOffset = 0;

            $db = \Db::getInstance();

            $query = new \DbQuery();
            $query->select('*')->from('log')->where("date_add>'" . $currentDate->format('Y-m-d H:i:s') . "'");
            $query->limit(static::LIMIT, $currentOffset);

            $logs = $db->executeS($query);

            while (!empty($logs)) {
                $result .= static::formatJsonOutput($logs);

                $currentOffset += static::LIMIT;

                $query = new \DbQuery();
                $query->select('*')->from('log')->where("date_add>'" . $currentDate->format('Y-m-d H:i:s') . "'");
                $query->limit(static::LIMIT, $currentOffset);

                $logs = $db->executeS($query);
            }
        } catch (\Exception $e) {
        }

        return $result . "\n]";
    }

    /**
     * Retrieves user info.
     *
     * @return string
     */
    protected static function getUserInfo()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        $result = 'user info :' . json_encode($configService->getUserInfo());

        $result .= "\n\napi key: " . $configService->getAuthorizationToken();

        return $result;
    }

    /**
     * Retrieves current queue status.
     *
     * @return string
     */
    protected static function getQueueStatus()
    {
        $result = array();

        try {
            $repository = RepositoryRegistry::getRepository(QueueItem::CLASS_NAME);

            $query = new QueryFilter();
            $query->orWhere('status', '=', QueueItem::QUEUED);
            $query->orWhere('status', '=', QueueItem::CREATED);
            $query->orWhere('status', '=', QueueItem::IN_PROGRESS);
            $query->orWhere('status', '=', QueueItem::FAILED);

            $result = $repository->select($query);
        } catch (RepositoryNotRegisteredException $e) {
        } catch (QueryFilterInvalidParamException $e) {
        }

        return static::formatJsonOutput($result);
    }

    /**
     * Retrieves parcel and warehouse information.
     *
     * @return string
     */
    protected static function getParcelAndWarehouseInfo()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

        $result = 'default parcel: ' . json_encode($configService->getDefaultParcel() ?: array());
        $result .= "\n\ndefault warehouse: " . json_encode($configService->getDefaultWarehouse() ?: array());

        return $result;
    }

    /**
     * Retrieves service info.
     *
     * @return string
     */
    protected static function getServicesInfo()
    {
        $result = array();

        try {
            $repository = RepositoryRegistry::getRepository(ShippingMethod::CLASS_NAME);
            $result = $repository->select();
        } catch (RepositoryNotRegisteredException $e) {
        }

        return "[\n" . static::formatJsonOutput($result) . "\n]";
    }

    /**
     * Formats json output.
     *
     * @param array $items
     *
     * @return string
     */
    protected static function formatJsonOutput(array &$items)
    {
        $result = '';

        foreach ($items as $item) {
            if (is_array($item)) {
                $result .= json_encode($item) . ",\n\n";
            } else {
                $result .= json_encode($item->toArray()) . ",\n\n";
            }
        }

        return rtrim($result, ",\n");
    }
}
