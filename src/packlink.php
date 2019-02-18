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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/packlink/vendor/autoload.php';

/**
 * @property bool bootstrap
 * @property string module_key
 * @property string name
 * @property string tab
 * @property string version
 * @property string author
 * @property int need_instance
 * @property array ps_versions_compliancy
 * @property string displayName
 * @property string description
 * @property \ContextCore context
 * @property string local_path
 * @method string display($file, $template, $cache_id = null, $compile_id = null)
 * @method unregisterHook($string)
 */
class Packlink extends CarrierModule
{
    const PACKLINK_SHIPPING_TAB = 'packlink/views/templates/admin/packlink_shipping_tab/shipping_tab.tpl';
    const PACKLINK_SHIPPING_CONTENT = 'packlink/views/templates/admin/packlink_shipping_content/shipping_content.tpl';
    const PRESTASHOP_ORDER_CREATED_STATUS = 0;
    const PRESTASHOP_PAYMENT_ACCEPTED_STATUS = 2;
    /**
     * Id of current carrier. This variable will be set by Presta when
     * method for calculating shipping cost is called.
     * @var int
     */
    public $id_carrier;
    /**
     * List of help URLs for different country codes.
     *
     * @var array
     */
    private static $helpUrls = array(
        'ES' => 'https://support-pro.packlink.com/hc/es-es/sections/202755109-Prestashop',
        'DE' => 'https://support-pro.packlink.com/hc/de/sections/202755109-Prestashop',
        'FR' => 'https://support-pro.packlink.com/hc/fr-fr/sections/202755109-Prestashop',
        'IT' => 'https://support-pro.packlink.com/hc/it/sections/202755109-Prestashop',
    );
    /**
     * List of terms and conditions URLs for different country codes.
     *
     * @var array
     */
    private static $termsAndConditionsUrls = array(
        'ES' => 'https://pro.packlink.es/terminos-y-condiciones/',
        'DE' => 'https://pro.packlink.de/agb/',
        'FR' => 'https://pro.packlink.fr/conditions-generales/',
        'IT' => 'https://pro.packlink.it/termini-condizioni/',
    );

    /**
     * Packlink constructor.
     */
    public function __construct()
    {
        $this->module_key = '0b685e39fafb6de6bd21daaa455f4404';
        $this->name = 'packlink';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0.0';
        $this->author = $this->l('Packlink Shipping S.L.');
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Packlink PRO Shipping');
        $this->description = $this->l('Save up to 70% on your shipping costs. No fixed fees, no minimum shipping volume required. Manage all your shipments in a single platform.');

        $this->context = \Context::getContext();
    }

    /**
     * Handle plugin installation
     *
     * @return bool
     *
     * @throws \PrestaShopException
     */
    public function install()
    {
        $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($this);
        $previousShopContext = Shop::getContext();
        Shop::setContext(Shop::CONTEXT_ALL);

        $result = $installer->initializePlugin() && parent::install() && $installer->addControllersAndHooks();

        Shop::setContext($previousShopContext);

        return $result;
    }

    /**
     * Handle plugin uninstall
     *
     * @return bool
     */
    public function uninstall()
    {
        $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($this);

        return $installer->uninstall() && parent::uninstall() && $installer->removeHooks();
    }

    /**
     * Hook used to insert content before carriers have been loaded.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function hookDisplayBeforeCarrier($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return $this->getPresta16ShippingStepPage($params);
        }

        $shippingServicePath = $this->_path . 'views/js/ShippingService17.js';
        $output = "<script src=\"{$shippingServicePath}\"></script>" . "\n";

        $ajaxPath = $this->_path . 'views/js/PrestaAjaxService.js';
        $output .= "<script src=\"{$ajaxPath}\"></script>" . "\n";

        $stylePath = $this->_path . 'views/css/checkout.css';
        $output .= "<link rel=\"stylesheet\" href=\"{$stylePath}\"/>" . "\n";

        $checkoutPath = $this->_path . 'views/js/CheckOutController.js';
        $output .= "<script src=\"{$checkoutPath}\"></script>" . "\n";

        $mapModalPath = $this->_path . 'views/js/MapModalController.js';
        $output .= "<script src=\"{$mapModalPath}\"></script>" . "\n";

        return $output;
    }

    /**
     * Hook used to insert html and javascript after carriers have been loaded on frontend.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function hookDisplayAfterCarrier($params)
    {
        $configuration = $this->getShippingStepConfiguration($params);

        $this->context->smarty->assign(array(
            'configuration' => json_encode($configuration),
        ));

        return $this->display(__FILE__, 'shipping_methods_17.tpl');
    }

    /**
     * Hooks on tab display to add shipping tab.
     *
     * @return string Rendered template output.
     */
    public function hookDisplayAdminOrderTabShip()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        if (!$this->moduleFullyConfigured()) {
            return '';
        }

