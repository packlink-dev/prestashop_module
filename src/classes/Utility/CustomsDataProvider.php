<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CustomsMappingService;
use Packlink\PrestaShop\Classes\Entities\CustomerCustomsData;
use Packlink\PrestaShop\Classes\Entities\ProductCustomsData;

/**
 * Class CustomsDataProvider.
 *
 * Single place to read the module-owned customs entities (product HS code / country of origin and
 * the customer tax id). Shared by the product/customer admin hooks (packlink.php) and the order
 * build (ShopOrderService) so the lookup is written once.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class CustomsDataProvider
{
    /**
     * Customs mapping configuration, or null when it could not be loaded.
     *
     * @var \Packlink\BusinessLogic\Customs\Models\CustomsMapping|null
     */
    private $customsMapping;
    /**
     * Product id => ProductCustomsData for the current build; null when nothing has been preloaded.
     *
     * @var ProductCustomsData[]|null
     */
    private $productCustomsCache;

    /**
     * @param \Packlink\BusinessLogic\Customs\Models\CustomsMapping|null $customsMapping Customs mapping
     *     configuration. Pass the already-loaded mapping so a single build reads it once.
     */
    public function __construct($customsMapping = null)
    {
        $this->customsMapping = $customsMapping;
    }

    /**
     * Loads the module-owned customs data for every given product id in a single query and caches it
     * for the current build, replacing a per-line N+1 lookup.
     *
     * @param int[] $productIds
     */
    public function preloadProducts(array $productIds)
    {
        $this->productCustomsCache = array();

        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (empty($productIds)) {
            return;
        }

        try {
            $repository = RepositoryRegistry::getRepository(ProductCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('productId', 'IN', $productIds);

            /** @var ProductCustomsData[] $rows */
            $rows = $repository->select($query);
            foreach ($rows as $row) {
                $this->productCustomsCache[(int)$row->productId] = $row;
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to preload product customs data: ' . $e->getMessage(), 'Integration');
        }
    }

    /**
     * Resolves the item tariff number from whichever source the merchant mapped.
     *
     * A malformed value is dropped rather than sent: Packlink rejects a customs invoice whose tariff
     * number is not 6 to 8 digits, and dropping it lets the configured default apply instead of
     * failing the whole draft. Digits are extracted first, so a feature holding "6109 10 00" or
     * "HS 61091000" still maps cleanly.
     *
     * @param int $productId
     *
     * @return string Empty string when nothing usable is configured for this product.
     */
    public function resolveTariffNumber($productId)
    {
        $source = $this->tariffNumberSource();

        if ($source === CustomsMappingService::SOURCE_PRODUCT_HS_CODE) {
            $customs = $this->productCustomsData($productId);
            $value = ($customs !== null && !empty($customs->hsCode)) ? $customs->hsCode : '';
        } else {
            $value = ProductFeatureSources::getValue($source, $productId, ProductFeatureSources::resolveLanguageId());
        }

        $digits = preg_replace('/[^0-9]/', '', (string)$value);
        if ($digits === '' ) {
            return '';
        }

        if (!preg_match('/^[0-9]{6,8}$/', $digits)) {
            Logger::logWarning(
                TranslationUtility::__(
                    'Ignoring mapped tariff number "%s" for product %s: expected 6 to 8 digits.',
                    array((string)$value, (string)$productId)
                ),
                'Integration'
            );

            return '';
        }

        return $digits;
    }

    /**
     * Resolves the item country of origin from whichever source the merchant mapped, as an ISO
     * 3166-1 alpha-2 code.
     *
     * A merchant-created feature usually holds a country name ("Germany"), not a code, so the value
     * is resolved through the shop's country table as well as being accepted as a code.
     *
     * @param int $productId
     *
     * @return string Empty string when nothing usable is configured for this product.
     */
    public function resolveCountryOfOrigin($productId)
    {
        $source = $this->countryOfOriginSource();

        if ($source === CustomsMappingService::SOURCE_PRODUCT_COUNTRY_OF_ORIGIN) {
            $customs = $this->productCustomsData($productId);

            // Already stored as an ISO code by the product page, so no resolution needed.
            return ($customs !== null && !empty($customs->countryOfOrigin)) ? $customs->countryOfOrigin : '';
        }

        $languageId = ProductFeatureSources::resolveLanguageId();

        $value = ProductFeatureSources::getValue($source, $productId, $languageId);
        if ($value === '') {
            return '';
        }

        $iso = CountryOriginOptions::resolveIso($value, $languageId);
        if ($iso === '') {
            Logger::logWarning(
                TranslationUtility::__(
                    'Ignoring mapped country of origin "%s" for product %s: not a known country name or ISO code.',
                    array($value, (string)$productId)
                ),
                'Integration'
            );
        }

        return $iso;
    }

    /**
     * Resolves the receiver tax id from the source the merchant selected in the customs mapping
     * (mapping_receiver_tax_id); defaults to the module customer Tax ID field.
     *
     * The address VAT number is passed in rather than looked up, so the order path and the checkout
     * path can each supply it from whatever address object they hold.
     *
     * @param int $customerId
     * @param string $addressVatNumber VAT number of the delivery address, empty when unavailable.
     *
     * @return string
     */
    public function resolveReceiverTaxId($customerId, $addressVatNumber)
    {
        $source = ($this->customsMapping !== null && !empty($this->customsMapping->mappingReceiverTaxId))
            ? $this->customsMapping->mappingReceiverTaxId
            : CustomsMappingService::SOURCE_CUSTOMER_TAX_ID;

        if ($source === CustomsMappingService::SOURCE_ADDRESS_VAT) {
            return (string)$addressVatNumber;
        }

        return self::getCustomerTaxId((int)$customerId);
    }

    /**
     * Returns the tariff-number source selected in the customs mapping (mapping_tariff_number);
     * defaults to the product HS code field.
     *
     * @return string
     */
    private function tariffNumberSource()
    {
        return ($this->customsMapping !== null && !empty($this->customsMapping->mappingTariffNumber))
            ? $this->customsMapping->mappingTariffNumber
            : CustomsMappingService::SOURCE_PRODUCT_HS_CODE;
    }

    /**
     * Source configured for the item country of origin, defaulting to the module's own product field.
     *
     * @return string
     */
    private function countryOfOriginSource()
    {
        return ($this->customsMapping !== null && !empty($this->customsMapping->mappingCountryOfOrigin))
            ? $this->customsMapping->mappingCountryOfOrigin
            : CustomsMappingService::SOURCE_PRODUCT_COUNTRY_OF_ORIGIN;
    }

    /**
     * Returns the module-owned customs data for a product, preferring the preloaded cache.
     *
     * @param int $productId
     *
     * @return ProductCustomsData|null
     */
    private function productCustomsData($productId)
    {
        $productId = (int)$productId;

        if (is_array($this->productCustomsCache)) {
            return isset($this->productCustomsCache[$productId]) ? $this->productCustomsCache[$productId] : null;
        }

        // Fallback single lookup for callers outside a preloaded build.
        return self::getProductCustomsData($productId);
    }

    /**
     * Returns the stored customs data for a product, or null when none exists.
     *
     * @param int $productId
     *
     * @return ProductCustomsData|null
     */
    public static function getProductCustomsData($productId)
    {
        try {
            $repository = RepositoryRegistry::getRepository(ProductCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('productId', '=', (int)$productId);

            /** @var ProductCustomsData|null $data */
            $data = $repository->selectOne($query);

            return $data;
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read customs data for product ' . (int)$productId . ': ' . $e->getMessage(),
                'Integration'
            );

            return null;
        }
    }

    /**
     * Returns the stored private-person tax id for a customer, or an empty string.
     *
     * @param int $customerId
     *
     * @return string
     */
    public static function getCustomerTaxId($customerId)
    {
        try {
            $repository = RepositoryRegistry::getRepository(CustomerCustomsData::CLASS_NAME);

            $query = new QueryFilter();
            $query->where('customerId', '=', (int)$customerId);

            /** @var CustomerCustomsData|null $data */
            $data = $repository->selectOne($query);

            return ($data !== null && !empty($data->taxId)) ? $data->taxId : '';
        } catch (\Exception $e) {
            Logger::logWarning(
                'Failed to read customs tax id for customer ' . (int)$customerId . ': ' . $e->getMessage(),
                'Integration'
            );

            return '';
        }
    }
}
