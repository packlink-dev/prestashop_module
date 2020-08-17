<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Registration\RegistrationInfoService as RegistrationInfoServiceInterface;
use Packlink\BusinessLogic\Registration\RegistrationInfo;
use PrestaShop\PrestaShop\Adapter\Entity\ShopUrl;

/**
 * Class RegistrationInfoService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class RegistrationInfoService implements RegistrationInfoServiceInterface
{
    /**
     * Returns registration data from the integration.
     *
     * @return RegistrationInfo
     */
    public function getRegistrationInfoData()
    {
        $data = $this->getRegistrationData();

        return new RegistrationInfo($data['email'], $data['phone'], $data['source']);
    }

    /**
     * Returns registration data from PrestaShop.
     *
     * @return array
     */
    private function getRegistrationData()
    {
        $result = array();

        $result['email'] = \Context::getContext()->employee->email;
        $result['phone']  =  '';
        $result['source'] = ShopUrl::getMainShopDomain() . \Context::getContext()->shop->physical_uri;

        return $result;
    }
}
