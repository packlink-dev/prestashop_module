<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 3.2.1.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
 * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
 * @throws \PrestaShopDatabaseException
 * @throws \PrestaShop\PrestaShop\Adapter\CoreException
 * @noinspection PhpUnused
 *
 */
function upgrade_module_3_2_0($module)
{
    \Packlink\PrestaShop\Classes\Bootstrap::init();

    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\SystemInfoService $systemInfoService */
    $systemInfoService = \Logeecom\Infrastructure\ServiceRegister::getService(
        \Packlink\BusinessLogic\SystemInformation\SystemInfoService::CLASS_NAME
    );
    $systemDetails = $systemInfoService->getSystemDetails();

    $services = getShippingServices();
    foreach ($services as $index => $service) {
        $services[$index] = updateShippingService($service, $systemDetails);
    }

    foreach ($services as $shippingMethod) {
        if (!empty($shippingMethod['id'])) {
            \Db::getInstance()->update(
                'packlink_entity',
                array(
                    'data' => pSQL(json_encode($shippingMethod), true)
                ),
                '`id` = ' . $shippingMethod['id']
            );
        }
    }

    $installer = new PacklinkInstaller($module);
    $installer->addController('SystemInfo');

    updateServices320();

    return true;
}

/**
 * Returns shipping services.
 *
 * @return array
 *
 * @throws \PrestaShopDatabaseException
 */
function getShippingServices()
{
    $query = new \DbQuery();
    $query->select('id, data')
        ->from(bqSQL('packlink_entity'))
        ->where('type="ShippingService"');

    $records = \Db::getInstance()->executeS($query);

    return array_values(array_map(static function ($item) {
        return json_decode($item['data'], true);
    }, $records));
}

/**
 * Updates shipping service.
 *
 * @param array $service
 * @param \Packlink\BusinessLogic\Http\DTO\SystemInfo[] $systemDetails
 *
 * @return array
 *
 * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
 */
function updateShippingService($service, $systemDetails)
{
    $service['currency'] = 'EUR';
    $service['fixedPrices'] = null;
    $service['systemDefaults'] = null;
    $service['pricingPolicies'] = getSystemSpecificPricingPolicies($service, $systemDetails);

    return $service;
}

/**
 * Returns transformed pricing policies.
 *
 * @param array $service
 * @param \Packlink\BusinessLogic\Http\DTO\SystemInfo[] $systemDetails
 *
 * @return array
 *
 * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
 */
function getSystemSpecificPricingPolicies($service, $systemDetails)
{
    $policies = array();

    if (!empty($service['pricingPolicies'])) {
        foreach ($service['pricingPolicies'] as $policy) {
            foreach ($systemDetails as $systemInfo) {
                $newPolicy = \Packlink\BusinessLogic\ShippingMethod\Models\ShippingPricePolicy::fromArray($policy);
                $newPolicy->systemId = $systemInfo->systemId;

                $policies[] = $newPolicy->toArray();
            }
        }
    }

    return $policies;
}

/**
 * Updates Packlink services.
 *
 * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
 */
function updateServices320()
{
    /** @var \Logeecom\Infrastructure\TaskExecution\QueueService $queueService */
    $queueService = \Logeecom\Infrastructure\ServiceRegister::getService(
        \Logeecom\Infrastructure\TaskExecution\QueueService::CLASS_NAME
    );
    /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
    $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
        \Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME
    );
    if ($queueService->findLatestByType('UpdateShippingServicesTask') !== null) {
        $queueService->enqueue(
            $configService->getDefaultQueueName(),
            new \Packlink\BusinessLogic\Tasks\UpdateShippingServicesTask()
        );
    }
}
