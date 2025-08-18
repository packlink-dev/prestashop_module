<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Module;
use PaymentModule;
use Validate;

/**
 * Class OfflinePaymentService
 *
 * @package Packlink\PrestaShop\Classes\BusinessLogicServices
 */
class OfflinePaymentService
{
    /**
     * Offline payment methods
     *
     * @var string[]
     */
    protected $knownOffline = array(
        'ps_wirepayment',
        'ps_checkpayment',
        'ps_cashondelivery',
    );

    /**
     * @return array
     */
    public function getOfflinePayments()
    {
        $offlinePayments = array();

        foreach (PaymentModule::getInstalledPaymentModules() as $payment) {
            $module = Module::getInstanceByName($payment['name']);

            if (!Validate::isLoadedObject($module) || !$module->active) {
                continue;
            }

            if ($this->isOffline($module->name)) {
                $offlinePayments[] = [
                    'name' => $module->name,
                    'displayName' => $module->displayName,
                ];
            }
        }

        return $offlinePayments;
    }

    /**
     * @param string $moduleName
     *
     * @return bool
     */
    protected function isOffline($moduleName)
    {
        return in_array($moduleName, $this->knownOffline, true);
    }
}