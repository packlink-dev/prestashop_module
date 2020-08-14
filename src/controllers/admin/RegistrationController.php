<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\RegistrationController as BaseRegistrationController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class RegistrationController
 */
class RegistrationController extends PacklinkBaseController
{
    /**
     * @var BaseRegistrationController
     */
    private $baseController;

    public function __construct()
    {
        parent::__construct();

        $this->baseController = new BaseRegistrationController();
    }

    /**
     * Array that identifies e-commerce.
     *
     * @var string[]
     */
    protected static $ecommerceIdentifiers = array('Prestashop');

    /**
     * Returns registration data.
     */
    public function displayAjaxGetRegisterData()
    {
        $country = Tools::getValue('country');

        if (empty($country)) {
            PacklinkPrestaShopUtility::die404(array('message' => 'Not found'));
        }

        PacklinkPrestaShopUtility::dieJson($this->baseController->getRegisterData($country));
    }

    /**
     * Register the user on Packlink.
     */
    public function displayAjaxRegister()
    {
        $payload = PacklinkPrestaShopUtility::getPacklinkPostData();

        $payload['ecommerces'] = static::$ecommerceIdentifiers;

        try {
            $status = $this->baseController->register($payload);
            PacklinkPrestaShopUtility::dieJson(array('success' => $status));
        } catch (Exception $e) {
            PacklinkPrestaShopUtility::dieJson(
                array(
                    'success' => false,
                    'error' => $e->getMessage(),
                )
            );
        }
    }
}
