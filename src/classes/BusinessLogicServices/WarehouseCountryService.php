<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Context;
use Country;
use Packlink\BusinessLogic\Country\WarehouseCountryService as BaseService;

/**
 * Class WarehouseCountryService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class WarehouseCountryService extends BaseService
{
    /**
     * Returns a list of supported country DTOs.
     *
     * @param bool $associative Indicates whether the result should be an associative array.
     *
     * @return \Packlink\BusinessLogic\Country\Country[]
     *
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoNotRegisteredException
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
     */
    public function getSupportedCountries($associative = true)
    {
        $countries = $this->getBrandConfigurationService()->get()->warehouseCountries;
        $activeCountries = Country::getCountries((int)Context::getContext()->language->id, true);
        $intersectedCountries = array();

        foreach ($activeCountries as $activeCountry) {
            if (array_key_exists($activeCountry['iso_code'], $countries)) {
                $intersectedCountries[] = $countries[$activeCountry['iso_code']];
            }
        }

        $result = $this->formatCountries($intersectedCountries);

        return $associative ? $result : array_values($result);
    }
}
