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

/** @noinspection PhpUnusedParameterInspection */
if (!defined('_PS_VERSION_')) {
    exit;
}

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

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
    const PRESTASHOP_PROCESSING_IN_PROGRESS_STATUS = 3;
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
     * List of country names for different country codes.
     *
     * @var array
     */
    private static $countryNames = array(
        'ES' => 'Spain',
        'DE' => 'Germany',
        'FR' => 'France',
        'IT' => 'Italy',
    );

    /**
     * Packlink constructor.
     */
    public function __construct()
    {
        $this->module_key = '0b685e39fafb6de6bd21daaa455f4404';
        $this->name = 'packlink';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0.2';
        $this->author = $this->l('Packlink Shipping S.L.');
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6.1', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Packlink PRO Shipping');
        $this->description = $this->l('Save up to 70% on your shipping costs. No fixed fees, no minimum shipping volume required. Manage all your shipments in a single platform.');

        $this->context = Context::getContext();
    }

    /**
     * Handle plugin installation
     *
     * @return bool
     *
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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
        $locationPickerLibrary = $this->_path . 'views/js/location/LocationPicker.js';
        $output = "<script src=\"{$locationPickerLibrary}\"></script>" . "\n";

        $locationPickerTrans = $this->_path . 'views/js/location/Translations.js';
        $output .= "<script src=\"{$locationPickerTrans}\"></script>" . "\n";

        $locationPickerCSS = $this->_path . 'views/css/locationPicker.css';
        $output .= "<link rel=\"stylesheet\" href=\"{$locationPickerCSS}\"/>" . "\n";

        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return $output . $this->getPresta16ShippingStepPage($params);
        }

        $shippingServicePath = $this->_path . 'views/js/ShippingService17.js';
        $output .= "<script src=\"{$shippingServicePath}\"></script>" . "\n";

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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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
     * @param \Cart $cart Shopping cart object.
     * @param int $shippingCost Shipping cost calculated by PrestaShop.
     * @param array $products Array of shop products for which shipping cost is calculated.
     *
     * @return float|bool Calculated shipping cost if carrier is available, otherwise FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function getPackageShippingCost($cart, $shippingCost, $products)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService::CLASS_NAME
        );
        $carrier = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCarrier($this->id_carrier);
        $carrierReferenceId = (int)$carrier->id_reference;
        $methodId = $carrierService->getShippingMethodId($carrierReferenceId);

        if ($methodId === null) {
            return false;
        }

        $shippingProducts = array();
        foreach ($products as $product) {
            if (!$product['is_virtual']) {
                $shippingProducts[] = $product;
            }
        }

        $calculatedCosts = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCosts();

        if ($this->displayBackupCarrier($cart, $calculatedCosts, $carrierReferenceId)) {
            $allCosts = $this->getCostsForAllShippingMethods($cart, $shippingProducts);
            if (!empty($allCosts)) {
                return min(array_values($allCosts));
            }
        }

        if ($calculatedCosts !== false) {
            return isset($calculatedCosts[$methodId]) ? $calculatedCosts[$methodId] : false;
        }

        $warehouse = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getDefaultWarehouse();
        if ($warehouse === null) {
            return false;
        }

        $toCountry = $this->getDestinationCountryCode($cart, $warehouse);
        $toZip = $this->getDestinationCountryZip($cart, $warehouse);
        $parcels = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getPackages($shippingProducts);

        /** @var \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService $shippingMethodService */
        $shippingMethodService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService::CLASS_NAME
        );

        $calculatedCosts = $shippingMethodService->getShippingCosts(
            $warehouse->country,
            $warehouse->postalCode,
            $toCountry,
            $toZip,
            $parcels,
            $this->getCartTotal($cart)
        );

        \Packlink\PrestaShop\Classes\Utility\CachingUtility::setCosts($calculatedCosts);

        return isset($calculatedCosts[$methodId]) ? $calculatedCosts[$methodId] : false;
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
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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

        $this->context->smarty->assign(array(
            'fancyBoxPath' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'js/jquery/plugins/fancybox/jquery.fancybox.js',
        ));

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

        $supportedLanguages = array('en', 'es', 'it', 'fr', 'de');

        $dropOffs = \Packlink\PrestaShop\Classes\Utility\CarrierUtility::getDropOffCarrierReferenceIds();
        $configuration = array(
            'dropoffIds' => $dropOffs,
            'getLocationsUrl' => $this->getFrontAction('locations'),
        );


        $lang = 'en';

        if (!empty($GLOBALS['locale'])) {
            $locale = explode('_', $GLOBALS['locale']);
            if (!empty($locale[0]) && in_array($locale[0], $supportedLanguages, true)) {
                $lang = $locale[0];
            }
        }

        $configuration['lang'] = $lang;

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
     * Creates drop-off address.
     *
     * @param \Order $order
     * @param \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function createDropOffAddress(
        \Order $order,
        \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping $mapping
    ) {
        $address = new Address();

        $db = Db::getInstance();

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
        $address->company = $dropOff['name'];
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
     * Returns whether backup carrier should be displayed.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param array $calculatedCosts Array of calculated shipping costs.
     * @param int $carrierId ID of the carrier.
     *
     * @return bool Returns TRUE if backup carrier should be displayed, otherwise returns FALSE.
     */
    private function displayBackupCarrier($cart, $calculatedCosts, $carrierId)
    {
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );

        if (is_array($calculatedCosts)
            && empty($calculatedCosts)
            && $carrierId === $configService->getBackupCarrierId()
        ) {
            $zoneId = Address::getZoneById($cart->id_address_delivery);
            $customer = new Customer($cart->id_customer);

            $internalCarriers = Carrier::getCarriers(
                Context::getContext()->language->id,
                true,
                false,
                $zoneId,
                $customer->getGroups(),
                Carrier::PS_CARRIERS_ONLY
            );

            return empty($internalCarriers);
        }

        return false;
    }

    /**
     * Returns shipping costs for all Packlink shipping methods, not just active ones.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param array $products Array of products.
     *
     * @return array Array of shipping costs for all Packlink shipping methods.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function getCostsForAllShippingMethods($cart, $products)
    {
        $warehouse = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getDefaultWarehouse();
        if ($warehouse === null) {
            return array();
        }

        /** @var \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService $shippingMethodsService */
        $shippingMethodService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\ShippingMethodService::CLASS_NAME
        );

        return \Packlink\BusinessLogic\ShippingMethod\ShippingCostCalculator::getShippingCosts(
            $shippingMethodService->getAllMethods(),
            $warehouse->country,
            $warehouse->postalCode,
            $this->getDestinationCountryCode($cart, $warehouse),
            $this->getDestinationCountryZip($cart, $warehouse),
            \Packlink\PrestaShop\Classes\Utility\CachingUtility::getPackages($products),
            $this->getCartTotal($cart)
        );
    }

    /**
     * Returns destination country code.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param \Packlink\BusinessLogic\Http\DTO\Warehouse $warehouse
     *
     * @return string Destination country code.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    private function getDestinationCountryCode($cart, $warehouse)
    {
        $countryCode = $warehouse->country;

        if (!empty($cart->id_address_delivery)) {
            $deliveryAddress = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getAddress(
                (int)$cart->id_address_delivery
            );
            $deliveryCountry = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCountry(
                (int)$deliveryAddress->id_country
            );

            $countryCode = $deliveryCountry->iso_code;
        }

        return $countryCode;
    }

    /**
     * Returns destination country ZIP code.
     *
     * @param \Cart $cart PrestaShop cart object.
     * @param \Packlink\BusinessLogic\Http\DTO\Warehouse $warehouse
     *
     * @return string Destination country zip code.
     */
    private function getDestinationCountryZip($cart, $warehouse)
    {
        $destinationZip = $warehouse->postalCode;

        if (!empty($cart->id_address_delivery)) {
            $deliveryAddress = \Packlink\PrestaShop\Classes\Utility\CachingUtility::getAddress(
                (int)$cart->id_address_delivery
            );

            $destinationZip = $deliveryAddress->postcode;
        }

        return $destinationZip;
    }

    /**
     * Checks if Packlink authorization token, default parcel and default warehouse have been set in the shop.
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
    private function createOrderDraft(\Order $order, OrderState $orderState)
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
        $carrier = new Carrier((int)$order->id_carrier);
        $orderDetails = $orderRepository->getOrderDetailsById((int)$order->id);
        $carrierServiceMapping = $carrierService->getMappingByCarrierReferenceId((int)$carrier->id_reference);

        return $orderDetails === null
            && $carrierServiceMapping !== null
            && ($orderStatus === self::PRESTASHOP_PAYMENT_ACCEPTED_STATUS
                || $orderStatus === self::PRESTASHOP_PROCESSING_IN_PROGRESS_STATUS);
    }

    /**
     * Enqueues SendDraftTask for creating order draft on Packlink and storing shipment reference.
     *
     * @param int $orderId ID of the order.
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository Order repository.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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

        $orderDetails = new \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails();
        $draftTask = new \Packlink\BusinessLogic\Tasks\SendDraftTask($orderId);
        $orderDetails->setOrderId($orderId);
        $orderRepository->saveOrderDetails($orderDetails);

        $queue->enqueue($configService->getDefaultQueueName(), $draftTask);
        if ($draftTask->getExecutionId() !== null) {
            // get again from database since it can happen that task already finished and
            // reference has been set, so we don't delete it here.
            $orderDetails = $orderRepository->getOrderDetailsById($orderId);
            $orderDetails->setTaskId($draftTask->getExecutionId());
            $orderRepository->saveOrderDetails($orderDetails);
        }
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
        $warehouseCountry = '';
        if ($userInfo !== null && array_key_exists($userInfo->country, self::$helpUrls)) {
            $linkLanguage = $userInfo->country;
            $warehouseCountry = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                self::$countryNames[$userInfo->country]
            );
        }

        $dashGetStatusUrl = $this->getAction('Dashboard', 'getStatus');
        $defaultParcelGetUrl = $this->getAction('DefaultParcel', 'getDefaultParcel');
        $defaultParcelSubmitUrl = $this->getAction('DefaultParcel', 'submitDefaultParcel');
        $defaultWarehouseGetUrl = $this->getAction('DefaultWarehouse', 'getDefaultWarehouse');
        $defaultWarehouseSubmitUrl = $this->getAction('DefaultWarehouse', 'submitDefaultWarehouse');
        $defaultWarehouseSearchPostalCodesUrl = $this->getAction('DefaultWarehouse', 'searchPostalCodes');
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
        $shopShippingMethodCountGetUrl = $this->getAction('ShippingMethods', 'getNumberShopMethods');
        $shopShippingMethodsDisableUrl = $this->getAction('ShippingMethods', 'disableShopShippingMethods');
        $shippingMethodsGetTaxClasses = $this->getAction('ShippingMethods', 'getAvailableTaxClasses');

        $frontendParams = array(
            'dashboardGetStatusUrl' => $dashGetStatusUrl,
            'defaultParcelGetUrl' => $defaultParcelGetUrl,
            'defaultParcelSubmitUrl' => $defaultParcelSubmitUrl,
            'defaultWarehouseGetUrl' => $defaultWarehouseGetUrl,
            'defaultWarehouseSubmitUrl' => $defaultWarehouseSubmitUrl,
            'defaultWarehouseSearchPostalCodesUrl' => $defaultWarehouseSearchPostalCodesUrl,
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
            'shopShippingMethodCountGetUrl' => $shopShippingMethodCountGetUrl,
            'shopShippingMethodsDisableUrl' => $shopShippingMethodsDisableUrl,
            'shippingMethodsGetTaxClasses' => $shippingMethodsGetTaxClasses,
            'dashboardIcon' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/dashboard.png',
            'dashboardLogo' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'modules/packlink/views/img/logo-pl.svg',
            'helpLink' => self::$helpUrls[$linkLanguage],
            'termsAndConditionsLink' => self::$termsAndConditionsUrls[$linkLanguage],
            'pluginVersion' => $this->version,
            'warehouseCountry' => $warehouseCountry,
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
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
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
            Context::getContext()->shop->id
        );
    }

    /**
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository
     * @param $orderId
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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

        if ($orderDetails === null
            || ($orderDetails->getReference() === null && $orderDetails->getTaskId() === null)
        ) {
            $message = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                'Create order draft in Packlink PRO'
            );
            $displayDraftButton = true;
        } else {
            $draftTask = $orderDetails->getTaskId() ? $queue->find($orderDetails->getTaskId()) : null;

            if ($draftTask !== null
                && $draftTask->getStatus() === \Logeecom\Infrastructure\TaskExecution\QueueItem::FAILED) {
                $message = \Packlink\PrestaShop\Classes\Utility\TranslationUtility::__(
                    'Previous attempt to create a draft failed. Error: %s',
                    array($draftTask->getFailureDescription())
                );
                $displayDraftButton = true;
            } elseif ($orderDetails->getReference() === null) {
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
     * @param \Packlink\BusinessLogic\Order\Models\OrderShipmentDetails $orderDetails Details of the order.
     * @param \Packlink\PrestaShop\Classes\Repositories\OrderRepository $orderRepository Order repository.
     *
     * @return object
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
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
            'reference' => $orderDetails->getReference(),
            'deleted' => $orderDetails->isDeleted(),
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
            'packlink_shipping_price' => $orderDetails->getShippingCost() !== null
                ? $orderDetails->getShippingCost() . ' €' : '',
            'link' => $this->getOrderDraftUrl($orderDetails->getReference()),
        );

        return $shipping;
    }

    /**
     * Gets total cart value.
     *
     * @param \Cart $cart
     *
     * @return array|float
     */
    private function getCartTotal(Cart $cart)
    {
        if (\Packlink\PrestaShop\Classes\Utility\CachingUtility::getCartTotal() === false) {
            \Packlink\PrestaShop\Classes\Utility\CachingUtility::setCartTotal(
                $cart->getOrderTotal(true, Cart::BOTH_WITHOUT_SHIPPING)
            );
        }

        return \Packlink\PrestaShop\Classes\Utility\CachingUtility::getCartTotal();
    }

    /**
     * Returns link to order draft on Packlink for the provided shipment reference.
     *
     * @param string $reference Shipment reference.
     *
     * @return string Link to order draft on Packlink.
     */
    private function getOrderDraftUrl($reference)
    {
        /** @var \Packlink\BusinessLogic\Configuration $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );
        $userCountry = $configService->getUserInfo() !== null
            ? Tools::strtolower($configService->getUserInfo()->country)
            : 'es';

        return "https://pro.packlink.$userCountry/private/shipments/$reference";
    }
}
