<?php

use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\RepositoryRegistry;
use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Location\LocationService;
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
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function postProcess()
    {
        $input = PacklinkPrestaShopUtility::getPacklinkPostData();

        $addressId = !empty($input['addressId']) ? $input['addressId'] : null;

        switch ($input['method']) {
            case 'getLocations':
                PacklinkPrestaShopUtility::dieJson($this->getLocations($input['methodId'], $addressId));
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
     * @param int $addressId
     *
     * @return array
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function getLocations($methodId, $addressId = null)
    {
        if (!$addressId) {
            $addressId = empty($this->context->cart->id_address_delivery) ? null
                : $this->context->cart->id_address_delivery;
        }

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

        /** @var \Packlink\BusinessLogic\Location\LocationService $locationService */
        $locationService = ServiceRegister::getService(LocationService::CLASS_NAME);

        try {
            return $locationService->getLocations($methodId, $countryCode, $postalCode, $this->getCartPackages());
        } catch (\InvalidArgumentException $e) {
            return array();
        }
    }

    /**
     * Creates mapping.
     *
     * @param $data
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createMapping($data)
    {
        if (!empty($data['cartId'])) {
            $cartId = $data['cartId'];
            $this->updateAddress($data['orderId'], $data['dropOff']);
        } else {
            $cartId = empty($this->context->cart->id) ? null : (string)$this->context->cart->id;
        }

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
     * Gets packages out of cart products.
     *
     * @return \Packlink\BusinessLogic\Http\DTO\Package[]
     */
    protected function getCartPackages()
    {
        $shippingProducts = array();
        if (!empty($this->context->cart)) {
            $products = $this->context->cart->getProducts();
            if (!empty($products)) {
                foreach ($this->context->cart->getProducts() as $product) {
                    if (!$product['is_virtual']) {
                        $shippingProducts[] = $product;
                    }
                }
            }
        }

        return CachingUtility::getPackages($shippingProducts);
    }

    /**
     * Updates order address.
     *
     * @param int $orderId
     * @param array $dropOff
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function updateAddress($orderId, $dropOff)
    {
        $order = new \Order($orderId);
        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $address = new \Address($order->id_address_delivery);
        if (!Validate::isLoadedObject($address)) {
            return;
        }

        $address->address1 = $dropOff['address'];
        $address->postcode = $dropOff['zip'];
        $address->city = $dropOff['city'];
        $address->company = $dropOff['name'];
        if (method_exists($this, 'l')) {
            $address->alias = $this->l('Drop-Off delivery address');
            $address->other = $this->l('Drop-Off delivery address');
        } else {
            $address->alias = 'Drop-Off delivery address';
            $address->other = 'Drop-Off delivery address';
        }

        $address->update();
    }
}
