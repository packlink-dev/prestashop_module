<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\Configuration as ConfigurationInterface;
use Packlink\BusinessLogic\Controllers\AnalyticsController;
use Packlink\BusinessLogic\DDP\DdpBehavior;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\Entities\CarrierServiceMapping;
use Packlink\PrestaShop\Classes\Utility\CachingUtility;
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
     * Translatable suffix appended to the title of the duties-paid carrier. This is the only place a DDP
     * carrier name is composed; never derive one carrier's name from the other by stripping strings.
     */
    const DDP_TITLE_SUFFIX = 'Delivery Duty Paid';

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

        if (!$this->createCarrier($shippingMethod, false)) {
            return false;
        }

        $this->syncDdpCarrier($shippingMethod);

        return true;
    }

    /**
     * Creates a PrestaShop carrier for the given shipping method and maps it to that method.
     *
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     * @param bool $isDdp Whether the duties-paid variant is being created.
     *
     * @return bool TRUE if the carrier has been created; otherwise, FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     */
    private function createCarrier(ShippingMethod $shippingMethod, $isDdp)
    {
        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = new \Carrier();

        $this->setCarrierData($carrier, $shippingMethod, $isDdp);

        try {
            if ($carrier->add()) {
                $carrier->setTaxRulesGroup((int)$shippingMethod->getTaxClass() ?: static::DEFAULT_TAX_CLASS);

                $this->setCarrierGroups($carrier);
                $ranges = $this->setCarrierRanges($carrier);
                $this->setCarrierZones($carrier, $shippingMethod, $ranges);

                $logoUrl = $shippingMethod->getLogoUrl();
                $isDisplay = $this->validateLogoUrl($logoUrl);
                if ($isDisplay) {
                    $this->updateCarrierLogo($shippingMethod, $carrier);
                }

                $this->saveCarrierServiceMapping((int)$carrier->id, $shippingMethod->getId(), $isDdp);

                return true;
            }
        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), 'Integration');
            $this->cleanUpCarrierData($carrier);
        }

        return false;
    }

    /**
     * Creates, refreshes or removes the duties-paid carrier so that it exists exactly when the shipping
     * method's effective DDP behavior is not NONE.
     *
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     */
    private function syncDdpCarrier(ShippingMethod $shippingMethod)
    {
        $ddpReferenceId = $this->getDdpCarrierReferenceId($shippingMethod->getId());
        $shouldExist = $shippingMethod->getEffectiveDdpBehavior() !== DdpBehavior::NONE;

        if (!$shouldExist) {
            if ($ddpReferenceId !== null) {
                $this->removeCarrier($ddpReferenceId);
                $this->deleteCarrierServiceMappingByReferenceId($ddpReferenceId);
            }

            return;
        }

        if ($ddpReferenceId === null) {
            if (!$this->createCarrier($shippingMethod, true)) {
                // Not fatal: pricing keeps the base carrier visible while the twin is missing (DP5),
                // but the merchant's behaviour setting is silently not honoured until the next sync.
                Logger::logWarning(
                    'Failed to create the duties-paid carrier for shipping method '
                    . (int)$shippingMethod->getId(),
                    'Integration'
                );
            }

            return;
        }

        $this->refreshCarrier($ddpReferenceId, $shippingMethod, true);
    }

    /**
     * Updates shipping method in shop integration.
     *
     * @param ShippingMethod $shippingMethod Shipping method.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     */
    public function update(ShippingMethod $shippingMethod)
    {
        $referenceId = $this->getCarrierReferenceId($shippingMethod->getId());
        if ($referenceId === null) {
            $this->add($shippingMethod);

            return;
        }

        $this->refreshCarrier($referenceId, $shippingMethod, false);
        $this->syncDdpCarrier($shippingMethod);
    }

    /**
     * Refreshes an existing carrier from the shipping method.
     *
     * @param int $referenceId PrestaShop carrier reference ID.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     * @param bool $isDdp Whether the carrier is the duties-paid variant.
     *
     * @throws \PrestaShopException
     */
    private function refreshCarrier($referenceId, ShippingMethod $shippingMethod, $isDdp)
    {
        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = \Carrier::getCarrierByReference($referenceId);

        if (!$carrier) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');

            return;
        }

        try {
            $this->setCarrierData($carrier, $shippingMethod, $isDdp);
            $ranges = $this->setCarrierRanges($carrier);
            $this->setCarrierZones($carrier, $shippingMethod, $ranges);

            $carrier->setTaxRulesGroup((int)$shippingMethod->getTaxClass() ?: static::DEFAULT_TAX_CLASS);
            $logoUrl = $shippingMethod->getLogoUrl();
            $isDisplay = $this->validateLogoUrl($logoUrl);
            if ($isDisplay) {
                $this->updateCarrierLogo($shippingMethod, $carrier);
            }

            $carrier->update();
        } catch (\Exception $e) {
            Logger::logError($e->getMessage(), 'Integration');
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
        $referenceIds = $this->getCarrierReferenceIds($shippingMethod->getId());
        if (empty($referenceIds)) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');

            return true;
        }

        $this->deleteCarrierServiceMapping($shippingMethod->getId());

        foreach ($referenceIds as $referenceId) {
            $this->removeCarrier($referenceId);
        }

        return true;
    }

    /**
     * Marks the carrier behind the given reference ID as deleted and removes its logo.
     *
     * @param int $referenceId PrestaShop carrier reference ID.
     *
     * @throws \PrestaShopException
     */
    private function removeCarrier($referenceId)
    {
        /** @var \Carrier $carrier PrestaShop carrier object. */
        $carrier = \Carrier::getCarrierByReference($referenceId);

        if (!$carrier) {
            Logger::logWarning(TranslationUtility::__('Carrier not found'), 'Integration');

            return;
        }

        if ($carrier->deleted) {
            Logger::logWarning(TranslationUtility::__('Carrier already deleted'), 'Integration');

            return;
        }

        $prestaCarrierLogoPath = $this->getPrestaCarrierLogoPath($carrier->id);
        if (\Tools::file_exists_cache($prestaCarrierLogoPath)) {
            unlink($prestaCarrierLogoPath);
        }

        $carrier->deleted = true;
        $carrier->update();
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
        $ranges = $this->setCarrierRanges($carrier);
        $this->setBackupCarrierZones($carrier, $shippingMethod, $ranges);

        $logoUrl = $shippingMethod->getLogoUrl();
        $isDisplay = $this->validateLogoUrl($logoUrl);
        if ($isDisplay && !$this->copyCarrierLogo($carrier->name, (int)$carrier->id,  $shippingMethod->getLogoUrl())) {
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
        foreach (CachingUtility::getCarrierServiceMappings() as $mapping) {
            if ((int)$mapping->carrierReferenceId === (int)$carrierReferenceId) {
                return $mapping;
            }
        }

        return null;
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
        $mapping = $this->getMappingByMethodId($methodId, false);

        return $mapping ? $mapping->carrierReferenceId : null;
    }

    /**
     * Returns reference ID of the duties-paid carrier mapped by shipping method service ID.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @return int|null PrestaShop carrier reference ID or null if the method has no DDP carrier.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getDdpCarrierReferenceId($methodId)
    {
        $mapping = $this->getMappingByMethodId($methodId, true);

        return $mapping ? $mapping->carrierReferenceId : null;
    }

    /**
     * Returns reference IDs of every carrier mapped to the given shipping method, base and duties-paid.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @return int[] PrestaShop carrier reference IDs.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function getCarrierReferenceIds($methodId)
    {
        $result = array();
        foreach ($this->getMappingsByMethodId($methodId) as $mapping) {
            $result[] = $mapping->carrierReferenceId;
        }

        return $result;
    }

    /**
     * Checks whether the carrier behind the given reference ID is a duties-paid carrier.
     *
     * @param int $carrierReferenceId PrestaShop carrier reference ID.
     *
     * @return bool
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function isDdpCarrier($carrierReferenceId)
    {
        $mapping = $this->getMappingByCarrierReferenceId($carrierReferenceId);

        return $mapping !== null && (bool)$mapping->isDdp;
    }

    /**
     * Returns all carrier service mappings for the given shipping method.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @return CarrierServiceMapping[]
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getMappingsByMethodId($methodId)
    {
        $result = array();
        foreach (CachingUtility::getCarrierServiceMappings() as $mapping) {
            if ((int)$mapping->methodId === (int)$methodId) {
                $result[] = $mapping;
            }
        }

        return $result;
    }

    /**
     * Returns the base or the duties-paid mapping of the given shipping method.
     *
     * The DDP flag is deliberately not part of the index map, so it is matched in PHP: mappings written
     * before DDP support carry no value at all and must be read as the base carrier.
     *
     * @param int $methodId Packlink shipping method ID.
     * @param bool $isDdp Whether the duties-paid mapping is wanted.
     *
     * @return CarrierServiceMapping|null
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function getMappingByMethodId($methodId, $isDdp)
    {
        foreach ($this->getMappingsByMethodId($methodId) as $mapping) {
            if ((bool)$mapping->isDdp === (bool)$isDdp) {
                return $mapping;
            }
        }

        return null;
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
        $carrierServiceMapping = $this->getMappingByCarrierReferenceId($carrierReferenceId);

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
        $result = array();

        try {
            $result = $this->getNonPacklinkCarriers('count(*) as shippingMethodsCount');
        } catch (\PrestaShopException $e) {
            Logger::logError('Error searching for non-Packlink carriers. Error: ' . $e->getMessage(), 'Integration');
        }

        return !empty($result[0]['shippingMethodsCount']) ? (int)$result[0]['shippingMethodsCount'] : 0;
    }

    /**
     * Disables shop shipping services/carriers.
     *
     * @return boolean TRUE if operation succeeded; otherwise, false.
     */
    public function disableShopServices()
    {
        try {
            $result = $this->getNonPacklinkCarriers();

            $ids = array_column($result, 'id_carrier');
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
     * Disables active Packlink carriers.
     *
     * @return bool TRUE if operation succeeded; otherwise, false.
     */
    public function disablePacklinkCarriers()
    {
        try {
            $carrierIds = $this->getPacklinkCarrierIds();

            foreach ($carrierIds as $carrierId) {
                $carrier = new \Carrier((int)$carrierId);
                $carrier->active = false;
                $carrier->update();
            }

            return true;
        } catch (\PrestaShopException $e) {
            Logger::logError(
                'Error disabling Packlink carriers. Error: ' . $e->getMessage(),
                'Integration'
            );
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
        $relativePath = $this->getCarrierLogoRelativePath($carrierName);

        return \Context::getContext()->link->getMediaLink(_MODULE_DIR_ . $relativePath);
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
            && !$this->copyCarrierLogo($shippingMethod->getCarrierName(),
                (int)$carrier->id,
                $shippingMethod->getLogoUrl())
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
    private function getCarrierLogoRelativePath($carrierName, $fallbackLogoUrl = '')
    {
        $defaultCarrierLogoPath = 'packlink/views/img/carrier.jpg';

        $carrierImageFile = \Tools::strtolower(str_replace(' ', '-', $carrierName));
        $logoFilePath = 'packlink/views/img/core/images/carriers/' . $carrierImageFile . '.png';

        if (!\Tools::file_exists_cache(_PS_MODULE_DIR_ . $logoFilePath)) {
            $shopUrl = _PS_BASE_URL_ . __PS_BASE_URI__;

            $relativeFallbackPath = str_replace($shopUrl, '', $fallbackLogoUrl);
            $relativeFallbackPath = preg_replace('/^modules\//', '', $relativeFallbackPath);
            return \Tools::file_exists_cache(_PS_MODULE_DIR_ . $relativeFallbackPath)
                ? $relativeFallbackPath : $defaultCarrierLogoPath;
        }

        return $logoFilePath;
    }

    /**
     * Sets shipping method data to carrier object.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     * @param bool $isDdp Whether the carrier is the duties-paid variant.
     */
    private function setCarrierData(\Carrier $carrier, ShippingMethod $shippingMethod, $isDdp = false)
    {
        $carrier->name = $isDdp
            ? $shippingMethod->getTitle() . ' - ' . TranslationUtility::__(static::DDP_TITLE_SUFFIX)
            : $shippingMethod->getTitle();
        $carrier->active = true;
        $carrier->deleted = false;
        if (!$carrier->id) {
            $carrier->shipping_handling = false;
        }

        $carrier->is_free = false;
        $carrier->shipping_method = \Carrier::SHIPPING_METHOD_DEFAULT;

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
     * @param bool $isDdp Whether the mapped carrier is the duties-paid variant.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function saveCarrierServiceMapping($carrierReferenceId, $methodId, $isDdp = false)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());
        $carrierServiceMapping = new CarrierServiceMapping();

        $carrierServiceMapping->carrierReferenceId = $carrierReferenceId;
        $carrierServiceMapping->methodId = $methodId;
        $carrierServiceMapping->isDdp = (bool)$isDdp;

        $serviceMappingRepository->save($carrierServiceMapping);
        // Same-request reads (e.g. syncDdpCarrier right after createCarrier) must see this write.
        CachingUtility::resetCarrierServiceMappings();
    }

    /**
     * Deletes every carrier service mapping for given shipping method, base and duties-paid alike.
     *
     * @param int $methodId Packlink shipping method ID.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function deleteCarrierServiceMapping($methodId)
    {
        $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());

        foreach ($this->getMappingsByMethodId($methodId) as $carrierServiceMapping) {
            $serviceMappingRepository->delete($carrierServiceMapping);
        }

        CachingUtility::resetCarrierServiceMappings();
    }

    /**
     * Deletes the carrier service mapping identified by a carrier reference ID.
     *
     * @param int $carrierReferenceId PrestaShop carrier reference ID.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function deleteCarrierServiceMappingByReferenceId($carrierReferenceId)
    {
        $carrierServiceMapping = $this->getMappingByCarrierReferenceId($carrierReferenceId);

        if ($carrierServiceMapping !== null) {
            $serviceMappingRepository = RepositoryRegistry::getRepository(CarrierServiceMapping::getClassName());
            $serviceMappingRepository->delete($carrierServiceMapping);
            CachingUtility::resetCarrierServiceMappings();
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
     * @return \ObjectModel[] Array of PrestaShop range objects.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function setCarrierRanges(\Carrier $carrier)
    {
        // we need to remove old ones before adding new one.
        $this->removeCarrierRanges($carrier);

        return $this->setRanges($carrier);
    }

    /**
     * Sets shipping ranges for a given shipping method.
     *
     * @param \Carrier $carrier
     *
     * @return \ObjectModel[]
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function setRanges(\Carrier $carrier)
    {
        return array(
            $this->addDefaultWeightRange($carrier->id),
            $this->addDefaultPriceRange($carrier->id),
        );
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
    private function addDefaultWeightRange($carrierId)
    {
        $rangeWeight = new \RangeWeight();
        $rangeWeight->id_carrier = $carrierId;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '10000';
        $rangeWeight->add();

        return $rangeWeight;
    }

    /**
     * Creates a default price range.
     *
     * @param int $carrierId
     *
     * @return \RangePrice
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function addDefaultPriceRange($carrierId)
    {
        $rangeWeight = new \RangePrice();
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
     * @param \ObjectModel[] $ranges PrestaShop prince/weight ranges.
     * @param ShippingMethod $shippingMethod Packlink shipping method entity.
     */
    private function setBackupCarrierZones(\Carrier $carrier, ShippingMethod $shippingMethod, $ranges)
    {
        $defaultCost = PHP_INT_MAX;
        foreach ($shippingMethod->getShippingServices() as $shippingService) {
            $defaultCost = min($defaultCost, $shippingService->basePrice);
        }

        $this->deleteCarrierZones($carrier);
        $this->setCarrierZones($carrier, $shippingMethod, $ranges, $defaultCost);
    }

    /**
     * Sets carrier zones.
     *
     * @param \Carrier $carrier PrestaShop carrier object.
     * @param ShippingMethod $shippingMethod
     * @param \ObjectModel[] $ranges PrestaShop prince/weight ranges.
     * @param float $price Delivery price.
     */
    private function setCarrierZones(\Carrier $carrier, ShippingMethod $shippingMethod, $ranges, $price = 0.0)
    {
        $this->deleteCarrierZones($carrier);

        $zones = \Zone::getZones(true);
        foreach ($zones as $zone) {
            if ($shippingMethod->isShipToAllCountries()
                || in_array((string)$zone['id_zone'], $shippingMethod->getShippingCountries(), true)
            ) {
                $carrier->addZone((int)$zone['id_zone']);
                $priceList = array();
                foreach ($ranges as $range) {
                    $priceList[] = array(
                        'id_carrier' => (int)$carrier->id,
                        'id_zone' => (int)$zone['id_zone'],
                        'id_range_price' => $range instanceof \RangePrice ? (int)$range->id : null,
                        'id_range_weight' =>$range instanceof \RangeWeight ? (int)$range->id : null,
                        'price' => $price,
                    );
                }

                $carrier->addDeliveryPrice($priceList);
            }
        }
    }

    /**
     * Deletes existing shipping zones for the provided carrier.
     *
     * @param \Carrier $carrier
     */
    private function deleteCarrierZones(\Carrier $carrier)
    {
        /** @var \Zone[] $zones */
        $zones = $carrier->getZones();
        foreach ($zones as $zone) {
            $carrier->deleteZone($zone['id_zone']);
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
    private function copyCarrierLogo($shippingMethodName, $carrierId, $fallbackLogoUrl = '')
    {
        $source = _PS_MODULE_DIR_ . $this->getCarrierLogoRelativePath($shippingMethodName, $fallbackLogoUrl);

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


    /**
     * Validates the logo URL.
     *
     * @param string $logoUrl Logo URL to validate.
     *
     * @return bool True if the URL is valid and points to a PNG or JPG/JPEG image, false otherwise.
     */
    private function validateLogoUrl($logoUrl)
    {
        if (
            empty($logoUrl) ||
            !filter_var($logoUrl, FILTER_VALIDATE_URL) ||
            !preg_match('/\.(png|jpe?g)$/i', parse_url($logoUrl, PHP_URL_PATH))
        ) {
            return false;
        }

        return true;
    }
}
