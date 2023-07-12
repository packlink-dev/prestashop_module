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
        $this->version = '3.2.15';
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
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     */
    public function install()
    {
        $installer = new \Packlink\PrestaShop\Classes\Utility\PacklinkInstaller($this);
        $previousShopContext = Shop::getContext();
        Shop::setContext(Shop::CONTEXT_ALL);

        $result = $installer->initializePlugin() && parent::install() && $installer->addControllersAndHooks();

        \Packlink\PrestaShop\Classes\BusinessLogicServices\CleanupTaskSchedulerService::scheduleTaskCleanupTask();

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
     * Hook for the new translation system in PrestaShop 1.7.7.
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem()
    {
        return version_compare(_PS_VERSION_, '1.7.7.0', '>=');
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
            'configuration' => $configuration,
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
                array('configuration' => $configuration)
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
     * Adds Packlink styles and scripts to the order overview page.
     *
     * @param array $params
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->context->controller->php_self === 'AdminOrders') {
            $this->context->controller->addCSS(
                array(
                    $this->_path . 'views/css/packlink.css?v=' . $this->version,
                    $this->_path . 'views/css/packlink-order-overview.css?v=' . $this->version,
                ),
                'all',
                null,
                false
            );

            $this->context->controller->addJS(
                array(
                    $this->_path . 'views/js/OrderOverviewDraft.js?v=' . $this->version,
                    $this->_path . 'views/js/core/UtilityService.js?v=' . $this->version,
                    $this->_path . 'views/js/core/ResponseService.js?v=' . $this->version,
                    $this->_path . 'views/js/core/StateUUIDService.js?v=' . $this->version,
                    $this->_path . 'views/js/core/AjaxService.js?v=' . $this->version,
                    $this->_path . 'views/js/PrestaAjaxService.js?v=' . $this->version,
                    $this->_path . 'views/js/PrestaPrintShipmentLabels.js?v=' . $this->version,
                    $this->_path . 'views/js/PrestaCreateOrderDraft.js?v=' . $this->version,
                ),
                false
            );
        }
    }

    /**
     * Hook that is triggered the grid definition for orders is created.
     *
     * @param array $params
     */
    public function hookActionOrderGridDefinitionModifier($params)
    {
        if ($this->isUserLoggedToPacklink()) {
            /** @var \PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface $definition */
            $definition = $params['definition'];

            /** @var \PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection */
            $columns = $definition->getColumns();
            /** @var \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection $bulkActions */
            $bulkActions = $definition->getBulkActions();

            $draftColumn = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn('packlink_draft');
            $draftColumn->setName($this->trans('Packlink PRO Shipping'))
                ->setOptions(array(
                    'actions' => (new \PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection()),
                ));

            $labelColumn = new \PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn('packlink_label');
            $labelColumn->setName($this->trans('Packlink label'))
                ->setOptions(array(
                    'actions' => (new \PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection()),
                ));

            $bulkAction = new \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\ButtonBulkAction('packlink_bulk_print_labels');
            $bulkAction->setName($this->trans('Print Packlink PRO shipment labels'))
                ->setOptions(array(
                    'class' => 'open_tabs',
                    'attributes' => array(
                        'data-route-param-name' => 'orderId',
                    ),
                ));

            $columns->addAfter('payment', $draftColumn);
            $columns->addBefore('actions', $labelColumn);
            $bulkActions->add($bulkAction);

            $definition->setColumns($columns);
            $definition->setBulkActions($bulkActions);
        }
    }

    /**
     * Hook that is triggered the grid definition for orders is being presented to the user.
     *
     * @param array $params
     *
     * @throws \PrestaShopException
     */
    public function hookActionOrderGridPresenterModifier($params)
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService $shipmentDetailsService */
        $shipmentDetailsService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\OrderShipmentDetails\OrderShipmentDetailsService::CLASS_NAME
        );
        /** @var \Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService $draftService */
        $draftService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\ShipmentDraft\ShipmentDraftService::CLASS_NAME
        );
        /** @var \Packlink\BusinessLogic\Order\OrderService $orderService */
        $orderService = \Logeecom\Infrastructure\ServiceRegister::getService(\Packlink\BusinessLogic\Order\OrderService::CLASS_NAME);

        $params['presented_grid']['data']['draftStatusUrl'] = $this->getAjaxControllerUrl('OrderDraft', 'getDraftStatus');
        $params['presented_grid']['data']['createDraftUrl'] = $this->getAjaxControllerUrl('OrderDraft', 'createOrderDraft');
        $params['presented_grid']['data']['printLabelsUrl'] = $this->context->link->getAdminLink('BulkShipmentLabels');
        $params['presented_grid']['data']['packlinkLogo'] = _PS_BASE_URL_ . _MODULE_DIR_ . 'packlink/logo.png';

        $records = $params['presented_grid']['data']['records']->all();
        foreach ($records as &$record) {
            $shipmentDetails = $shipmentDetailsService->getDetailsByOrderId((string)$record['id_order']);
            $draftStatus = $draftService->getDraftStatus((string)$record['id_order']);
            $status = $draftStatus->status === \Logeecom\Infrastructure\TaskExecution\QueueItem::IN_PROGRESS
                ? \Logeecom\Infrastructure\TaskExecution\QueueItem::QUEUED
                : $draftStatus->status;
            $draftCreated = $status === \Logeecom\Infrastructure\TaskExecution\QueueItem::COMPLETED && $shipmentDetails;
            $shipmentLabels = $shipmentDetails ? $shipmentDetails->getShipmentLabels() : array();

            $record['draftStatus'] = $status;
            $record['draftDeleted'] = $draftCreated ? $shipmentDetails->isDeleted() : false;
            $record['isLabelAvailable'] = $shipmentDetails ? $orderService->isReadyToFetchShipmentLabels($shipmentDetails->getStatus()) : false;
            $record['isLabelPrinted'] = !empty($shipmentLabels) && $shipmentLabels[0]->isPrinted();
            $record['draftLink'] = $draftCreated ? $shipmentDetails->getShipmentUrl() : '#';
        }

        $params['presented_grid']['data']['records'] = new \PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection($records);
    }

    /**
     * Displays order tab link.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function hookDisplayAdminOrderTabLink($params)
    {
        if ($this->isUserLoggedToPacklink()) {
            return $this->render($this->getModuleTemplatePath() . 'packlink_shipping_tab/shipping_tab.html.twig');
        }

        return '';
    }

    /**
     * Displays order tab content.
     *
     * @param array $params
     *
     * @return string
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function hookDisplayAdminOrderTabContent($params)
    {
        if ($this->isUserLoggedToPacklink()) {
            return $this->render(
                $this->getModuleTemplatePath() . 'packlink_shipping_content/shipping_content.html.twig',
                \Packlink\PrestaShop\Classes\Utility\AdminShippingTabDataProvider::getShippingContentData(
                    $this->context,
                    $this,
                    (string)$params['id_order']
                )
            );
        }

        return '';
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

        \Packlink\BusinessLogic\Configuration::setUICountryCode($this->context->language->iso_code);

        $this->context->smarty->assign(array(
            'lang' => $this->getTranslations(),
            'templates' => $this->getTemplates(),
            'urls' => $this->getUrls(),
            'stateUrl' => $this->getAction('ModuleState', 'getCurrentState'),
            'baseResourcesUrl' => $this->getPathUri() . 'views/img/core',
            'gridResizerScript' => $this->getPathUri() . 'views/js/core/GridResizerService.js?v=' . $this->version,
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
                $this->getPathUri() . 'views/css/app.css?v=' . $this->version,
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
                $this->getPathUri() . 'views/js/core/UtilityService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/TemplateService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/AjaxService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/TranslationService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ValidationService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ShippingServicesRenderer.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/AutoTestController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ConfigurationController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/DefaultParcelController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/DefaultWarehouseController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/EditServiceController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/SingleStorePricePolicyController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/LoginController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ModalService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/MyShippingServicesController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/OnboardingOverviewController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/OnboardingStateController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/OnboardingWelcomeController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/OrderStatusMappingController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/PageControllerFactory.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/PickShippingServiceController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/PricePolicyController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/RegisterController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/RegisterModalController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ResponseService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/ServiceCountriesModalController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/StateController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/SystemInfoController.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/StateUUIDService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/PrestaAjaxService.js?v=' . $this->version,
                $this->getPathUri() . 'views/js/core/SettingsButtonService.js?v=' . $this->version,
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
     * Returns JSON encoded module page translations in the default language and some module-specific translations.
     *
     * @return string
     */
    private function getDefaultTranslations()
    {
        /** @var \Packlink\BusinessLogic\CountryLabels\CountryService $countryService */
        $countryService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\CountryLabels\Interfaces\CountryService::CLASS_NAME
        );
        $labels = $countryService->getAllLabels('en');
        $defaultTranslations = $labels['en'];

        $zonesDescription = 'Select the availability for the zones that are supported for your shipping service.';
        $selectOneZone = 'Select availability of at least one zone and add as many required';

        $defaultTranslations['shippingServices']['serviceCountriesTitle'] = 'Availability by destination zone';
        $defaultTranslations['shippingServices']['serviceCountriesDescription'] = $zonesDescription;
        $defaultTranslations['shippingServices']['openCountries'] = 'See zones';
        $defaultTranslations['shippingServices']['allCountriesSelected'] = 'All zones selected';
        $defaultTranslations['shippingServices']['oneCountrySelected'] = 'One zone selected';
        $defaultTranslations['shippingServices']['selectedCountries'] = '%s zones selected.';
        $defaultTranslations['shippingServices']['selectAllCountries'] = 'All selected zones';
        $defaultTranslations['shippingServices']['selectCountriesHeader'] = 'Zones supported for your shipping service';
        $defaultTranslations['shippingServices']['selectCountriesSubheader'] = $selectOneZone;
        $defaultTranslations['shippingServices']['atLeastOneCountry'] = 'At least one zone must be selected.';

        return json_encode($defaultTranslations);
    }

    /**
     * Returns JSON encoded module page translations in the current language and some module-specific translations.
     *
     * @return string
     */
    private function getCurrentTranslations()
    {
        $locale = Tools::strtolower($this->context->language->iso_code);

        /** @var \Packlink\BusinessLogic\CountryLabels\CountryService $countryService */
        $countryService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\CountryLabels\Interfaces\CountryService::CLASS_NAME
        );
        $labels = $countryService->getAllLabels($locale);
        $currentTranslations = $labels[$locale];

        if (!empty($currentTranslations)) {
            $currentTranslations['shippingServices']['serviceCountriesTitle'] = $this->l(
                'Availability by destination zone'
            );
            $currentTranslations['shippingServices']['serviceCountriesDescription'] = $this->l(
                'Select the availability for the zones that are supported for your shipping service.'
            );
            $currentTranslations['shippingServices']['openCountries'] = $this->l('See zones');
            $currentTranslations['shippingServices']['allCountriesSelected'] = $this->l('All zones selected');
            $currentTranslations['shippingServices']['oneCountrySelected'] = $this->l('One zone selected');
            $currentTranslations['shippingServices']['selectedCountries'] = $this->l('%s zones selected.');
            $currentTranslations['shippingServices']['selectAllCountries'] = $this->l('All selected zones');
            $currentTranslations['shippingServices']['selectCountriesHeader'] = $this->l(
                'Zones supported for your shipping service'
            );
            $currentTranslations['shippingServices']['selectCountriesSubheader'] = $this->l(
                'Select availability of at least one zone and add as many required'
            );
            $currentTranslations['shippingServices']['atLeastOneCountry'] = $this->l(
                'At least one zone must be selected.'
            );
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
        $baseDir = $this->getLocalPath() . 'views/templates/core/';

        return array(
            'pl-login-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'login.html'),
            ),
            'pl-register-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'register.html'),
            ),
            'pl-register-modal' => Tools::file_get_contents($baseDir . 'register-modal.html'),
            'pl-onboarding-welcome-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'onboarding-welcome.html'),
            ),
            'pl-onboarding-overview-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'onboarding-overview.html'),
            ),
            'pl-default-parcel-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'default-parcel.html'),
            ),
            'pl-default-warehouse-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'default-warehouse.html'),
            ),
            'pl-configuration-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'configuration.html'),
                'pl-header-section' => '',
            ),
            'pl-order-status-mapping-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'order-status-mapping.html'),
                'pl-header-section' => '',
            ),
            'pl-system-info-modal' => Tools::file_get_contents($baseDir . 'system-info-modal.html'),
            'pl-my-shipping-services-page' => array(
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'my-shipping-services.html'),
                'pl-header-section' => Tools::file_get_contents($baseDir . 'shipping-services-header.html'),
                'pl-shipping-services-table' => Tools::file_get_contents($baseDir . 'shipping-services-table.html'),
                'pl-shipping-services-list' => Tools::file_get_contents($baseDir . 'shipping-services-list.html'),
            ),
            'pl-disable-carriers-modal' => Tools::file_get_contents($baseDir . 'disable-carriers-modal.html'),
            'pl-pick-service-page' => array(
                'pl-header-section' => '',
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'pick-shipping-services.html'),
                'pl-shipping-services-table' => Tools::file_get_contents($baseDir . 'shipping-services-table.html'),
                'pl-shipping-services-list' => Tools::file_get_contents($baseDir . 'shipping-services-list.html'),
            ),
            'pl-edit-service-page' => array(
                'pl-header-section' => '',
                'pl-main-page-holder' => Tools::file_get_contents($baseDir . 'edit-shipping-service.html'),
                'pl-pricing-policies' => Tools::file_get_contents($baseDir . 'pricing-policies-list.html'),
            ),
            'pl-pricing-policy-modal' => Tools::file_get_contents($baseDir . 'pricing-policy-modal.html'),
            'pl-countries-selection-modal' => Tools::file_get_contents($baseDir . 'countries-selection-modal.html'),
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
                'logoPath' => '', // Not used. Logos are retrieved based on the base resource url.
            ),
            'register' => array(
                'getRegistrationData' => $this->getAction('Registration', 'getRegisterData'),
                'submit' => $this->getAction('Registration', 'register'),
            ),
            'onboarding-state' => array(
                'getState' => $this->getAction('Onboarding', 'getCurrentState'),
            ),
            'onboarding-welcome' => array(),
            'onboarding-overview' => array(
                'defaultParcelGet' => $this->getAction('DefaultParcel', 'getDefaultParcel'),
                'defaultWarehouseGet' => $this->getAction('DefaultWarehouse', 'getDefaultWarehouse'),
            ),
            'default-parcel' => array(
                'getUrl' => $this->getAction('DefaultParcel', 'getDefaultParcel'),
                'submitUrl' => $this->getAction('DefaultParcel', 'submitDefaultParcel'),
            ),
            'default-warehouse' => array(
                'getUrl' => $this->getAction('DefaultWarehouse', 'getDefaultWarehouse'),
                'getSupportedCountriesUrl' => $this->getAction(
                    'DefaultWarehouse',
                    'getSupportedCountries'
                ),
                'submitUrl' => $this->getAction('DefaultWarehouse', 'submitDefaultWarehouse'),
                'searchPostalCodesUrl' => $this->getAction('DefaultWarehouse', 'searchPostalCodes'),
            ),
            'configuration' => array(
                'getDataUrl' => $this->getAction('Configuration', 'getData'),
            ),
            'system-info' => array(
                'getStatusUrl' => $this->getAction('Debug', 'getStatus'),
                'setStatusUrl' => $this->getAction('Debug', 'setStatus'),
            ),
            'order-status-mapping' => array(
                'getMappingAndStatusesUrl' => $this->getAction(
                    'OrderStateMapping',
                    'getMappingsAndStatuses'
                ),
                'setUrl' => $this->getAction('OrderStateMapping', 'saveMappings'),
            ),
            'my-shipping-services' => array(
                'getServicesUrl' => $this->getAction('ShippingMethods', 'getActive'),
                'deleteServiceUrl' => $this->getAction('ShippingMethods', 'deactivate'),
                'getCurrencyDetailsUrl' => $this->getAction('SystemInfo', 'get'),
                'systemId' => (string)\Context::getContext()->shop->id,
            ),
            'pick-shipping-service' => array(
                'getActiveServicesUrl' => $this->getAction('ShippingMethods', 'getActive'),
                'getServicesUrl' => $this->getAction('ShippingMethods', 'getInactive'),
                'getTaskStatusUrl' => $this->getAction('ShippingMethods', 'getTaskStatus'),
                'startAutoConfigureUrl' => $this->getAction('PacklinkAutoConfigure', 'start'),
                'disableCarriersUrl' => $this->getAction('ShippingMethods', 'disableShopShippingMethods'),
                'getCurrencyDetailsUrl' => $this->getAction('SystemInfo', 'get'),
                'systemId' => (string)\Context::getContext()->shop->id,
            ),
            'edit-service' => array(
                'getServiceUrl' => $this->getAction('ShippingMethods', 'getShippingMethod'),
                'saveServiceUrl' => $this->getAction('ShippingMethods', 'save'),
                'getTaxClassesUrl' => $this->getAction('ShippingMethods', 'getAvailableTaxClasses'),
                'getCountriesListUrl' => $this->getAction('ShippingZones', 'getShippingZones'),
                'getCurrencyDetailsUrl' => $this->getAction('SystemInfo', 'get'),
                'hasTaxConfiguration' => true,
                'hasCountryConfiguration' => true,
                'canDisplayCarrierLogos' => true,
            ),
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
            array('configuration' => $configuration)
        );

        $stylesPath = $this->_path . 'views/css/packlink-shipping-methods.css?v=' . $this->version;
        $output = "<link rel=\"stylesheet\" href=\"{$stylesPath}\"/>\n";

        $shippingServicePath = $this->_path . 'views/js/ShippingService16.js?v=' . $this->version;
        $output .= "<script src=\"{$shippingServicePath}\"></script>\n";

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
        $ajaxPath = $this->getPathUri() . 'views/js/core/AjaxService.js?v=' . $this->version;
        $output = "<script src=\"{$ajaxPath}\"></script>\n";

        $responsePath = $this->getPathUri() . 'views/js/core/ResponseService.js?v=' . $this->version;
        $output .= "<script src=\"{$responsePath}\"></script>\n";

        $stateUuidPath = $this->getPathUri() . 'views/js/core/StateUUIDService.js?v=' . $this->version;
        $output .= "<script src=\"{$stateUuidPath}\"></script>\n";

        $prestaAjaxPath = $this->_path . 'views/js/PrestaAjaxService.js?v=' . $this->version;
        $output .= "<script src=\"{$prestaAjaxPath}\"></script>\n";

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
     * Returns URL endpoint of ajax controller action.
     *
     * @param string $controller
     * @param string $action
     *
     * @return string
     *
     * @throws \PrestaShopException
     */
    private function getAjaxControllerUrl($controller, $action)
    {
        return $this->context->link->getAdminLink($controller) . '&' .
            http_build_query(
                array(
                    'ajax' => true,
                    'action' => $action,
                )
            );
    }

    /**
     * Returns whether the user has logged in with his/her auth token.
     *
     * @return bool
     */
    private function isUserLoggedToPacklink()
    {
        \Packlink\PrestaShop\Classes\Bootstrap::init();

        /** @var \Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService $configService */
        $configService = \Logeecom\Infrastructure\ServiceRegister::getService(
            \Packlink\BusinessLogic\Configuration::CLASS_NAME
        );

        $authToken = $configService->getAuthorizationToken();

        return !empty($authToken);
    }

    /**
     * Render a twig template.
     *
     * @param string $template
     * @param array $params
     *
     * @return string
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    private function render($template, $params = array())
    {
        /** @var Twig_Environment $twig */
        $twig = $this->get('twig');

        return $twig->render($template, $params);
    }

    /**
     * Get path to this module's template directory
     */
    private function getModuleTemplatePath()
    {
        return sprintf('@Modules/%s/views/templates/admin/', $this->name);
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
