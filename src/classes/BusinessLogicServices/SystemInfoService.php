<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Packlink\BusinessLogic\Http\DTO\SystemInfo;
use Packlink\BusinessLogic\SystemInformation\SystemInfoService as SystemInfoInterface;

/**
 * Class SystemInfoService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class SystemInfoService implements SystemInfoInterface
{
    /**
     * Returns system information.
     *
     * @return SystemInfo[]
     */
    public function getSystemDetails()
    {
        $shopIds = \Shop::getCompleteListOfShopsID();
        $systemInfos = array();

        foreach ($shopIds as $shopId) {
            $systemInfos[] = $this->getSystemInfo($shopId);
        }

        return $systemInfos;
    }

    /**
     * Returns system information for a particular system, identified by the system ID.
     *
     * @param string $systemId
     *
     * @return SystemInfo|null
     */
    public function getSystemInfo($systemId)
    {
        $shop = \Shop::getShop($systemId);

        if ($shop) {
            return SystemInfo::fromArray(array(
                'system_id' => $systemId,
                'system_name' => $shop['name'],
                'currencies' => $this->getCurrencies($systemId),
            ));
        }

        return null;
    }

    /**
     * Returns a list of supported shop currencies.
     *
     * @param string $systemId
     *
     * @return array
     */
    private function getCurrencies($systemId)
    {
        $currencies = \Currency::getCurrenciesByIdShop($systemId);
        $currencyCodes = array();
        foreach ($currencies as $currency) {
            if ($currency['active'] && $currency['conversion_rate'] === 1) {
                $currencyCodes[] = $currency['iso_code'];
            }
        }

        return $currencyCodes;
    }
}
