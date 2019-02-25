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

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class CarrierService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CarrierService implements ShopShippingMethodService
{
    /**
     * Adds / Activates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @return bool TRUE if activation succeeded; otherwise, FALSE.
     *
     * @throws \PrestaShopException
     */
    public function add(ShippingMethod $shippingMethod)
    {
        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = new \Carrier();

        $carrier->name = $shippingMethod->getTitle();
        $this->setCarrierData($carrier, $shippingMethod);

        try {
            if ($carrier->add()) {
                $this->setCarrierGroups($carrier);
                $rangeWeight = $this->setCarrierRangeWeight($carrier);
                $this->setCarrierZones($carrier, $rangeWeight);

                $this->updateCarrierLogo($shippingMethod, $carrier);
                $this->saveCarrierServiceMapping((int)$carrier->id, $shippingMethod->getId());

                if (count($this->getPacklinkCarrierIds()) === 1) {
                    $this->addBackupCarrier($shippingMethod);
                }

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
            return false;
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

            if (count($this->getPacklinkCarrierIds()) === 1) {
                $this->deleteBackupCarrier();
            }
        }

        return true;
    }

    /**
     * Returns carrier service mapping object identified by carrier ID.
     *
     * @param int $carrierId ID of the carrier.
     *
     * @return CarrierServiceMapping|null Carrier service mapping entity or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function getMappingByCarrierId($carrierId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('carrierId', Operators::EQUALS, $carrierId);
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

        return $carrierServiceMapping ? $carrierServiceMapping->carrierId : null;
    }

    /**
     * Returns Packlink shipping method ID mapped by carrier reference ID.
     *
     * @param int $carrierId PrestaShop carrier reference ID.
     *
     * @return int|null Packlink shipping method ID or null if not found.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getShippingMethodId($carrierId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        $filter = new QueryFilter();
        $filter->where('carrierId', Operators::EQUALS, $carrierId);
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
     * Returns path to carrier logo or default carrier logo if logo for requested carrier doesn't exist.
     *
     * @param string $carrierName Name of the carrier.
     *
     * @return string
     */
    public function getCarrierLogoFilePath($carrierName)
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
     * Sets shipping method data to carrier object.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     */
    private function setCarrierData(\Carrier $carrier, ShippingMethod $shippingMethod)
    {
        $carrier->active = true;
        $carrier->deleted = false;
        $carrier->shipping_handling = false;
        $carrier->is_free = false;
        $carrier->shipping_method = \Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->setTaxRulesGroup(1, true);
        $carrier->range_behavior = false;
        $carrier->need_range = true;
        $carrier->shipping_external = true;
        $carrier->external_module_name = 'packlink';
        $carrier->is_module = true;

        $languages = \Language::getLanguages();
        foreach ($languages as $language) {
            $carrier->delay[(int)$language['id_lang']] = $shippingMethod->getDeliveryTime();
        }
    }

    /**
     * Adds Packlink backup carrier based on first configured Packlink shipping method entity.
     *
     * @param ShippingMethod $shippingMethod First configured Packlink shipping method entity.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function addBackupCarrier(ShippingMethod $shippingMethod)
    {
        $carrier = new \Carrier();
        $carrier->name = 'shipping cost';
        $this->setCarrierData($carrier, $shippingMethod);

        if (!$carrier->add()) {
           throw new \RuntimeException(TranslationUtility::__('Failed creating backup carrier'));
        }

        $this->setCarrierGroups($carrier);
        $rangeWeight = $this->setCarrierRangeWeight($carrier);
        $this->setBackupCarrierZones($carrier, $rangeWeight, $shippingMethod);

        if (!$this->copyCarrierLogo($carrier->name, (int)$carrier->id)) {
            throw new \RuntimeException(
                TranslationUtility::__('Failed copying carrier logo to the system')
            );
        }

        $this->saveCarrierServiceMapping((int)$carrier->id, 0);
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);
        $configService->setBackupCarrierId((int)$carrier->id);
    }

    /**
     * Deletes Packlink backup carrier.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function deleteBackupCarrier()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(ConfigurationInterface::CLASS_NAME);

        $backupCarrierId = $configService->getBackupCarrierId();
        if ($backupCarrierId === null) {
            Logger::logError(TranslationUtility::__('Backup carrier not found'));

            return;
        }

        $carrier = \Carrier::getCarrierByReference($backupCarrierId);
        if ($carrier === false) {
            Logger::logError(TranslationUtility::__('Backup carrier not found'));

            return;
        }

        $carrierLogoPath = $this->getPrestaCarrierLogoPath($backupCarrierId);
        if (\Tools::file_exists_cache($carrierLogoPath)) {
            unlink($carrierLogoPath);
        }

        $carrier->deleted = true;
        $carrier->update();

        $configService->setBackupCarrierId(null);
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

        $carrierServiceMapping->carrierId = $carrierReferenceId;
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
        $carrier->setGroups(array_column($groups, 'id_group'));
    }

    /**
     * Sets carrier range weight.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     *
     * @return \RangeWeight PrestaShop range weight object,
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function setCarrierRangeWeight(\Carrier $carrier)
    {
        /** @var \RangeWeight $rangeWeight */
        $rangeWeight = new \RangeWeight();

        $rangeWeight->id_carrier = $carrier->id;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '10000';
        $rangeWeight->add();

        return $rangeWeight;
    }

    /**
     * Sets zones for backup carrier by adding default shipping cost of the first carrier for delivery price.
     *
     * @param \Carrier $carrier PrestaShop carrier entity.
     * @param \RangeWeight $rangeWeight PrestaShop range weight.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     */
    private function setBackupCarrierZones(\Carrier $carrier, \RangeWeight $rangeWeight, ShippingMethod $shippingMethod)
    {
        $defaultCost = PHP_INT_MAX;
        foreach ($shippingMethod->getShippingServices() as $shippingService) {
            $defaultCost = min($defaultCost, $shippingService->basePrice);
        }

        $this->setCarrierZones($carrier, $rangeWeight, $defaultCost);
    }

    /**
     * Sets carrier zones.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param \RangeWeight $rangeWeight PrestaShop range weight object.
     * @param float $price Delivery price.
     */
    private function setCarrierZones(\Carrier $carrier, \RangeWeight $rangeWeight, $price = 0.0)
    {
        $zones = \Zone::getZones(true);
        foreach ($zones as $zone) {
            $carrier->addZone((int)$zone['id_zone']);
            $priceList = array();
            $priceList[] = array(
                'id_carrier' => (int)$carrier->id,
                'id_zone' => (int)$zone['id_zone'],
                'id_range_price' => null,
                'id_range_weight' => (int)$rangeWeight->id,
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
     * @throws \PrestaShopDatabaseException
     */
    private function getPacklinkCarrierIds()
    {
        $query = new \DbQuery();
        $query->select('id_carrier')
            ->from('carrier')
            ->where('external_module_name = \'packlink\'')
            ->where('deleted = 0');

        $result = \Db::getInstance()->executeS($query);

        return array_column($result, 'id_carrier');
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
        $source = _PS_MODULE_DIR_ . $this->getCarrierLogoFilePath($shippingMethodName);

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

        $ranges = \RangeWeight::getRanges($carrier->id);
        foreach ($ranges as $range) {
            $rangeWeight = new \RangeWeight((int)$range['id_range_weight']);
            $rangeWeight->delete();
        }

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
}
