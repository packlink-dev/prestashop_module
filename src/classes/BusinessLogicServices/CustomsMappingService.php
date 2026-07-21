<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Customs\Models\MappingFieldOptions;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class CustomsMappingService.
 *
 * PrestaShop implementation of the core abstract customs mapping service. It supplies, for each
 * customs field the merchant can map, the list of PrestaShop data sources it may be filled from.
 * Core neither hardcodes nor consumes these mappings; the platform both offers them here and honors
 * the merchant's selection when building the order (see ShopOrderService).
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CustomsMappingService extends \Packlink\BusinessLogic\Customs\CustomsMappingService
{
    /**
     * Data-source keys the merchant can map customs fields to.
     */
    const SOURCE_CUSTOMER_TAX_ID = 'tax_id';
    const SOURCE_ADDRESS_VAT = 'vat_number';
    const SOURCE_PRODUCT_HS_CODE = 'product_hs_code';

    /**
     * Returns the data-mapping field definitions rendered on the customs settings page. Each entry
     * targets a CustomsMapping field and lists the PrestaShop sources it can be filled from.
     *
     * @return MappingFieldOptions[]
     */
    public function getMappingFieldsOptions()
    {
        return array(
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_receiver_tax_id',
                'label' => TranslationUtility::__('Receiver Tax ID'),
                'options' => array(
                    array(
                        'value' => self::SOURCE_CUSTOMER_TAX_ID,
                        'name' => TranslationUtility::__('Customer Tax ID'),
                    ),
                    array(
                        'value' => self::SOURCE_ADDRESS_VAT,
                        'name' => TranslationUtility::__('Company VAT (address)'),
                    ),
                ),
            )),
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_company_vat',
                'label' => TranslationUtility::__('Company VAT'),
                'options' => array(
                    array(
                        'value' => self::SOURCE_ADDRESS_VAT,
                        'name' => TranslationUtility::__('Company VAT (address)'),
                    ),
                ),
            )),
            MappingFieldOptions::fromArray(array(
                'field' => 'mapping_tariff_number',
                'label' => TranslationUtility::__('Tariff number (HS code)'),
                'options' => array(
                    array(
                        'value' => self::SOURCE_PRODUCT_HS_CODE,
                        'name' => TranslationUtility::__('Product HS code'),
                    ),
                ),
            )),
        );
    }
}
