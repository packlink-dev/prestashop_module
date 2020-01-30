<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Controllers\AnalyticsController;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\BusinessLogic\Utility\Php\Php55;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class CarrierService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CarrierService implements ShopShippingMethodService
{
    const DEFAULT_TAX_CLASS = 0;
    const DEFAULT_TAX_CLASS_LABEL = 'No tax';

    /**
     * Adds / Activates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @return bool TRUE if activation succeeded; otherwise, FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     */
    public function add(ShippingMethod $shippingMethod)
    {
        $referenceId = $this->getCarrierReferenceId($shippingMethod->getId());
        if ($referenceId !== null) {
            $this->update($shippingMethod);

            return true;
        }

        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = new \Carrier();

        $this->setCarrierData($carrier, $shippingMethod);

        try {
            if ($carrier->add()) {
                $carrier->setTaxRulesGroup((int)$shippingMethod->getTaxClass() ?: static::DEFAULT_TAX_CLASS);

                $this->setCarrierGroups($carrier);
                $range = $this->setCarrierRange($carrier, $shippingMethod);
                $this->setCarrierZones($carrier, $range->id, $shippingMethod);

                $this->updateCarrierLogo($shippingMethod, $carrier);
                $this->saveCarrierServiceMapping((int)$carrier->id, $shippingMethod->getId());

                return true;
            }
        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), 'Integration');
            $this->cleanUpCarrierData($carrier);
        }

        return false;
    }

    /**
     * Updates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function update(ShippingMethod $shippingMethod)
    {
        $referenceId = $this->getCarrierReferenceId($shippingMethod->getId());
        if ($referenceId === null) {
            Logger::logWarning(TranslationUtility::__('Carrier service mapping not found'), 'Integration');
        } else {
            /** @var \Carrier $carrier PrestaShop carrier object. */
            $carrier = \Carrier::getCarrierByReference($referenceId);

            if ($carrier) {
                try {
                    $this->setCarrierData($carrier, $shippingMethod);
                    $this->setCarrierRange($carrier, $shippingMethod);
                    $carrier->setTaxRulesGroup((int)$shippingMethod->getTaxClass() ?: static::DEFAULT_TAX_CLASS);
                    $this->updateCarrierLogo($shippingMethod, $carrier);
                    $carrier->update();
                } catch (\Exception $e) {
                    Logger::logError($e->getMessage(), 'Integration');
                }
            } else {
                Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');
            }
        }
    }

    /**
     * Deletes shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @return bool TRUE if deletion succeeded; otherwise, FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     */
    public function delete(ShippingMethod $shippingMethod)
    {
        $referenceId = $this->getCarrierReferenceId($shippingMethod->getId());
        if ($referenceId === null) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');

            return true;
        }

        $this->deleteCarrierServiceMapping($shippingMethod->getId());

        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = \Carrier::getCarrierByReference($referenceId);

        if (!$carrier) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');
        } elseif ($carrier->deleted) {
            Logger::logWarning(TranslationUtility::__('Carrier already deleted'), 'Integration');
        } else {
            $prestaCarrierLogoPath = $this->getPrestaCarrierLogoPath($carrier->id);
            if (\Tools::file_exists_cache($prestaCarrierLogoPath)) {
                unlink($prestaCarrierLogoPath);
            }

            $carrier->deleted = true;
            $carrier->update();
        }

        return true;
    }

    /**
     * Adds backup shipping method based on provided shipping method.
     *
     * @param ShippingMethod $shippingMethod
     *
     * @return bool TRUE if backup shipping method is added; otherwise, FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function addBackupShippingMethod(ShippingMethod $shippingMethod)
    {
        $carrier = new \Carrier();
        $this->setCarrierData($carrier, $shippingMethod);
        $carrier->name = 'shipping cost';

        if (!$carrier->add()) {
            return false;
        }

        $this->setCarrierGroups($carrier);
        $range = $this->setCarrierRange($carrier, $shippingMethod);
        $this->setBackupCarrierZones($carrier, $range->id, $shippingMethod);

        if (!$this->copyCarrierLogo($carrier->name, (int)$carrier->id)) {
            throw new \RuntimeException(
                TranslationUtility::__('Failed copying carrier logo to the system')
            );
        }

        $this->saveCarrierServiceMapping((int)$carrier->id, 0);
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
        $configService->setBackupCarrierId((int)$carrier->id);

        return true;
    }

    /**
     * Deletes backup shipping method.
     *
     * @return bool TRUE if backup shipping method is deleted; otherwise, FALSE.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function deleteBackupShippingMethod()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);

        $backupCarrierId = $configService->getBackupCarrierId();
        if ($backupCarrierId === null) {
            Logger::logWarning(TranslationUtility::__('Backup carrier not found'));

            return false;
        }

        $carrier = \Carrier::getCarrierByReference($backupCarrierId);
        if ($carrier === false) {
            Logger::logWarning(TranslationUtility::__('Backup carrier not found'));

            return false;
        }

        $carrierLogoPath = $this->getPrestaCarrierLogoPath($backupCarrierId);
        if (\Tools::file_exists_cache($carrierLogoPath)) {
            unlink($carrierLogoPath);
        }

        $carrier->deleted = true;
        $carrier->update();

        $configService->setBackupCarrierId(null);

        return true;
    }

    /**
     * Returns carrier service mapping object identified by carrier ID.
     *
     * @param int $carrierReferenceId ID of the carrier.
     *
     * @return CarrierServiceMapping|null Carrier service mapping entity or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function getMappingByCarrierReferenceId($carrierReferenceId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('carrierReferenceId', Operators::EQUALS, $carrierReferenceId);
        /** @var CarrierServiceMapping $carrierServiceMapping */
        /** @noinspection OneTimeUseVariablesInspection */
        $carrierServiceMapping = $serviceMappingRepository->selectOne($filter);

        return $carrierServiceMapping;
    }

    /**
     * Returns reference ID of the carrier mapped by shipping method service ID.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @return int|null PrestaShop carrier reference ID or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getCarrierReferenceId($methodId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('methodId', Operators::EQUALS, $methodId);
        /** @var CarrierServiceMapping $carrierServiceMapping */
        $carrierServiceMapping = $serviceMappingRepository->selectOne($filter);

        return $carrierServiceMapping ? $carrierServiceMapping->carrierReferenceId : null;
    }

    /**
     * Returns Packlink shipping method ID mapped by carrier reference ID.
     *
     * @param int $carrierReferenceId PrestaShop carrier reference ID.
     *
     * @return int|null Packlink shipping method ID or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getShippingMethodId($carrierReferenceId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('carrierReferenceId', Operators::EQUALS, $carrierReferenceId);
        /** @var CarrierServiceMapping $carrierServiceMapping */
        $carrierServiceMapping = $serviceMappingRepository->selectOne($filter);

        return $carrierServiceMapping ? $carrierServiceMapping->methodId : null;
    }

    /**
     * Deletes all carriers added by Packlink.
     */
    public function deletePacklinkCarriers()
    {
        try {
            $carrierIds = $this->getPacklinkCarrierIds();
            foreach ($carrierIds as $carrierId) {
                $carrier = new \Carrier((int)$carrierId);
                $carrier->deleted = true;
                $carrier->update();
            }
        } catch (\PrestaShopException $e) {
            Logger::logError('Error marking carriers deleted. Error: ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Gets the number of non-Packlink carriers.
     *
     * @return int
     */
    public function getNumberOfOtherCarriers()
    {
        try {
            $result = $this->getNonPacklinkCarriers('count(*) as shippingMethodsCount');
        } catch (\PrestaShopException $e) {
            $result = array();
        }

        return !empty($result[0]['shippingMethodsCount']) ? (int)$result[0]['shippingMethodsCount'] : 0;
    }

    /**
     * Disables other carriers.
     *
     * @return bool
     */
    public function disableOtherCarriers()
    {
        try {
            $result = $this->getNonPacklinkCarriers();

            $ids = Php55::arrayColumn($result, 'id_carrier');
            foreach ($ids as $id) {
                $carrier = new \Carrier((int)$id);
                $carrier->active = false;
                $carrier->update();
            }

            AnalyticsController::sendOtherServicesDisabledEvent();

            return true;
        } catch (\PrestaShopDatabaseException $e) {
        } catch (\PrestaShopException $e) {
        }

        return false;
    }

    /**
     * Generates PrestaShop public URL for logo of carrier with provided title.
     *
     * @param string $carrierName Name of the carrier.
     *
     * @return string URL to carrier logo image file.
     */
    public function getCarrierLogoFilePath($carrierName)
    {
        return _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/' . $this->getCarrierLogoRelativePath($carrierName);
    }

    /**
     * Updates carrier logo.
     *
     * @param \Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod $shippingMethod
     * @param \Carrier $carrier
     */
    protected function updateCarrierLogo(ShippingMethod $shippingMethod, \Carrier $carrier)
    {
        $prestaLogoPath = $this->getPrestaCarrierLogoPath($carrier->id);
        if (\Tools::file_exists_cache($prestaLogoPath)) {
            unlink($prestaLogoPath);
        }

        if ($shippingMethod->isDisplayLogo()
            && !$this->copyCarrierLogo($shippingMethod->getCarrierName(), (int)$carrier->id)
        ) {
            throw new \RuntimeException(
                TranslationUtility::__('Failed copying carrier logo to the system')
            );
        }
    }

    /**
     * Returns path to carrier logo or default carrier logo if logo for requested carrier doesn't exist.
     *
     * @param string $carrierName Name of the carrier.
     *
     * @return string
     */
    private function getCarrierLogoRelativePath($carrierName)
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
        $userInfo = $configService->getUserInfo();
        $defaultCarrierLogoPath = 'packlink/views/img/carriers/carrier.jpg';

        if ($userInfo === null) {
            return $defaultCarrierLogoPath;
        }

        $carrierImageFile = \Tools::strtolower(str_replace(' ', '-', $carrierName));
        $logoFilePath = 'packlink/views/img/carriers/'
            . \Tools::strtolower($userInfo->country)
            . '/' . $carrierImageFile . '.png';

        return \Tools::file_exists_cache(_PS_MODULE_DIR_ . $logoFilePath)
            ? $logoFilePath : $defaultCarrierLogoPath;
    }

    /**
     * Sets shipping method data to carrier object.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     */
    private function setCarrierData(\Carrier $carrier, ShippingMethod $shippingMethod)
    {
        $carrier->name = $shippingMethod->getTitle();
        $carrier->active = true;
        $carrier->deleted = false;
        if (!$carrier->id) {
            $carrier->shipping_handling = false;
        }

        $carrier->is_free = false;
        if ($shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_VALUE) {
            $carrier->shipping_method = \Carrier::SHIPPING_METHOD_PRICE;
        } elseif ($shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_WEIGHT) {
            $carrier->shipping_method = \Carrier::SHIPPING_METHOD_WEIGHT;
        } else {
            $carrier->shipping_method = \Carrier::SHIPPING_METHOD_DEFAULT;
        }

        $carrier->need_range = true;
        $carrier->shipping_external = true;
        $carrier->external_module_name = 'packlink';
        $carrier->is_module = true;

        if (empty($carrier->delay)) {
            $languages = \Language::getLanguages();
            foreach ($languages as $language) {
                $carrier->delay[(int)$language['id_lang']] = $shippingMethod->getDeliveryTime();
            }
        }
    }

    /**
     * Saves carrier service mapping.
     *
     * @param int $carrierReferenceId Carrier entity reference ID.
     * @param int $methodId Packlink shipping method ID.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function saveCarrierServiceMapping($carrierReferenceId, $methodId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());
        $carrierServiceMapping = new CarrierServiceMapping();

        $carrierServiceMapping->carrierReferenceId = $carrierReferenceId;
        $carrierServiceMapping->methodId = $methodId;

        $serviceMappingRepository->save($carrierServiceMapping);
    }

    /**
     * Deletes carrier service mapping for given shipping method.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function deleteCarrierServiceMapping($methodId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('methodId', Operators::EQUALS, $methodId);
        $carrierServiceMapping = $serviceMappingRepository->selectOne($filter);

        if ($carrierServiceMapping !== null) {
            $serviceMappingRepository->delete($carrierServiceMapping);
        }
    }

    /**
     * Sets carrier groups.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     */
    private function setCarrierGroups(\Carrier $carrier)
    {
        $groups = \Group::getGroups(\Configuration::get('PS_LANG_DEFAULT'));
        $carrier->setGroups(Php55::arrayColumn($groups, 'id_group'));
    }

    /**
     * Sets carrier range weight.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     *
     * @param \Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod $shippingMethod
     *
     * @return \RangeWeight|\RangePrice PrestaShop range weight object,
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function setCarrierRange(\Carrier $carrier, ShippingMethod $shippingMethod)
    {
        // we need to remove old ones before adding new one.
        $this->removeCarrierRanges($carrier);

        /** @var \Packlink\BusinessLogic\ShippingMethod\Models\FixedPricePolicy[] $policy */
        $pricingByValue = $shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_VALUE;
        if ($pricingByValue) {
            $policy = $shippingMethod->getFixedPriceByValuePolicy();
        } elseif ($shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_WEIGHT) {
            $policy = $shippingMethod->getFixedPriceByWeightPolicy();
        } else {
            return $this->getDefaultWeightRange($carrier->id);
        }

        $max = 0;
        foreach ($policy as $fixedPricePolicy) {
            $max = max($fixedPricePolicy->to, $max);
        }

        /** @var \RangeWeight[] $ranges */
        if ($pricingByValue) {
            $ranges = \RangePrice::getRanges($carrier->id);
            $range = empty($ranges) ? new \RangePrice() : new \RangePrice($ranges[0]['id_range_price']);
        } else {
            $ranges = \RangeWeight::getRanges($carrier->id);
            $range = empty($ranges) ? new \RangeWeight() : new \RangeWeight($ranges[0]['id_range_weight']);
        }

        $range->id_carrier = $carrier->id;
        $range->delimiter1 = '0';
        $range->delimiter2 = (string)($max ?: 10000);
        $range->id ? $range->save() : $range->add();

        return $range;
    }

    /**
     * Removes carrier ranges, if any.
     *
     * @param \Carrier $carrier
     *
     * @throws \PrestaShopException
     */
    private function removeCarrierRanges(\Carrier $carrier)
    {
        /**
         * @var \RangeWeight|\RangePrice $class
         * @var string $id
         */
        foreach (array('\RangeWeight' => 'id_range_weight', '\RangePrice' => 'id_range_price') as $class => $id) {
            $ranges = $class::getRanges($carrier->id) ?: array();
            foreach ($ranges as $rangeData) {
                /** @var \RangeWeight|\RangePrice $range */
                $range = new $class($rangeData[$id]);
                $range->delete();
            }
        }
    }

    /**
     * Creates a default weight range.
     *
     * @param int $carrierId
     *
     * @return \RangeWeight
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function getDefaultWeightRange($carrierId)
    {
        $rangeWeight = new \RangeWeight();
        $rangeWeight->id_carrier = $carrierId;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '10000';
        $rangeWeight->add();

        return $rangeWeight;
    }

    /**
     * Sets zones for backup carrier by adding default shipping cost of the first carrier for delivery price.
     *
     * @param \Carrier $carrier PrestaShop carrier entity.
     * @param int $rangeId PrestaShop prince/weight range id.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     */
    private function setBackupCarrierZones(\Carrier $carrier, $rangeId, ShippingMethod $shippingMethod)
    {
        $defaultCost = PHP_INT_MAX;
        foreach ($shippingMethod->getShippingServices() as $shippingService) {
            $defaultCost = min($defaultCost, $shippingService->basePrice);
        }

        $this->setCarrierZones($carrier, $rangeId, $shippingMethod, $defaultCost);
    }

    /**
     * Sets carrier zones.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param int $rangeId
     * @param \Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod $shippingMethod
     * @param float $price Delivery price.
     */
    private function setCarrierZones(\Carrier $carrier, $rangeId, ShippingMethod $shippingMethod, $price = 0.0)
    {
        $zones = \Zone::getZones(true);
        foreach ($zones as $zone) {
            $carrier->addZone((int)$zone['id_zone']);
            $priceList = array();
            $priceList[] = array(
                'id_carrier' => (int)$carrier->id,
                'id_zone' => (int)$zone['id_zone'],
                'id_range_price' =>
                    $shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_VALUE
                        ? (int)$rangeId : null,
                'id_range_weight' =>
                    $shippingMethod->getPricingPolicy() === ShippingMethod::PRICING_POLICY_FIXED_PRICE_BY_WEIGHT
                        ? (int)$rangeId : null,
                'price' => $price,
            );
            $carrier->addDeliveryPrice($priceList);
        }
    }

    /**
     * Returns IDs of active carriers that have been created by Packlink module.
     *
     * @return array Array of carrier IDs.
     *
     * @throws \PrestaShopException
     */
    private function getPacklinkCarrierIds()
    {
        $query = new \DbQuery();
        $query->select('id_carrier')
            ->from('carrier')
            ->where('external_module_name = \'packlink\'')
            ->where('deleted = 0');

        $result = \Db::getInstance()->executeS($query);

        return Php55::arrayColumn($result, 'id_carrier');
    }

    /**
     * Copies carrier logo from plugin directory to PrestaShop shipping images directory.
     *
     * @param string $shippingMethodName Name of the shipping method.
     * @param int $carrierId ID of the carrier.
     *
     * @return bool Returns true if logo has been successfully copied, otherwise returns false.
     */
    private function copyCarrierLogo($shippingMethodName, $carrierId)
    {
        $source = _PS_MODULE_DIR_ . $this->getCarrierLogoRelativePath($shippingMethodName);

        if (!copy($source, $this->getPrestaCarrierLogoPath($carrierId))) {
            return false;
        }

        return true;
    }

    /**
     * Cleans up all information related to provided carrier and deletes carrier itself in event of exception.
     *
     * @param \Carrier $carrier PrestaShop carrier entity.
     *
     * @throws \PrestaShopException
     */
    private function cleanUpCarrierData(\Carrier $carrier)
    {
        if ($carrier->id === null) {
            return;
        }

        \Db::getInstance()->delete('carrier_group', 'id_carrier=' . $carrier->id);

        $this->removeCarrierRanges($carrier);

        $zones = $carrier->getZones();
        foreach ($zones as $zone) {
            $carrier->deleteZone((int)$zone['id_zone']);
        }

        $carrier->deleteDeliveryPrice($carrier->getRangeTable());

        $prestaLogoPath = $this->getPrestaCarrierLogoPath($carrier->id);
        if (\Tools::file_exists_cache($prestaLogoPath)) {
            unlink($prestaLogoPath);
        }

        $carrier->delete();
    }

    /**
     * Retrieves PrestaShop carrier logo path.
     *
     * @param int $id
     *
     * @return string
     */
    private function getPrestaCarrierLogoPath($id)
    {
        $imgDir = _PS_SHIP_IMG_DIR_;

        return rtrim($imgDir, '/') . '/' . $id . '.jpg';
    }

    /**
     * Gets non-Packlink carriers.
     *
     * @param string $select
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getNonPacklinkCarriers($select = 'id_carrier')
    {
        $db = \Db::getInstance();
        $query = new \DbQuery();
        $query->select($select)
            ->from('carrier')
            ->where("external_module_name <> 'packlink'")
            ->where('active = 1')
            ->where('deleted = 0');

        return $db->executeS($query) ?: array();
    }
}
