<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Customs\Models\MappingFieldOptions;
use Packlink\BusinessLogic\Customs\Models\TaxIdOption;
use Packlink\PrestaShop\Classes\Utility\ProductFeatureSources;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class CustomsMappingService.
 *
 * PrestaShop implementation of the core abstract customs mapping service. It supplies, for each
 * customs field the merchant can map, the list of PrestaShop data sources it may be filled from.
 * Core neither hardcodes nor consumes these mappings; the platform both offers them here and honors
 * the merchant's selection when building the order (see ShopOrderService).
 *
 * Three fields are offered: the item tariff number (HS code), the item country of origin, and the
 * receiver tax id. The two product fields can be filled either from the dedicated fields this module
 * adds to the product Shipping tab, or from any product feature the merchant created themselves -
 * so a merchant who already keeps the HS code in a feature named "Tariff code" maps it directly
 * instead of re-entering it.
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CustomsMappingService extends \Packlink\BusinessLogic\Customs\CustomsMappingService
{
    /**
     * Data-source keys the merchant can map customs fields to. Product feature sources are keyed
     * dynamically by ProductFeatureSources (feature_<id>).
     */
    const SOURCE_CUSTOMER_TAX_ID = 'tax_id';
    const SOURCE_ADDRESS_VAT = 'vat_number';
    const SOURCE_PRODUCT_HS_CODE = 'product_hs_code';
    const SOURCE_PRODUCT_COUNTRY_OF_ORIGIN = 'product_country_of_origin';

    /**
     * Returns the PrestaShop sources the receiver tax id can be filled from.
     *
     * Required by the core CustomsMappingService contract and served by the core CustomsController.
     * Built from the same two sources as the 'mapping_receiver_tax_id' entry in
     * getMappingFieldsOptions(), so the settings page and this endpoint cannot drift apart.
     *
     * @return TaxIdOption[]
     */
    public function getReceiverTaxIdOptions()
    {
        return array(
            TaxIdOption::fromArray(array(
                'value' => self::SOURCE_CUSTOMER_TAX_ID,
                'name' => TranslationUtility::__('Customer tax ID'),
            )),
            TaxIdOption::fromArray(array(
                'value' => self::SOURCE_ADDRESS_VAT,
                'name' => TranslationUtility::__('Company VAT number'),
            )),
        );
    }

    /**
     * Returns the data-mapping field definitions rendered on the customs settings page. Each entry
     * targets a CustomsMapping field and lists the PrestaShop sources it can be filled from.
     *
     * @return MappingFieldOptions[]
     */
    public function getMappingFieldsOptions()
    {
        $featureOptions = ProductFeatureSources::getOptions(ProductFeatureSources::resolveLanguageId());

        return array(
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_tariff_number',
                'label' => TranslationUtility::__('Tariff number (HS code)'),
                'options' => array_merge(
                    array(
                        array(
                            'value' => self::SOURCE_PRODUCT_HS_CODE,
                            'name' => TranslationUtility::__('HS code'),
                        ),
                    ),
                    $featureOptions
                ),
            )),
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_country_of_origin',
                'label' => TranslationUtility::__('Country of origin'),
                'options' => array_merge(
                    array(
                        array(
                            'value' => self::SOURCE_PRODUCT_COUNTRY_OF_ORIGIN,
                            'name' => TranslationUtility::__('Country of origin'),
                        ),
                    ),
                    $featureOptions
                ),
            )),
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_receiver_tax_id',
                'label' => TranslationUtility::__('Receiver tax ID / VAT number'),
                'options' => array(
                    array(
                        'value' => self::SOURCE_CUSTOMER_TAX_ID,
                        'name' => TranslationUtility::__('Customer tax ID'),
                    ),
                    array(
                        'value' => self::SOURCE_ADDRESS_VAT,
                        'name' => TranslationUtility::__('Company VAT number'),
                    ),
                ),
            )),
        );
    }
}
