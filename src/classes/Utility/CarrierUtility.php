<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Packlink\BusinessLogic\ShippingMethod\Models\ShippingMethod;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;

class CarrierUtility
{
    /**
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public static function getDropOffCarrierReferenceIds()
    {
        $repository = RepositoryRegistry::getRepository(ShippingMethod::getClassName());
        $query = new QueryFilter();
        $query->where('enabled', '=', true)
            ->where('destinationDropOff', '=', true);

        $methods = $repository->select($query);

        $result = array();

        $service = new CarrierService();

        /** @var ShippingMethod $method */
        foreach ($methods as $method) {
            $id = $service->getCarrierReferenceId($method->getServiceId());
            if ($id) {
                $result[$id] = $method->getServiceId();
            }
        }

        return $result;
    }
}
