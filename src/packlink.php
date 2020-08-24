<?php

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
 * @property \Context context
 * @method string display($file, $template, $cache_id = null, $compile_id = null)
 * @method unregisterHook($string)
 */
class Packlink extends CarrierModule
{
    const PACKLINK_SHIPPING_TAB = 'views/templates/admin/packlink_shipping_tab/shipping_tab.tpl';
    const PACKLINK_SHIPPING_CONTENT = 'views/templates/admin/packlink_shipping_content/shipping_content.tpl';
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
     * Packlink constructor.
     */
    public function __construct()
    {
        $this->module_key = 'a7a3a395043ca3a09d703f7d1c74a107';
        $this->name = 'packlink';
        $this->tab = 'shipping_logistics';
        $this->version = '2.3.0';
        $this->author = $this->l('Packlink Shipping S.L.');
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6.0.14', 'max' => _PS_VERSION_);
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
     * Install overridden files from the module.
     *
     * @return bool
     */
    public function installOverrides()
    {
        $this->uninstallOverrides();
        $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($this);
        $installer->removeOldOverrides();
        if (!$installer->shouldInstallOverrides()) {
            // skip installing overrides / do not call parent method
            return true;
        }

        return parent::installOverrides();
    }

    /**
     * Enables the module.
     *
     * @param bool $force_all
     *
     * @return bool
     */
    public function enable($force_all = false)
    {
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            // v1.7+ installs overrides on enable and 1.6 does not.
            $this->installOverrides();
        }

        return parent::enable($force_all);
    }

    /**
     * Disables the module.
     *
     * @param bool $force_all
     *
     * @return bool
     */
    public function disable($force_all = false)
    {
        if (version_compare(_PS_VERSION_, '1.7', '<')) {
            // v1.7+ uninstalls overrides on disable and 1.6 does not.
            $this->uninstallOverrides();
        }

        return parent::disable($force_all);
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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function hookDisplayBeforeCarrier($params)
    {
        $output = $this->getLocationPickerFilesLinks();

        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return $output . $this->getPresta16ShippingStepPage($params);
        }

        $shippingServicePath = $this->_path . 'views/js/ShippingService17.js?v=' . $this->version;
        $output .= "<script src=\"{$shippingServicePath}\"></script>\n";

        $output .= $this->getCheckoutFilesLinks();

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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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
     *
     * @throws \SmartyException
     */
    public function hookDisplayAdminOrderTabShip()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        if (!$this->moduleFullyConfigured()) {
            return '';
        }

        return $this->context->smarty->createTemplate(
            $this->getLocalPath() . self::PACKLINK_SHIPPING_TAB,
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

        \Packlink\PrestaShop\Classes\Utility\AdminShippingTabDataProvider::prepareShippingTabData(
            $this->context,
            $this,
            Tools::getValue('id_order')
        );

        return $this->context->smarty->createTemplate(
            $this->getLocalPath() . self::PACKLINK_SHIPPING_CONTENT,
            $this->context->smarty
        )->fetch();
    }

    /**
     * Frontend hook for order creation.
     *
     * @param array $params Hook parameters.
     *
     * @return string
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    public function hookDisplayOrderConfirmation($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Order $order */
        $order = array_key_exists('order', $params) ? $params['order'] : $params['objOrder'];
        $cartId = $order->id_cart;
        $carrierId = $order->id_carrier;
        if (\Packlink\PrestaShop\Classes\Utility\CarrierUtility::isDropOff((int)$carrierId)
            && !\Packlink\PrestaShop\Classes\Utility\CheckoutUtility::isDropOffSelected(
                (string)$cartId,
                (string)$carrierId
            )
        ) {
            $configuration = $this->getShippingStepConfiguration($params);
            $configuration['id'] = $carrierId;
            $configuration['orderId'] = $order->id;
            $configuration['addressId'] = $order->id_address_delivery;
            $configuration['cartId'] = $order->id_cart;
            $this->context->smarty->assign(
                array('configuration' => json_encode($configuration))
            );

            $output = $this->getLocationPickerFilesLinks();

            $output .= $this->getCheckoutFilesLinks();
            $output .= $this->display(__FILE__, 'confirm.tpl');

            return $output;
        }

        return '';
    }

