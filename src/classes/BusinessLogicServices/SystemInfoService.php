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
     *
     * @throws \PrestaShopDatabaseException
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
     *
     * @throws \PrestaShopDatabaseException
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
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getCurrencies($systemId = null)
    {
        if ($systemId === null) {
            return array();
        }

        $currencies = $this->getCurrenciesForShop($systemId);
        $currencyCodes = array();
        foreach ($currencies as $currency) {
            if ($currency['active'] && (float)$currency['conversion_rate'] === 1.0) {
                $currencyCodes[] = $currency['iso_code'];
            }
        }

        return $currencyCodes;
    }

    /**
     * Returns currency for the provided system.
     *
     * NOTE: This had to be implemented since the PrestaShop's method that does this
     * has a bug in some versions which always returns currencies only for the current system.
     *
     * @param string $systemId
     *
     * @return array|bool|\mysqli_result|\PDOStatement|resource|null
     *
     * @throws \PrestaShopDatabaseException
     */
    private function getCurrenciesForShop($systemId)
    {
        return \Db::getInstance()->executeS('
		SELECT *
		FROM `' . _DB_PREFIX_ . 'currency` c
		LEFT JOIN `' . _DB_PREFIX_ . 'currency_shop` cs ON (cs.`id_currency` = c.`id_currency`)
        ' . ($systemId ? ' WHERE cs.`id_shop` = ' . (int)$systemId : '') . '
		ORDER BY `iso_code` ASC');
    }
}
