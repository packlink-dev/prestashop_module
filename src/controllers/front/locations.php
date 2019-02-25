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

use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Http\DTO\BaseDto;
use Packlink\BusinessLogic\Http\Proxy;
use Packlink\BusinessLogic\ShippingMethod\ShippingCostCalculator;
use Packlink\BusinessLogic\ShippingMethod\ShippingMethodService;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping;
use Packlink\PrestaShop\Classes\Utility\CachingUtility;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

class PacklinkLocationsModuleFrontController extends ModuleFrontController
{
    /**
     * PacklinkLocationsModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();
    }

    /**
     * Responds to ajax request.
     *
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpAuthenticationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpCommunicationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpRequestException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function postProcess()
    {
        $input = PacklinkPrestaShopUtility::getPacklinkPostData();

        switch ($input['method']) {
            case 'getLocations':
                PacklinkPrestaShopUtility::dieJson($this->getLocations($input['methodId']));
                break;

            case 'postSelectedDropoff':
                $this->createMapping($input);
                PacklinkPrestaShopUtility::dieJson();
                break;

            default:
                PacklinkPrestaShopUtility::die400();
        }
    }

    /**
     * Retrieves locations list.
     *
     * @param string $methodId
     *
     * @return array
     *
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpAuthenticationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpCommunicationException
     * @throws \Logeecom\Infrastructure\Http\Exceptions\HttpRequestException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function getLocations($methodId)
    {
        /** @var Proxy $proxy */
        $proxy = ServiceRegister::getService(Proxy::CLASS_NAME);

        $addressId = empty($this->context->cart->id_address_delivery) ? null
            : $this->context->cart->id_address_delivery;

        if (!$addressId) {
            return array();
        }

        $address = new Address($addressId);

        if (!Validate::isLoadedObject($address)) {
            return array();
        }

        $country = new \Country($address->id_country);

        if (!Validate::isLoadedObject($country)) {
            return array();
        }

        $countryCode = \Tools::strtoupper($country->iso_code);
        $postalCode = $address->postcode;

        $warehouse = CachingUtility::getDefaultWarehouse();

        /** @var ShippingMethodService $shippingMethodService */
        $shippingMethodService = ServiceRegister::getService(ShippingMethodService::CLASS_NAME);
        $method = $shippingMethodService->getShippingMethod($methodId);
        if ($method === null) {
            return array();
        }

        try {
            $cheapestService = ShippingCostCalculator::getCheapestShippingService(
                $method,
                $warehouse->country,
                $warehouse->postalCode,
                $countryCode,
                $postalCode
            );
        } catch (\InvalidArgumentException $e) {
            return array();
        }

        $locations = $proxy->getLocations($cheapestService->serviceId, $countryCode, $postalCode);

        return $this->transformCollectionToResponse($locations);
    }

    /**
     * Creates mapping.
     *
     * @param $data
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    protected function createMapping($data)
    {
        $cartId = empty($this->context->cart->id) ? null : (string)$this->context->cart->id;

        if (!$cartId) {
            return;
        }

        $repository = RepositoryRegistry::getRepository(CartCarrierDropOffMapping::getClassName());
        $query = new QueryFilter();
        $query->where('cartId', '=', $cartId)
            ->where('carrierReferenceId', '=', $data['carrierId']);

        /** @var CartCarrierDropOffMapping $mapping */
        $mapping = $repository->selectOne($query);

        if ($mapping === null) {
            $mapping = new CartCarrierDropOffMapping();
            $mapping->setCarrierReferenceId($data['carrierId']);
            $mapping->setCartId($cartId);
            $mapping->setDropOff($data['dropOff']);
            $repository->save($mapping);
        } else {
            $mapping->setDropOff($data['dropOff']);
            $repository->update($mapping);
        }
    }

    /**
     * Transforms collection of DTO's to an array response.
     *
     * @param BaseDto[] $collection
     *
     * @return array
     */
    protected function transformCollectionToResponse($collection)
    {
        $result = array();

        foreach ($collection as $element) {
            $result[] = $element->toArray();
        }

        return $result;
    }
}
