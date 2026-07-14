<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Customs\Models\TaxIdOption;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class CustomsMappingService.
 *
 * PrestaShop implementation of the core abstract customs mapping service. It supplies the option
 * list the merchant can map the customs receiver tax id to. Loading/saving the CustomsMapping and
 * seeding the sender tax id from the Packlink user are handled by the core abstract class.
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class CustomsMappingService extends \Packlink\BusinessLogic\Customs\CustomsMappingService
{
    /**
     * Options the merchant can map the receiver tax id to:
     *  - the module-owned customer "Tax ID" field ([[CustomerCustomsData]]);
     *  - the native PrestaShop Address VAT number (company VAT).
     *
     * @return TaxIdOption[]
     */
    public function getReceiverTaxIdOptions()
    {
        return array(
            TaxIdOption::fromArray(array(
                'value' => 'tax_id',
                'name' => TranslationUtility::__('Tax ID'),
            )),
            TaxIdOption::fromArray(array(
                'value' => 'vat_number',
                'name' => TranslationUtility::__('Company VAT'),
            )),
        );
    }
}