    /**
     * Hook for order creation.
     *
     * @param array $params Hook parameters.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapNotFound
     */
    public function hookActionValidateOrder($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Order $order */
        $order = $params['order'];
        $carrier = new \Carrier($order->id_carrier);

        $isDelayed = false;

        if (\Packlink\PrestaShop\Classes\Utility\CarrierUtility::isDropOff((int)$carrier->id_reference)) {
            $isDropOffSelected = \Packlink\PrestaShop\Classes\Utility\CheckoutUtility::isDropOffSelected(
                (string)$order->id_cart,
                (string)$order->id_carrier
            );

            if ($isDropOffSelected) {
                $this->createDropOffAddress($order);
            } else {
                $isDelayed = true;
            }
        }

        $this->createOrderDraft($params['order'], $params['orderStatus'], $isDelayed);
    }

    /**
     * Hook that is triggered when order status is updated on backend.
     *
     * @param $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapNotFound
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function hookActionOrderStatusUpdate($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

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
     * @return float | bool Calculated shipping cost if carrier is available, otherwise FALSE.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopException
     * @throws \PrestaShop\PrestaShop\Adapter\CoreException
     */
    public function getPackageShippingCost($cart, $shippingCost, $products)
    {
        $cost = \Packlink\PrestaShop\Classes\ShippingServices\PackageCostCalculator::getPackageCost(
            $cart,
            $products,
            $this->id_carrier
        );

        if (!empty($cost) && !empty($shippingCost)) {
            $cost += $shippingCost;
        }

        return $cost;
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
     */
    public function getContent()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup $wakeupService */
        $wakeupService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Logeecom\Infrastructure\TaskExecution\Interfaces\TaskRunnerWakeup::CLASS_NAME
        );
        $wakeupService->wakeup();

        $this->loadStyles();
        $this->loadScripts();

        \Packlink\BusinessLogic\Configuration::setCurrentLanguage($this->context->language->iso_code);

        $this->context->smarty->assign(array(
            'lang' => $this->getTranslations(),
            'templates' => $this->getTemplates(),
            'urls' => $this->getUrls(),
            'baseResourcesUrl' => $this->getPathUri() . 'views/packlink',
            'gridResizerScript' => $this->getPathUri() . 'views/packlink/js/GridResizerService.js?v=' . $this->version,
        ));

