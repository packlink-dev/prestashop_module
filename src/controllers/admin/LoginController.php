<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\IntegrationRegistrationServiceInterface;
use Packlink\BusinessLogic\User\UserAccountService;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\BusinessLogic\Controllers\LoginController as BaseLoginController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class LoginController
 */
class LoginController extends PacklinkBaseController
{
    /**
     * Attempts to log the user in with the provided Packlink API key.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueStorageUnavailableException
     */
    public function displayAjaxLogin()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        $controller = new BaseLoginController(
            ServiceRegister::getService(UserAccountService::CLASS_NAME),
            ServiceRegister::getService(IntegrationRegistrationServiceInterface::CLASS_NAME),
            ServiceRegister::getService(Configuration::CLASS_NAME)
        );
        $result = $controller->login(!empty($data['apiKey']) ? $data['apiKey'] : '');

        $response = array('success' => $result['success']);

        if (!$result['success'] && !empty($result['errorCode'])) {
            $response['error'] = $result['errorCode'];
        }

        PacklinkPrestaShopUtility::dieJson($response);
    }
}
