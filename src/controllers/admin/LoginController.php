<?php

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
        $controller = new BaseLoginController();
        $status = $controller->login(!empty($data['apiKey']) ? $data['apiKey'] : '');

        $response = array(
            'success' => $status,
        );

        if (!$status && method_exists($controller, 'getLastErrorCode')) {
            $response['error'] = $controller->getLastErrorCode();
        }

        PacklinkPrestaShopUtility::dieJson($response);
    }
}
