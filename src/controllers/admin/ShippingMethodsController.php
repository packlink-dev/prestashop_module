<?php

use Logeecom\Infrastructure\ServiceRegister;
use Logeecom\Infrastructure\TaskExecution\QueueItem;
use Packlink\BusinessLogic\Controllers\DTO\ShippingMethodConfiguration;
use Packlink\BusinessLogic\Controllers\DTO\ShippingMethodResponse;
use Packlink\BusinessLogic\Controllers\ShippingMethodController;
use Packlink\BusinessLogic\Controllers\UpdateShippingServicesTaskStatusController;
use Packlink\BusinessLogic\ShippingMethod\Interfaces\ShopShippingMethodService;
use Packlink\BusinessLogic\Tax\TaxClass;
use Packlink\PrestaShop\Classes\BusinessLogicServices\CarrierService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ShippingMethodsController
 */
class ShippingMethodsController extends PacklinkBaseController
{
    /**
     * @var ShippingMethodController
     */
    protected $controller;

    /**
     * ShippingMethodsController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        $this->controller = new ShippingMethodController();
    }

    /**
     * Retrieves all shipping methods.
     */
    public function displayAjaxGetAll()
    {
        $shippingMethods = $this->controller->getAll();

        PacklinkPrestaShopUtility::dieDtoEntities($shippingMethods);
    }

    /**
     * Retrieves all shipping methods.
     */
    public function displayAjaxGetTaskStatus()
    {
        $status = QueueItem::FAILED;
        try {
            $controller = new UpdateShippingServicesTaskStatusController();
            $status = $controller->getLastTaskStatus();
        } catch (\Logeecom\Infrastructure\Exceptions\BaseException $e) {
        }

        PacklinkPrestaShopUtility::dieJson(array('status' => $status));
    }

    /**
     * Activates shipping method.
     */
    public function displayAjaxActivate()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        $this->activateShippingMethod(array_key_exists('id', $data) ? $data['id'] : 0);

        PacklinkPrestaShopUtility::dieJson(array('message' => $this->l('Shipping method successfully selected.')));
    }

    /**
     * Deactivates shipping method.
     */
    public function displayAjaxDeactivate()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        if (!$data['id'] || !$this->controller->deactivate((int)$data['id'])) {
            PacklinkPrestaShopUtility::die400(
                array('message' => $this->l('Failed to deselect shipping method.'))
            );
        }

        PacklinkPrestaShopUtility::dieJson(
            array('message' => $this->l('Shipping method successfully deselected.'))
        );
    }

    /**
     * Handles saving shipping method.
     */
    public function displayAjaxSave()
    {
        $configuration = $this->getShippingMethodConfigurationFromRequest();

        if (\Tools::strlen($configuration->name) > 64) {
            PacklinkPrestaShopUtility::die400(
                array(
                    'message' => $this->l('Title can have at most 64 characters.'),
                )
            );
        }

        /** @var ShippingMethodResponse $model */
        $model = $this->controller->save($configuration);
        if ($model === null) {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to save shipping method.')));
        }

        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        $model->logoUrl = $carrierService->getCarrierLogoFilePath($model->carrierName);

        $this->activateShippingMethod($model->id);

        $model->selected = true;

        PacklinkPrestaShopUtility::dieJson($model->toArray());
    }

    /**
     * Retrieves number of shop shipping methods.
     */
    public function displayAjaxGetNumberShopMethods()
    {
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);

        PacklinkPrestaShopUtility::dieJson(array('count' => $carrierService->getNumberOfOtherCarriers()));
    }

    /**
     * Disables shop shipping methods.
     */
    public function displayAjaxDisableShopShippingMethods()
    {
        /** @var CarrierService $carrierService */
        $carrierService = ServiceRegister::getService(ShopShippingMethodService::CLASS_NAME);
        if ($carrierService->disableOtherCarriers()) {
            PacklinkPrestaShopUtility::dieJson(array('message' => $this->l('Successfully disabled shipping methods.')));
        } else {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to disable shipping methods.')));
        }
    }

    /**
     * Retrieves available tax classes.
     */
    public function displayAjaxGetAvailableTaxClasses()
    {
        try {
            $taxRules = TaxRulesGroup::getTaxRulesGroups();
        } catch (PrestaShopDatabaseException $e) {
            $taxRules = array();
        }

        $taxClasses = array();
        try {
            $taxClasses = $this->formatTaxClasses($taxRules);
        } catch (\Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException $e) {
            PacklinkPrestaShopUtility::die400WithValidationErrors($e->getValidationErrors());
        }

        PacklinkPrestaShopUtility::dieDtoEntities($taxClasses);
    }

    private function activateShippingMethod($id)
    {
        if (!$id || !$this->controller->activate((int)$id)) {
            PacklinkPrestaShopUtility::die400(array('message' => $this->l('Failed to activate shipping method.')));
        }
    }

    /**
     * Returns tax classes for the provided tax rules.
     *
     * @param $taxRules
     *
     * @return TaxClass[]
     *
     * @throws \Packlink\BusinessLogic\DTO\Exceptions\FrontDtoValidationException
     */
    private function formatTaxClasses($taxRules)
    {
        $taxClasses = array(
            TaxClass::fromArray(array(
                'value' => CarrierService::DEFAULT_TAX_CLASS,
                'label' => $this->l(CarrierService::DEFAULT_TAX_CLASS_LABEL),
            )),
        );

        foreach ($taxRules as $taxRule) {
            $taxClasses[] = TaxClass::fromArray(array(
                'value' => $taxRule['id_tax_rules_group'],
                'label' => $taxRule['name'],
            ));
        }

        return $taxClasses;
    }

    /**
     * Retrieves shipping configuration.
     *
     * @return ShippingMethodConfiguration
     */
    private function getShippingMethodConfigurationFromRequest()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();

        return ShippingMethodConfiguration::fromArray($data);
    }
}
