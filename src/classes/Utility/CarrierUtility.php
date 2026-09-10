<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\CashOnDelivery\Interfaces\CashOnDeliveryServiceInterface;
use Packlink\BusinessLogic\Http\DTO\CashOnDelivery;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;

class CarrierUtility
{
    /**
     * Gets all carrier IDs that require drop-off.
     *
     * @var int $carrierReference
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public static function getServiceFromReferenceId($carrierReference)
    {
        $carrierService = new CarrierService();
        return $carrierService->getShippingMethodId((int)$carrierReference);
    }
    /**
     * Gets all carrier IDs that require drop-off.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public static function getDropOffCarrierReferenceIds()
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $query = new QueryFilter();
        $query->where('enabled', Operators::EQUALS, true)
            ->where('destinationDropOff', Operators::EQUALS, true);

        $methods = $repository->select($query);

        $result = array();
        $service = new CarrierService();

        /** @var ShippingMethod $method */
        foreach ($methods as $method) {
            // Both the base and the duties-paid carrier of a drop-off method require the drop-off picker.
            foreach ($service->getCarrierReferenceIds($method->getId()) as $carrierReferenceId) {
                if ($carrierReferenceId) {
                    $carrier = \Carrier::getCarrierByReference($carrierReferenceId);
                    if (\Validate::isLoadedObject($carrier)) {
                        $result[$carrier->id] = $method->getId();
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Gets all carrier IDs that require drop-off.
     *
     * @param float $cartTotal
     * @param CashOnDelivery $cod
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public static function getCashOnDeliveryReferenceIds($cartTotal, $cod)
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $query = new QueryFilter();
        $methods = $repository->select($query);

        $result = array();
        $service = new CarrierService();

        /** @var ShippingMethod $method */
        foreach ($methods as $method) {
            // The surcharge belongs to the method, so it applies to its duties-paid carrier as well.
            $carrierReferenceIds = $service->getCarrierReferenceIds($method->getId());

            $services = $method->getShippingServices();

            foreach ($services as $shippingService) {
                if($shippingService->cashOnDeliveryConfig &&  $shippingService->cashOnDeliveryConfig->offered) {
                    if($cod->account->getCashOnDeliveryFee() !== null) {
                        $fee = $cod->account->getCashOnDeliveryFee();
                    } else {
                        $fee = self::calculateFee(
                            $cartTotal, $shippingService->cashOnDeliveryConfig->applyPercentageCashOnDelivery,
                            $shippingService->cashOnDeliveryConfig->maxCashOnDelivery);
                    }

                    foreach ($carrierReferenceIds as $carrierReferenceId) {
                        $result[$carrierReferenceId] = $fee;
                    }

                    break;
                }
            }

        }

        return $result;
    }

    /**
     * Checks whether carrier with provided reference is a drop-off or not.
     *
     * @param int $carrierReference
     *
     * @return bool
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public static function isDropOff($carrierReference)
    {
        $service = new CarrierService();
        $id = $service->getShippingMethodId($carrierReference);

        if ($id === null) {
            return false;
        }

        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $filter = new QueryFilter();
        $filter->where('id', Operators::EQUALS, $id);
        /** @var ShippingMethod $method */
        $method = $repository->selectOne($filter);

        return $method && $method->isDestinationDropOff();
    }


    public static function getCashOnDeliveryConfig()
    {
        /** @var CashOnDeliveryServiceInterface $codService */
        $codService = ServiceRegister::getService(CashOnDeliveryServiceInterface::CLASS_NAME);
        try {
            return $codService->getCashOnDeliveryConfig();
        } catch (QueryFilterInvalidParamException $e) {
            return null;
        }
    }

    /**
     * Calculate COD surcharge fee if it is not set in the configuration than use from api.
     *
     * @param float $orderTotal Total order amount
     * @param float $percentage Percentage fee
     * @param float $minFee Minimum fee
     *
     * @return float COD surcharge
     * @throws QueryFilterInvalidParamException
     */
    private static function calculateFee($orderTotal, $percentage, $minFee)
    {
        $calculated = round($orderTotal * ($percentage / 100), 2);

        if ($calculated < $minFee) {
            return $minFee;
        }

        return $calculated;
    }
}
