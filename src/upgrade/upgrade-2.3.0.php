<?php

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingPricePolicy;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\Repositories\BaseRepository;
use Packlink\PrestaShop\Classes\Utility\PacklinkInstaller;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 2.3.0.
 *
 * @param \Packlink $module
 *
 * @return boolean
 *
 * @throws \PrestaShopException
 *
 * @noinspection PhpUnused
 */
function upgrade_module_2_3_0($module)
{
    $previousShopContext = \Shop::getContext();
    \Shop::setContext(\Shop::CONTEXT_ALL);

    try {
        $installer = new PacklinkInstaller($module);
        foreach (getNewControllers() as $controller) {
            $installer->addController($controller);
        }

        Bootstrap::init();

        transformShippingMethods();
    } catch (\Exception $e) {
        Logger::logError('Error updating to version 2.3.0. Error: ' . $e->getMessage(), 'Integration');

        return false;
    }

    $module->enable();
    \Shop::setContext($previousShopContext);

    return true;
}

/**
 * Returns controllers that were added in the new update.
 *
 * @return array
 */
function getNewControllers()
{
    return array(
        'Configuration',
        'Login',
        'ModuleState',
        'Onboarding',
        'Registration',
        'RegistrationRegions',
        'ShippingZones',
    );
}

/**
 * Transforms shipping methods to the new format.
 *
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
 * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
 * @throws \PrestaShopDatabaseException
 * @throws \PrestaShopException
 */
function transformShippingMethods()
{
    $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
    /** @var CarrierService $carrierService */
    $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

    $sql = 'SELECT * FROM '
        . bqSQL(_DB_PREFIX_ . BaseRepository::TABLE_NAME)
        . ' WHERE type = "ShippingService" ';

    $statement = \Db::getInstance()->query($sql);

    while ($row = \Db::getInstance()->nextRow($statement)) {
        $data = json_decode($row['data'], true);
        $data['pricingPolicies'] = getTransformedPricingPolicies($data);
        $data['logoUrl'] = getLogoUrl($data);

        $shippingMethod = ShippingMethod::fromArray($data);
        $repository->update($shippingMethod);

        if ($shippingMethod->isActivated()) {
            $carrierService->update($shippingMethod);
        }
    }
}

/**
 * Returns transformed pricing policies for a given shipping method.
 *
 * @param array $method
 *
 * @return array
 */
function getTransformedPricingPolicies(array $method)
{
    $result = array();

    switch ($method['pricingPolicy']) {
        case 1:
            // Packlink prices.
            break;
        case 2:
            // Percent prices.
            $pricingPolicy = new ShippingPricePolicy();
            $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE_AND_WEIGHT;
            $pricingPolicy->fromPrice = 0;
            $pricingPolicy->fromWeight = 0;
            $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_PACKLINK_ADJUST;
            $pricingPolicy->increase = $method['percentPricePolicy']['increase'];
            $pricingPolicy->changePercent = $method['percentPricePolicy']['amount'];
            $result[] = $pricingPolicy->toArray();
            break;
        case 3:
            // Fixed price by weight.
            foreach ($method['fixedPriceByWeightPolicy'] as $policy) {
                $pricingPolicy = new ShippingPricePolicy();
                $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_WEIGHT;
                $pricingPolicy->fromWeight = $policy['from'];
                $pricingPolicy->toWeight = !empty($policy['to']) ? $policy['to'] : null;
                $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                $pricingPolicy->fixedPrice = $policy['amount'];
                $result[] = $pricingPolicy->toArray();
            }
            break;
        case 4:
            // Fixed price by price.
            foreach ($method['fixedPriceByValuePolicy'] as $policy) {
                $pricingPolicy = new ShippingPricePolicy();
                $pricingPolicy->rangeType = ShippingPricePolicy::RANGE_PRICE;
                $pricingPolicy->fromPrice = $policy['from'];
                $pricingPolicy->toPrice = !empty($policy['to']) ? $policy['to'] : null;
                $pricingPolicy->pricingPolicy = ShippingPricePolicy::POLICY_FIXED_PRICE;
                $pricingPolicy->fixedPrice = $policy['amount'];
                $result[] = $pricingPolicy->toArray();
            }
            break;
    }

    return $result;
}

/**
 * Returns updated carrier logo file path for the given shipping method.
 *
 * @param array $method
 *
 * @return string
 */
function getLogoUrl($method)
{
    if (strpos($method['logoUrl'], '/views/img/core/images/') !== false) {
        return  $method['logoUrl'];
    }

    return str_replace('/views/img/', '/views/img/core/images/', $method['logoUrl']);
}
