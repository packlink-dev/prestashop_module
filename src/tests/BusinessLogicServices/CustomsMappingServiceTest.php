<?php

namespace Packlink\PrestaShop\Tests\BusinessLogicServices;

use Packlink\PrestaShop\Classes\BusinessLogicServices\CustomsMappingService;
use PHPUnit\Framework\TestCase;

/**
 * Class CustomsMappingServiceTest.
 *
 * Guards the data-mapping field definitions the module offers on the customs settings page: which
 * customs fields are mappable and which PrestaShop sources each may be filled from.
 *
 * @package Packlink\PrestaShop\Tests\BusinessLogicServices
 */
class CustomsMappingServiceTest extends TestCase
{
    public function testMappingFieldsOptions()
    {
        $service = new CustomsMappingService();

        $valuesByField = array();
        foreach ($service->getMappingFieldsOptions() as $fieldOptions) {
            $values = array();
            foreach ($fieldOptions->options as $option) {
                $values[] = $option->value;
            }
            $valuesByField[$fieldOptions->field] = $values;
        }

        $this->assertArrayHasKey('mapping_receiver_tax_id', $valuesByField);
        $this->assertContains('tax_id', $valuesByField['mapping_receiver_tax_id']);
        $this->assertContains('vat_number', $valuesByField['mapping_receiver_tax_id']);

        $this->assertArrayHasKey('mapping_company_vat', $valuesByField);
        $this->assertContains('vat_number', $valuesByField['mapping_company_vat']);

        $this->assertArrayHasKey('mapping_tariff_number', $valuesByField);
        $this->assertContains('product_hs_code', $valuesByField['mapping_tariff_number']);
    }
}
