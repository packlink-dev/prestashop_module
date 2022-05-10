<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;
use Packlink\PrestaShop\Classes\Utility\SystemInfoUtility;
use Packlink\BusinessLogic\Controllers\DebugController as BaseDebugController;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

class DebugController extends PacklinkBaseController
{
    const SYSTEM_INFO_FILE_NAME = 'packlink-debug-data.zip';

    /** @var BaseDebugController */
    private $baseController;

    public function __construct()
    {
        parent::__construct();

        $this->baseController = new BaseDebugController();
    }

    /**
     * Retrieves debug mode status.
     *
     * @throws \PrestaShopException
     */
    public function displayAjaxGetStatus()
    {
        PacklinkPrestaShopUtility::dieJson(array(
            'status' => $this->baseController->getStatus(),
            'downloadUrl' => $this->getAction('Debug', 'getSystemInfo'),
        ));
    }

    /**
     * Sets debug mode status.
     */
    public function displayAjaxSetStatus()
    {
        $data = PacklinkPrestaShopUtility::getPacklinkPostData();
        if (!isset($data['status']) || !is_bool($data['status'])) {
            PacklinkPrestaShopUtility::die400();
        }

        $this->baseController->setStatus($data['status']);

        PacklinkPrestaShopUtility::dieJson(array('status' => $data['status']));
    }

    /**
     * Downloads system info zip file.
     *
     * @throws \PrestaShopException
     */
    public function displayAjaxGetSystemInfo()
    {
        $file = SystemInfoUtility::getSystemInfo();

        PacklinkPrestaShopUtility::dieFile($file, self::SYSTEM_INFO_FILE_NAME);
    }

    /**
     * Tests cURL request to the async process controller.
     *
     * @return void
     */
    public function displayAjaxTestCurl()
    {
        /** @var \Logeecom\Infrastructure\Configuration\Configuration $config */
        $config = ServiceRegister::getService( \Logeecom\Infrastructure\Configuration\Configuration::CLASS_NAME );
        $url    = $config->getAsyncProcessUrl( 'test' );

        $curl    = curl_init();
        $verbose = fopen( 'php://temp', 'wb+' );
        /** @noinspection CurlSslServerSpoofingInspection */
        curl_setopt_array(
            $curl,
            array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HEADER         => true,
                // CURLOPT_SSLVERSION      => CURL_SSLVERSION_TLSv1_0,
                // CURLOPT_HTTP_VERSION    => CURL_HTTP_VERSION_1_1,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 2,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_VERBOSE        => true,
                CURLOPT_STDERR         => $verbose,
                // CURLOPT_SSL_CIPHER_LIST => 'TLSv1.2',
                CURLOPT_HTTPHEADER     => array(
                    'Cache-Control: no-cache',
                ),
            )
        );

        $response = curl_exec( $curl );

        rewind( $verbose );
        echo '<pre>', stream_get_contents( $verbose );

        curl_close( $curl );

        echo $response;

        echo '</pre>';
        exit;
    }
}
