<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Exceptions\BaseException;
use Logeecom\Infrastructure\ORM\Entity;
use Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;

class SystemInfoUtility
{
    const PHP_INFO_FILE_NAME = 'phpinfo.html';
    const SYSTEM_INFO_FILE_NAME = 'system-info.json';
    const LOG_FILE_NAME = 'logs.json';
    const USER_INFO_FILE_NAME = 'packlink-user-info.json';
    const QUEUE_INFO_FILE_NAME = 'queue.json';
    const PARCEL_WAREHOUSE_FILE_NAME = 'parcel-warehouse.json';
    const SERVICE_INFO_FILE_NAME = 'services.json';
    const DATABASE = 'MySQL';
    const LOG_NUMBER_DAYS = 7;
    const LIMIT = 10000;

    /**
     * Returns path to zip archive that contains current system information.
     *
     * @return string
     * @throws \PrestaShopException
     */
    public static function getSystemInfo()
    {
        if (!defined('JSON_PRETTY_PRINT')) {
            define('JSON_PRETTY_PRINT', 128);
        }

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
     *
     * @throws \PrestaShopException
     */
    protected static function getPrestaShopInfo()
    {
        /** @var Configuration $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $result = array();
        $result['PrestaShop version'] = _PS_VERSION_;
        $result['Theme'] = _THEME_NAME_;

        $adminDirectoryPath = explode('/', _PS_ADMIN_DIR_);
        $adminDirectory = $adminDirectoryPath[count($adminDirectoryPath) - 1];

        $result['Base admin URL'] = _PS_BASE_URL_ . '/' . $adminDirectory . '/';
        // PrestaShop only supports MySQL database.
        $result['Database'] = \Db::getInstance()->getBestEngine();
        $result['Database version'] = \Db::getInstance()->getVersion();

        $packlink = \Module::getInstanceByName('packlink');

        $result['Plugin version'] = $packlink->version;
        $result['Async process URL'] = $configService->getAsyncProcessUrl('test');
        $result['Auto-test URL'] = \Context::getContext()->link->getAdminLink('PacklinkAutoTest');
        $result['Test cURL URL'] = \Context::getContext()->link->getAdminLink('Debug') . '&' .
            http_build_query(
                array(
                    'ajax' => true,
                    'action' => 'testCurl',
                )
            );

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Retrieves logs from PrestaShop.
     *
     * @return string
     */
    protected static function getLogs()
    {
        $result = array(array());

        try {
            $currentDate = new \DateTime();
            $currentDate->sub(new \DateInterval('P' . static::LOG_NUMBER_DAYS . 'D'));
            $currentOffset = 0;

            $logs = self::getDatabaseLogs($currentDate, $currentOffset);
            while (!empty($logs)) {
                $result[] = $logs;
                $currentOffset += static::LIMIT;
                $logs = self::getDatabaseLogs($currentDate, $currentOffset);
            }

            $result = call_user_func_array('array_merge', $result);
        } catch (\Exception $e) {
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

        /** @noinspection NullPointerExceptionInspection */
        $result = $configService->getUserInfo()->toArray();
        $result['API key'] = $configService->getAuthorizationToken();

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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
            $repository = RepositoryRegistry::getQueueItemRepository();

            $query = new QueryFilter();
            $query->where('status', Operators::NOT_EQUALS, QueueItem::COMPLETED);

            $result = $repository->select($query);
        } catch (BaseException $e) {
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

        $result = array();
        $result['Default parcel'] = $configService->getDefaultParcel() ?: array();
        $result['Default warehouse'] = $configService->getDefaultWarehouse() ?: array();

        return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
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

        return static::formatJsonOutput($result);
    }

    /**
     * Formats json output.
     *
     * @param Entity[] $items
     *
     * @return string
     */
    protected static function formatJsonOutput(array &$items)
    {
        $response = array();
        foreach ($items as $item) {
            $response[] = $item->toArray();
        }

        return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param \DateTime $currentDate
     * @param $currentOffset
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    protected static function getDatabaseLogs(\DateTime $currentDate, $currentOffset)
    {
        $query = new \DbQuery();
        $query->select('*')->from('log')->where("date_add > '" . $currentDate->format('Y-m-d H:i:s') . "'");
        $query->limit(static::LIMIT, $currentOffset);

        return \Db::getInstance()->executeS($query);
    }
}