        return $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_SHIPPING_TAB,
            $this->context->smarty
        )->fetch();
    }

    /**
     * Hooks on content display to add Packlink shipping content.
     *
     * @return string Rendered template output.
     *
     * @throws \Exception
     */
    public function hookDisplayAdminOrderContentShip()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        if (!$this->moduleFullyConfigured()) {
            return '';
        }

        $orderId = (int)Tools::getValue('id_order');

        $this->prepareShippingTabData($orderId);

        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'packlink/views/js/PrestaCreateOrderDraft.js');
        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'packlink/views/js/PrestaAjaxService.js');

        return $this->context->smarty->createTemplate(
            _PS_MODULE_DIR_ . self::PACKLINK_SHIPPING_CONTENT,
            $this->context->smarty
        )->fetch();
    }

    /**
     * Frontend hook for order creation.
     *
     * @param array $params Hook parameters.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function hookDisplayOrderConfirmation($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Order $order */
        $order = $params['order'] ?: $params['objOrder'];

        $repository = \Logeecom\Infrastructure\ORM\RepositoryRegistry::getRepository(
            \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping::getClassName()
        );

        $query = new \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter();
        $query->where('cartId', '=', (string)$order->id_cart)
            ->where('carrierReferenceId', '=', (string)$order->id_carrier);

        /** @var \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping */
        $mapping = $repository->selectOne($query);

        if ($mapping) {
            $this->createDropOffAddress($order, $mapping);
        }

        $this->createOrderDraft($order, $order->getCurrentOrderState());
    }

    /**
     * Backend hook for order creation.
     *
     * @param array $params Hook parameters.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function hookActionValidateOrder($params)
    {
        $this->createOrderDraft($params['order'], $params['orderStatus']);
    }

    /**
     * Hook that is triggered when order status is updated on backend.
     *
     * @param $params
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function hookActionOrderStatusUpdate($params)
    {
        $order = new \Order((int)$params['id_order']);

        // If order has just been created, this hook should not handle that event
        // since it has already been handled by hook for validating order.
        if ((int)$order->current_state !== self::PRESTASHOP_ORDER_CREATED_STATUS
            && array_key_exists('newOrderStatus', $params)
        ) {
            $this->createOrderDraft($order, $params['newOrderStatus']);
        }
    }

    /**
     * Front Methods
     *
     * If you set need_range at true when you created your carrier,
     * the method called by the cart will be getOrderShippingCost.
     * If not, the method called will be getOrderShippingCostExternal
     *
     * $params var contains the cart, the customer, the address
     * $shipping_cost var contains the price calculated by the range in carrier tab
     *
     * @param Cart $params Shopping cart object.
     * @param int $shippingCost Shipping cost calculated by PrestaShop.
     * @param array $products Array of shop products for which shipping cost is calculated.
     *
     * @return float|bool Calculated shipping cost if carrier is available, otherwise FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function getPackageShippingCost($params, $shippingCost, $products)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService::CLASS_NAME
        );
        $carrier = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCarrier($this->id_carrier);
        $carrierReferenceId = (int)$carrier->id_reference;
        $serviceId = $carrierService->getShippingServiceId($carrierReferenceId);

        if ($serviceId === null) {
            return false;
        }

        $calculatedCosts = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCosts();
        if ($calculatedCosts !== false) {
            return isset($calculatedCosts[$serviceId]) ? $calculatedCosts[$serviceId] : false;
        }

        $warehouse = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getDefaultWarehouse();
        if ($warehouse === null) {
            return false;
        }

        $toCountry = $warehouse->country;
        $toZip = $warehouse->postalCode;

        if (!empty($params->id_address_delivery)) {
            $deliveryAddress = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getAddress(
                (int)$params->id_address_delivery
            );
            $deliveryCountry = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCountry(
                (int)$deliveryAddress->id_country
            );

            $toCountry = $deliveryCountry->iso_code;
            $toZip = $deliveryAddress->postcode;
        }

        $parcels = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getParcels($products);

        /** @var \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService $shippingMethodService */
        $shippingMethodService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService::CLASS_NAME
        );

        $calculatedCosts = $shippingMethodService->getShippingCosts(
            $warehouse->country,
            $warehouse->postalCode,
            $toCountry,
            $toZip,
            $parcels
        );

        \Packlink\PrestaShop\Classes\Utility\CachingUtility::setCosts($calculatedCosts);

        return isset($calculatedCosts[$serviceId]) ? $calculatedCosts[$serviceId] : false;
    }

    /**
     * Required by base class.
     *
     * @param $params
     * @param $shipping_cost
     */
    public function getOrderShippingCost($params, $shipping_cost)
    {
    }

    /**
     * Required by base class.
     *
     * @param $params
     */
    public function getOrderShippingCostExternal($params)
    {
    }

    /**
     * Returns content.
     *
     * @return string
     *
     * @throws \PrestaShopException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function getContent()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        $output = '';

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );
        $apiToken = $configService->getAuthorizationToken();
        $loggedIn = false;

        if (!$apiToken && Tools::isSubmit('api_key')) {
            $loggedIn = $this->login();

            if (!$loggedIn) {
                $output .= $this->displayError(
                    \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__('API key was incorrect.')
                );
            }
        }

        /** @var \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup $wakeupService */
        $wakeupService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup::CLASS_NAME
        );
        $wakeupService->wakeup();

        if ($apiToken || $loggedIn) {
            return $this->renderConfigForm();
        }

        $this->context->controller->addCSS($this->_path . 'views/css/packlink.css', 'all');
        $this->context->controller->addCSS($this->_path . 'views/css/bootstrap-prestashop-ui-kit.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/prestashop-ui-kit.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/UtilityService.js');
        $this->context->controller->addJS($this->_path . 'views/js/PrestaFix.js');
        $this->context->controller->addJS($this->_path . 'views/js/Login.js');

        $this->context->smarty->assign(array(
            'iconPath' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/flags/',
            'loginImage' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/login.png',
            'loginIcon' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/logo-pl.svg',
        ));

        return $output . $this->display(__FILE__, 'login.tpl');
    }

    /**
     * Generates configuration for shipping step in checkout process.
     *
     * @param array $params
     *
     * @return array
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function getShippingStepConfiguration($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        $dropOffs = \Packlink\PrestaShop\Classes\Utility\CarrierUtility::getDropOffCarrierReferenceIds();
        $configuration = array(
            'dropoffIds' => $dropOffs,
            'getLocationsUrl' => $this->getFrontAction('locations'),
        );

        /** @var \Cart $cart */
        $cart = $params['cart'];

        if (!empty($dropOffs[(int)$cart->id_carrier])) {
            $repository = \Logeecom\Infrastructure\ORM\RepositoryRegistry::getRepository(
                \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping::getClassName()
            );

            $query = new \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter();
            $query->where('cartId', '=', (string)$cart->id)
                ->where('carrierReferenceId', '=', (string)$cart->id_carrier);

            /** @var \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping */
            $mapping = $repository->selectOne($query);

            if ($mapping) {
                $configuration['selectedLocation'] = $mapping->getDropOff();
                $configuration['selectedCarrier'] = $mapping->getCarrierReferenceId();
            }
        }

        return $configuration;
    }

    /**
     * Returns additional content that has to be injected in shipping step during checkout in PrestaShop 1.6.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    protected function getPresta16ShippingStepPage($params)
    {
        $configuration = $this->getShippingStepConfiguration($params);

        $this->context->smarty->assign(
            array('configuration' => json_encode($configuration))
        );

        $shippingServicePath = $this->_path . 'views/js/ShippingService16.js';
        $output = "<script src=\"{$shippingServicePath}\"></script>" . "\n";

        $ajaxPath = $this->_path . 'views/js/PrestaAjaxService.js';
        $output .= "<script src=\"{$ajaxPath}\"></script>" . "\n";

        $stylePath = $this->_path . 'views/css/checkout.css';
        $output .= "<link rel=\"stylesheet\" href=\"{$stylePath}\"/>" . "\n";

        $checkoutPath = $this->_path . 'views/js/CheckOutController.js';
        $output .= "<script src=\"{$checkoutPath}\"></script>" . "\n";

        $mapModalPath = $this->_path . 'views/js/MapModalController.js';
        $output .= "<script src=\"{$mapModalPath}\"></script>" . "\n";

        return $output . $this->display(__FILE__, 'shipping_methods_16.tpl');
    }

    /**
     * Creates dropoff address.
     *
     * @param \Order $order
     * @param \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createDropOffAddress(
        Order $order,
        \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping
    ) {
        $address = new Address();

        $db = \Db::getInstance();

        $dropOff = $mapping->getDropOff();

        $countryQuery = new DbQuery();
        $countryQuery->select('id_country')->from('country')->where("iso_code='{$dropOff['countryCode']}'");

        $countryResult = $db->executeS($countryQuery);

        if (!empty($countryResult[0]['id_country'])) {
            $address->id_country = (int)$countryResult[0]['id_country'];
        }

        if (!empty($dropOff['state'])) {
            $stateQuery = new DbQuery();
            $stateQuery->select('id_state')->from('state')->where("iso_code='{$dropOff['state']}'");

            $sateResult = $db->executeS($stateQuery);

            if (!empty($sateResult[0]['id_state'])) {
                $address->id_state = (int)$sateResult[0]['id_state'];
            }
        }

        $address->address1 = $dropOff['address'];
        $address->postcode = $dropOff['zip'];
        $address->city = $dropOff['city'];
        $address->firstname = $this->context->customer->firstname;
        $address->lastname = $this->context->customer->lastname;
        $address->phone = $this->getPhone($order);
        $address->alias = $this->l('Drop-Off delivery address');
        $address->other = $this->l('Drop-Off delivery address');

        if ($address->save()) {
            $order->id_address_delivery = $address->id;
            $order->save();
        }
    }

    /**
     * Checks if  Packlink authorization token, default parcel and default warehouse have been set in the shop.
     *
     * @return bool Returns TRUE if module has been fully configured, otherwise returns FALSE.
     */
    private function moduleFullyConfigured()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );

        $authToken = $configService->getAuthorizationToken();
        $defaultParcel = $configService->getDefaultParcel();
        $defaultWarehouse = $configService->getDefaultWarehouse();

        return $authToken && $defaultParcel && $defaultWarehouse;
    }

    /**
     * Retrieves customers phone.
     *
     * @param \Order $order
     *
     * @return string
     */
    private function getPhone(\Order $order)
    {
        $phone = '';

        $shippingAddress = new Address($order->id_address_delivery);

        if (Validate::isLoadedObject($shippingAddress)) {
            $phone = $shippingAddress->phone;
        }

        if (empty($phone) && $order->id_address_delivery !== $order->id_address_invoice) {
            $invoiceAddress = new Address($order->id_address_invoice);
            if (Validate::isLoadedObject($invoiceAddress)) {
                $phone = $invoiceAddress->phone;
            }
        }

        return $phone ?: '';
    }

    /**
     * Checks whether order draft should be created, and if so, enqueues order draft task for creating order draft
     * and storing shipping reference.
     *
     * @param \Order $order PrestaShop order object.
     * @param \OrderState $orderState Order state object.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function createOrderDraft(\Order $order, \OrderState $orderState)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();
        /** @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository */
        $orderRepository = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Order\Interfaces\OrderRepository::CLASS_NAME
        );

        if ($this->draftShouldBeCreated($order, $orderState->id, $orderRepository)) {
            $this->enqueueDraftTask((int)$order->id, $orderRepository);
        }
    }

    /**
     * Checks whether draft for this order hasn't already been created, whether it has been paid and whether the
     * shipping carrier of that order has been created by Packlink. If all these conditions are satisfied, order draft
     * for the provided order should be created.
     *
     * @param \Order $order PrestaShop order object.
     * @param int $orderStatus Current order status.
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository Order repository.
     *
     * @return bool Returns TRUE if draft should be created, otherwise returns FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function draftShouldBeCreated(\Order $order, $orderStatus, $orderRepository)
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService::CLASS_NAME
        );
        $carrier = new \Carrier((int)$order->id_carrier);
        $orderDetails = $orderRepository->getOrderDetailsById((int)$order->id);
        $carrierServiceMapping = $carrierService->getMappingByCarrierId((int)$carrier->id_reference);

        return $orderDetails === null
            && $carrierServiceMapping !== null
            && $orderStatus === self::PRESTASHOP_PAYMENT_ACCEPTED_STATUS;
    }

    /**
     * Enqueues SendDraftTask for creating order draft on Packlink and storing shipment reference.
     *
     * @param int $orderId ID of the order.
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository Order repository.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     */
    private function enqueueDraftTask($orderId, $orderRepository)
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );
        /** @var \Logeecom\Infrastructure\TaskExecution\QueueService $queue */
        $queue = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Logeecom\Infrastructure\TaskExecution\QueueService::CLASS_NAME
        );

        $orderDetails = new \Packlink\PrestaShop\Classes\Entities\ShopOrderDetails();
        $draftTask = new \Packlink\BusinessLogic\Tasks\SendDraftTask($orderId);
        $orderDetails->setOrderId($orderId);
        $orderRepository->saveOrderDetails($orderDetails);

        $queue->enqueue($configService->getDefaultQueueName(), $draftTask);

        if ($draftTask->getExecutionId() !== null) {
            $orderDetails->setTaskId($draftTask->getExecutionId());
        }

        $orderRepository->saveOrderDetails($orderDetails);
    }

    /**
     * Renders configuration page.
     *
     * @return string
     * @throws \PrestaShopException
     */
    private function renderConfigForm()
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );
        $userInfo = $configService->getUserInfo();
        $linkLanguage = 'ES';
        if ($userInfo !== null && array_key_exists($userInfo->country, self::$helpUrls)) {
            $linkLanguage = $userInfo !== null ? $userInfo->country : 'ES';
        }

        $dashGetStatusUrl = $this->getAction('Dashboard', 'getStatus');
        $defaultParcelGetUrl = $this->getAction('DefaultParcel', 'getDefaultParcel');
        $defaultParcelSubmitUrl = $this->getAction('DefaultParcel', 'submitDefaultParcel');
        $defaultWarehouseGetUrl = $this->getAction('DefaultWarehouse', 'getDefaultWarehouse');
        $defaultWarehouseSubmitUrl = $this->getAction('DefaultWarehouse', 'submitDefaultWarehouse');
        $shippingMethodsGetAllUrl = $this->getAction('ShippingMethods', 'getAll');
        $shippingMethodsActivateUrl = $this->getAction('ShippingMethods', 'activate');
        $shippingMethodsDeactivateUrl = $this->getAction('ShippingMethods', 'deactivate');
        $shippingMethodsSaveUrl = $this->getAction('ShippingMethods', 'save');
        $getSystemOrderStatusesUrl = $this->getAction('OrderStateMapping', 'getSystemOrderStatuses');
        $orderStatusMappingsGetUrl = $this->getAction('OrderStateMapping', 'getMappings');
        $orderStatusMappingSaveUrl = $this->getAction('OrderStateMapping', 'saveMappings');
        $debugGetStatusUrl = $this->getAction('Debug', 'getStatus');
        $debugSetStatusUrl = $this->getAction('Debug', 'setStatus');
        $getSystemInfoUrl = $this->getAction('Debug', 'getSystemInfo', false);

        $frontendParams = array(
            'dashboardGetStatusUrl' => $dashGetStatusUrl,
            'defaultParcelGetUrl' => $defaultParcelGetUrl,
            'defaultParcelSubmitUrl' => $defaultParcelSubmitUrl,
            'defaultWarehouseGetUrl' => $defaultWarehouseGetUrl,
            'defaultWarehouseSubmitUrl' => $defaultWarehouseSubmitUrl,
            'shippingMethodsGetAllUrl' => $shippingMethodsGetAllUrl,
            'shippingMethodsActivateUrl' => $shippingMethodsActivateUrl,
            'shippingMethodsDeactivateUrl' => $shippingMethodsDeactivateUrl,
            'shippingMethodsSaveUrl' => $shippingMethodsSaveUrl,
            'getSystemOrderStatusesUrl' => $getSystemOrderStatusesUrl,
            'orderStatusMappingsGetUrl' => $orderStatusMappingsGetUrl,
            'orderStatusMappingsSaveUrl' => $orderStatusMappingSaveUrl,
            'debugGetStatusUrl' => $debugGetStatusUrl,
            'debugSetStatusUrl' => $debugSetStatusUrl,
            'getSystemInfoUrl' => $getSystemInfoUrl,
            'dashboardIcon' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/dashboard.png',
            'dashboardLogo' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/logo-pl.svg',
            'helpLink' => self::$helpUrls[$linkLanguage],
            'termsAndConditionsLink' => self::$termsAndConditionsUrls[$linkLanguage],
            'pluginVersion' => $this->version,
        );

        $this->context->smarty->assign($frontendParams);

        $this->context->controller->addCSS($this->_path . 'views/css/packlink.css', 'all');
        $this->context->controller->addCSS($this->_path . 'views/css/bootstrap-prestashop-ui-kit.css', 'all');
        $this->context->controller->addJS($this->_path . 'views/js/prestashop-ui-kit.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/StateController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/TemplateService.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/SidebarController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/DefaultParcelController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/PageControllerFactory.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/DefaultWarehouseController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/ShippingMethodsController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/UtilityService.js');
        $this->context->controller->addJS($this->_path . 'views/js/PrestaAjaxService.js');
        $this->context->controller->addJS($this->_path . 'views/js/PrestaFix.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/OrderStateMappingController.js');
        $this->context->controller->addJS($this->_path . 'views/js/core/FooterController.js');

        return $this->display(__FILE__, 'packlink.tpl');
    }

    /**
     * Performs login.
     *
     * @return bool
     *
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    private function login()
    {
        $apiToken = Tools::getValue('api_key', null);
        /** @var \Packlink\BusinessLogic\User\UserAccountService $userAccountService */
        $userAccountService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\User\UserAccountService::CLASS_NAME
        );

        return $userAccountService->login($apiToken);
    }

    /**
     * Retrieves ajax action.
     *
     * @param string $controller
     * @param string $action
     * @param bool $ajax
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    private function getAction($controller, $action, $ajax = true)
    {
        return $this->context->link->getAdminLink($controller) . '&' .
            http_build_query(
                array(
                    'ajax' => $ajax,
                    'action' => $action,
                )
            );
    }

    /**
     * Retrieves front action url.
     *
     * @param string $controller
     *
     * @return string
     */
    private function getFrontAction($controller)
    {
        return $this->context->link->getModuleLink(
            'packlink',
            $controller,
            array(),
            null,
            null,
            \Configuration::get('PS_SHOP_DEFAULT')
        );
    }

    /**
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository
     * @param $orderId
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     */
    private function prepareLabelsTemplate(
        \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository,
        $orderId
    ) {
        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'packlink/views/js/PrestaPrintShipmentLabels.js');

        $labels = $orderRepository->getLabelsByOrderId($orderId);
        $printLabels = array();

        foreach ($labels as $index => $label) {
            $printLabels[] = (object)array(
                'date' => $label->getDateCreated()->format('d/m/Y'),
                'link' => $label->getLink(),
                'status' => $label->isPrinted()
                    ? \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__('Printed')
                    : \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__('Ready'),
                'printed' => $label->isPrinted(),
                'number' => sprintf('#PLSL%02d', $index + 1),
            );
        }

        $printLabelUrl = $this->context->link->getAdminLink('ShipmentLabels') . '&' .
            http_build_query(
                array(
                    'ajax' => true,
                    'action' => 'setLabelPrinted',
                )
            );

        $this->context->smarty->assign(
            array(
                'orderId' => $orderId,
                'labels' => $printLabels,
                'printLabelUrl' => $printLabelUrl,
            )
        );
    }

    /**
     * Prepares Packlink shipping tab data based on the state of order details and draft task.
     *
     * @param int $orderId ID of the order.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function prepareShippingTabData($orderId)
    {
        /* @var \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository */
        $orderRepository = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Order\Interfaces\OrderRepository::CLASS_NAME
        );
        /** @var \Logeecom\Infrastructure\TaskExecution\QueueService $queue */
        $queue = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Logeecom\Infrastructure\TaskExecution\QueueService::CLASS_NAME
        );
        $orderDetails = $orderRepository->getOrderDetailsById($orderId);

        $shipping = null;
        $message = '';
        $displayDraftButton = false;

        if ($orderDetails === null || $orderDetails->getTaskId() === null) {
            $message = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                'Create order draft on Packlink'
            );
            $displayDraftButton = true;
        } else {
            $draftTask = $queue->find($orderDetails->getTaskId());

            if ($draftTask !== null
                && $draftTask->getStatus() === \Logeecom\Infrastructure\TaskExecution\QueueItem::FAILED) {
                $message = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                    'Previous attempt to create a draft failed. Error: %s',
                    array($draftTask->getFailureDescription())
                );
                $displayDraftButton = true;
            } elseif ($orderDetails->getShipmentReference() === null) {
                $message = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                    'Draft is currently being created in Packlink'
                );
            } else {
                $shipping = $this->prepareShippingObject($orderId, $orderDetails, $orderRepository);
            }
        }
        $this->context->smarty->assign(array(
            'shipping' => $shipping,
            'message' => $message,
            'pluginBasePath' => $this->_path,
            'orderId' => $orderId,
            'displayDraftButton' => $displayDraftButton,
            'createDraftUrl' => $this->context->link->getAdminLink('OrderDraft') . '&' .
                http_build_query(
                    array(
                        'ajax' => true,
                        'action' => 'createOrderDraft',
                    )
                ),
        ));
    }

    /**
     * Prepares shipping details object for Packlink shipping tab.
     *
     * @param int $orderId ID of the order.
     * @param \Packlink\PrestaShop\Classes\Entities\ShopOrderDetails $orderDetails Details of the order.
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository Order repository.
     *
     * @return object
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function prepareShippingObject(
        $orderId,
        $orderDetails,
        \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository
    ) {
        /** @var \Logeecom\Infrastructure\Utility\TimeProvider $timeProvider */
        $timeProvider = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Logeecom\Infrastructure\Utility\TimeProvider::CLASS_NAME
        );

        $order = new \Order($orderId);
        $carrier = new Carrier((int)$order->id_carrier);

        $this->prepareLabelsTemplate($orderRepository, $orderId);

        $shipping = (object)array(
            'name' => $carrier->name,
            'reference' => $orderDetails->getShipmentReference(),
            'icon' => file_exists(_PS_SHIP_IMG_DIR_ . '/' . (int)$carrier->id . '.jpg') ?
                _PS_BASE_URL_ . __PS_BASE_URI__ . 'img/s/' . (int)$carrier->id . '.jpg' : '',
            'status' => $orderDetails->getShippingStatus() !== null ? $orderDetails->getShippingStatus()
                : '',
            'time' => $orderDetails->getLastStatusUpdateTime() !== null
                ? $timeProvider->serializeDate($orderDetails->getLastStatusUpdateTime(), 'd.m.Y H:i:s')
                : '',
            'carrier_tracking_numbers' => $orderDetails->getCarrierTrackingNumbers(),
            'carrier_tracking_url' => $orderDetails->getCarrierTrackingUrl() !== null
                ? $orderDetails->getCarrierTrackingUrl() : '',
            'packlink_shipping_price' => $orderDetails->getPacklinkShippingPrice() !== null
                ? $orderDetails->getPacklinkShippingPrice() . ' €' : '',
            'link' => $order->packlink_order_draft,
        );

        return $shipping;
    }
}
