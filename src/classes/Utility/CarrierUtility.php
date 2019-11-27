<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;

class CarrierUtility
{
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
            $carrierReferenceId = $service->getCarrierReferenceId($method->getId());
            if ($carrierReferenceId) {
                $carrier = \Carrier::getCarrierByReference($carrierReferenceId);
                if (\Validate::isLoadedObject($carrier)) {
                    $result[$carrier->id] = $method->getId();
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
}