        return $this->display(__FILE__, 'index.tpl');
    }

    /**
     * Loads Packlink stylesheets.
     */
    private function loadStyles()
    {
        $this->context->controller->addCSS(
            array(
                'https://fonts.googleapis.com/icon?family=Material+Icons+Outlined',
                $this->getPathUri() . 'views/packlink/css/app.css?v=' . $this->version,
                $this->getPathUri() . 'views/css/packlink-core-override.css?v=' . $this->version,
            ),
            'all',
            null,
            false
        );
    }

    /**
     * Loads Packlink scripts.
     */
    private function loadScripts()
    {
        $this->context->controller->addJS(
            array(
                $this->getPathUri() . 'views/js/PrestaFix.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/UtilityService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/TemplateService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/AjaxService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/TranslationService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ValidationService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ShippingServicesRenderer.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/AutoTestController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ConfigurationController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/DefaultParcelController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/DefaultWarehouseController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/EditServiceController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/LoginController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ModalService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/MyShippingServicesController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/OnboardingOverviewController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/OnboardingStateController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/OnboardingWelcomeController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/OrderStatusMappingController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/PageControllerFactory.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/PickShippingServiceController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/PricePolicyController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/RegisterController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/RegisterModalController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ResponseService.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/ServiceCountriesModalController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/StateController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/SystemInfoController.js?v=' . $this->version,
                $this->getPathUri() . 'views/packlink/js/StateUUIDService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/PrestaAjaxService.js?v=' . $this->version,
            ),
            false
        );
    }

    /**
     * Returns Packlink module translations in the default and the current system language.
     *
     * @return array
     */
    private function getTranslations()
    {
        return array(
            'default' => $this->getDefaultTranslations(),
            'current' => $this->getCurrentTranslations(),
        );
    }

    /**
     * Returns JSON encoded module page translations in the default language, along with some module-specific translations.
     *
     * @return string
     */
    private function getDefaultTranslations()
    {
        $baseDir = $this->getLocalPath() . 'views/packlink/lang/';
        $defaultTranslations = json_decode(file_get_contents($baseDir . 'en.json'), true);

        $defaultTranslations['shippingServices']['serviceCountriesTitle'] = 'Availability by destination zone';
        $defaultTranslations['shippingServices']['serviceCountriesDescription'] = 'Select the availability for the zones that are supported for your shipping service.';
        $defaultTranslations['shippingServices']['openCountries'] = 'See zones';
        $defaultTranslations['shippingServices']['allCountriesSelected'] = 'All zones selected';
        $defaultTranslations['shippingServices']['oneCountrySelected'] = 'One zone selected';
        $defaultTranslations['shippingServices']['selectedCountries'] = '%s zones selected.';
        $defaultTranslations['shippingServices']['selectAllCountries'] = 'All selected zones';
        $defaultTranslations['shippingServices']['selectCountriesHeader'] = 'Zones supported for your shipping service';
        $defaultTranslations['shippingServices']['selectCountriesSubheader'] = 'Select availability of at least one zone and add as many required';
        $defaultTranslations['shippingServices']['atLeastOneCountry'] = 'At least one zone must be selected.';

        return json_encode($defaultTranslations);
    }

    /**
     * Returns JSON encoded module page translations in the current system language, along with some module-specific translations.
     *
     * @return string
     */
    private function getCurrentTranslations()
    {
        $baseDir = $this->getLocalPath() . 'views/packlink/lang/';
        $locale = strtolower($this->context->language->iso_code);

        $currentLangFilename = $baseDir . $locale . '.json';
        $currentTranslations = file_exists($currentLangFilename) ? json_decode(file_get_contents($currentLangFilename), true) : array();

        if (!empty($currentTranslations)) {
            $currentTranslations['shippingServices']['serviceCountriesTitle'] = $this->l('Availability by destination zone');
            $currentTranslations['shippingServices']['serviceCountriesDescription'] = $this->l('Select the availability for the zones that are supported for your shipping service.');
            $currentTranslations['shippingServices']['openCountries'] = $this->l('See zones');
            $currentTranslations['shippingServices']['allCountriesSelected'] = $this->l('All zones selected');
            $currentTranslations['shippingServices']['oneCountrySelected'] = $this->l('One zone selected');
            $currentTranslations['shippingServices']['selectedCountries'] = $this->l('%s zones selected.');
            $currentTranslations['shippingServices']['selectAllCountries'] = $this->l('All selected zones');
            $currentTranslations['shippingServices']['selectCountriesHeader'] = $this->l('Zones supported for your shipping service');
            $currentTranslations['shippingServices']['selectCountriesSubheader'] = $this->l('Select availability of at least one zone and add as many required');
            $currentTranslations['shippingServices']['atLeastOneCountry'] = $this->l('At least one zone must be selected.');
        }

        return json_encode($currentTranslations);
    }

    /**
     * Returns Packlink module templates.
     *
     * @return array
     */
    private function getTemplates()
    {
        $baseDir = $this->getLocalPath() . 'views/packlink/templates/';

        return array(
            'configuration' => json_encode(file_get_contents($baseDir . 'configuration.html')),
            'countries-selection-modal' => json_encode(file_get_contents($baseDir . 'countries-selection-modal.html')),
            'default-parcel' => json_encode(file_get_contents($baseDir . 'default-parcel.html')),
            'default-warehouse' => json_encode(file_get_contents($baseDir . 'default-warehouse.html')),
            'disable-carriers-modal' => json_encode(file_get_contents($baseDir . 'disable-carriers-modal.html')),
            'edit-shipping-service' => json_encode(file_get_contents($baseDir . 'edit-shipping-service.html')),
            'login' => json_encode(file_get_contents($baseDir . 'login.html')),
            'my-shipping-services' => json_encode(file_get_contents($baseDir . 'my-shipping-services.html')),
            'onboarding-overview' => json_encode(file_get_contents($baseDir . 'onboarding-overview.html')),
            'onboarding-welcome' => json_encode(file_get_contents($baseDir . 'onboarding-welcome.html')),
            'order-status-mapping' => json_encode(file_get_contents($baseDir . 'order-status-mapping.html')),
            'pick-shipping-services' => json_encode(file_get_contents($baseDir . 'pick-shipping-services.html')),
            'pricing-policies-list' => json_encode(file_get_contents($baseDir . 'pricing-policies-list.html')),
            'pricing-policy-modal' => json_encode(file_get_contents($baseDir . 'pricing-policy-modal.html')),
            'register' => json_encode(file_get_contents($baseDir . 'register.html')),
            'register-modal' => json_encode(file_get_contents($baseDir . 'register-modal.html')),
            'shipping-services-header' => json_encode(file_get_contents($baseDir . 'shipping-services-header.html')),
            'shipping-services-list' => json_encode(file_get_contents($baseDir . 'shipping-services-list.html')),
            'shipping-services-table' => json_encode(file_get_contents($baseDir . 'shipping-services-table.html')),
            'system-info-modal' => json_encode(file_get_contents($baseDir . 'system-info-modal.html')),
        );
    }

    /**
     * Returns Packlink module controller URLs.
     *
     * @return array
     *
     * @throws \PrestaShopException
     */
    private function getUrls()
    {
        return array(
            'login' => array(
                'submit' => $this->getAction('Login', 'login'),
                'listOfCountriesUrl' => $this->getAction('RegistrationRegions', 'getRegions'),
            ),
            'register' => array(
                'getRegistrationData' => $this->getAction('Registration', 'getRegisterData'),
                'submit' => $this->getAction('Registration', 'register'),
            ),
            'onboardingState' => array(
                'getState' => $this->getAction('Onboarding', 'getCurrentState'),
            ),
            'onboardingOverview' => array(
                'defaultParcelGet' => $this->getAction('DefaultParcel', 'getDefaultParcel'),
                'defaultWarehouseGet' => $this->getAction('DefaultWarehouse', 'getDefaultWarehouse'),
            ),
            'defaultParcel' => array(
                'getUrl' => $this->getAction('DefaultParcel', 'getDefaultParcel'),
                'submitUrl' => $this->getAction('DefaultParcel', 'submitDefaultParcel'),
            ),
            'defaultWarehouse' => array(
                'getUrl' => $this->getAction('DefaultWarehouse', 'getDefaultWarehouse'),
                'getSupportedCountriesUrl' => $this->getAction('DefaultWarehouse', 'getSupportedCountries'),
                'submitUrl' => $this->getAction('DefaultWarehouse', 'submitDefaultWarehouse'),
                'searchPostalCodesUrl' => $this->getAction('DefaultWarehouse', 'searchPostalCodes'),
            ),
            'configuration' => array(
                'getDataUrl' => $this->getAction('Configuration', 'getData'),
            ),
            'systemInfo' => array(
                'getStatusUrl' => $this->getAction('Debug', 'getStatus'),
                'setStatusUrl' => $this->getAction('Debug', 'setStatus'),
            ),
            'orderStatusMapping' => array(
                'getMappingAndStatusesUrl' => $this->getAction('OrderStateMapping', 'getMappingsAndStatuses'),
                'setUrl' => $this->getAction('OrderStateMapping', 'saveMappings'),
            ),
            'myShippingServices' => array(
                'getServicesUrl' => $this->getAction('ShippingMethods', 'getActive'),
                'deleteServiceUrl' => $this->getAction('ShippingMethods', 'deactivate'),
            ),
            'pickShippingService' => array(
                'getActiveServicesUrl' => $this->getAction('ShippingMethods', 'getActive'),
                'getServicesUrl' => $this->getAction('ShippingMethods', 'getAll'),
                'getTaskStatusUrl' => $this->getAction('ShippingMethods', 'getTaskStatus'),
                'startAutoConfigureUrl' => $this->getAction('PacklinkAutoConfigure', 'start'),
                'disableCarriersUrl' => $this->getAction('ShippingMethods', 'disableShopShippingMethods'),
            ),
            'editService' => array(
                'getServiceUrl' => $this->getAction('ShippingMethods', 'getShippingMethod'),
                'saveServiceUrl' => $this->getAction('ShippingMethods', 'save'),
                'getTaxClassesUrl' => $this->getAction('ShippingMethods', 'getAvailableTaxClasses'),
                'getCountriesListUrl' => $this->getAction('ShippingZones', 'getShippingZones'),
            ),
            'stateUrl' => $this->getAction('ModuleState', 'getCurrentState'),
        );
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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
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
        $cart = $params['cart'];
        $language = new \Language((int)$cart->id_lang);

        if (Validate::isLoadedObject($language) && in_array($language->iso_code, $supportedLanguages, true)) {
            $lang = $language->iso_code;
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
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    protected function getPresta16ShippingStepPage($params)
    {
        $configuration = $this->getShippingStepConfiguration($params);

        $this->context->smarty->assign(
            array('configuration' => json_encode($configuration))
        );

        $shippingServicePath = $this->_path . 'views/js/ShippingService16.js?v=' . $this->version;
        $output = "<script src=\"{$shippingServicePath}\"></script>\n";

        $output .= $this->getCheckoutFilesLinks();

        return $output . $this->display(__FILE__, 'shipping_methods_16.tpl');
    }

    /**
     * Gets the HTML for links for CSS and JS files needed in checkout process.
     *
     * @return string
     */
    protected function getCheckoutFilesLinks()
    {
        $ajaxPath = $this->_path . 'views/js/core/AjaxService.js?v=' . $this->version;
        $output = "<script src=\"{$ajaxPath}\"></script>\n";

        $ajaxPath = $this->_path . 'views/js/PrestaAjaxService.js?v=' . $this->version;
        $output .= "<script src=\"{$ajaxPath}\"></script>\n";

        $stylePath = $this->_path . 'views/css/checkout.css?v=' . $this->version;
        $output .= "<link rel=\"stylesheet\" href=\"{$stylePath}\"/>\n";

        $checkoutPath = $this->_path . 'views/js/CheckOutController.js?v=' . $this->version;
        $output .= "<script src=\"{$checkoutPath}\"></script>\n";

        $mapModalPath = $this->_path . 'views/js/MapModalController.js?v=' . $this->version;
        $output .= "<script src=\"{$mapModalPath}\"></script>\n";

        return $output;
    }

    /**
     * Gets HTML scripts for output template for checkout process.
     *
     * @return string
     */
    protected function getLocationPickerFilesLinks()
    {
        $locationPickerLibrary = $this->_path . 'views/js/location/LocationPicker.js?v=' . $this->version;
        $output = "<script src=\"{$locationPickerLibrary}\"></script>\n";

        $locationPickerTrans = $this->_path . 'views/js/location/Translations.js?v=' . $this->version;
        $output .= "<script src=\"{$locationPickerTrans}\"></script>\n";

        $locationPickerCSS = $this->_path . 'views/css/locationPicker.css?v=' . $this->version;
        $output .= "<link rel=\"stylesheet\" href=\"{$locationPickerCSS}\"/>\n";

        return $output;
    }

    /**
     * Creates drop-off address.
     *
     * @param \Order $order
     *
     * @return bool
     */
    protected function createDropOffAddress(\Order $order)
    {
        try {
            $repository = \Logeecom\Infrastructure\ORM\RepositoryRegistry::getRepository(
                \Packlink\PrestaShop\Classes\Entities\CartCarrierDropOffMapping::getClassName()
            );

            $query = new \Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter();
            $query->where('cartId', '=', (string)$order->id_cart)
                ->where('carrierReferenceId', '=', (string)$order->id_carrier);

            $mapping = $repository->selectOne($query);

            if (!$mapping) {
                \Logeecom\Infrastructure\Logger\Logger::logWarning(
                    "Drop-off is not selected for order [{$order->id}]."
                );

                return false;
            }

            $dropOff = $mapping->getDropOff();
            \Packlink\PrestaShop\Classes\Utility\AddressUitlity::createDropOffAddress($order, $dropOff);
        } catch (\Exception $e) {
            \Logeecom\Infrastructure\Logger\Logger::logError(
                "Failed to create drop-off for order [{$order->id}] because: {$e->getMessage()}."
            );

            return false;
        }

        return true;
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
     * Checks whether order draft should be created, and if so, enqueues order draft task for creating order draft
     * and storing shipping reference.
     *
     * @param \Order $order PrestaShop order object.
     * @param \OrderState $orderState Order state object.
     *
     * @param bool $isDelayed
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapExists
     * @throws \Packlink\BusinessLogic\ShipmentDraft\Exceptions\DraftTaskMapNotFound
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function createOrderDraft(\Order $order, OrderState $orderState, $isDelayed = false)
    {
        if ($this->shouldCreateDraft($order, $orderState)) {
            /** @var \Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService $shipmentDraftService */
            $shipmentDraftService = \Logeecom\Infrastructure\ServiceRegister::getService(
                \Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService::CLASS_NAME
            );
            $shipmentDraftService->enqueueCreateShipmentDraftTask((string)$order->id, $isDelayed);
        }
    }

    /**
     * Returns whether Packlink draft should be created.
     *
     * @param \Order $order
     * @param \OrderState $orderState
     *
     * @return bool
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    private function shouldCreateDraft(\Order $order, OrderState $orderState)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();
        /** @var \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService $orderShipmentDetailsService */
        $orderShipmentDetailsService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService::CLASS_NAME
        );
        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService $carrierService */
        $carrierService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService::CLASS_NAME
        );
        $carrier = new Carrier((int)$order->id_carrier);
        $orderDetails = $orderShipmentDetailsService->getDetailsByOrderId((string)$order->id);
        $carrierServiceMapping = $carrierService->getMappingByCarrierReferenceId((int)$carrier->id_reference);

        return $orderDetails === null
            && $carrierServiceMapping !== null
            && ($orderState->id === self::PRESTASHOP_PAYMENT_ACCEPTED_STATUS
                || $orderState->id === self::PRESTASHOP_PROCESSING_IN_PROGRESS_STATUS);
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
            Context::getContext()->shop->id
        );
    }
}
