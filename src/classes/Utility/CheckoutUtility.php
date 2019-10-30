<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;

class CheckoutUtility
{
    /**
     * Checks whether if drop-off is selected.
     *
     * @param string $cartId
     *
     * @param $carrierId
     *
     * @return bool
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public static function isDropOffSelected($cartId, $carrierId)
    {
        $repository = RepositoryRegistry::getRepository(CartCarrierDropOffMapping::getClassName());

        $query = new \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter();
        $query->where('cartId', '=', $cartId)
            ->where('carrierReferenceId', '=', $carrierId);

        return $repository->selectOne($query) !== null;
    }
}