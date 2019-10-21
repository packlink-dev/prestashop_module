<?php

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\PrestaShop\Classes\Bootstrap;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;

/**
 * Class PacklinkBaseController.
 */
class PacklinkBaseController extends ModuleAdminController
{
    /**
     * @var ConfigurationService
     */
    private $configService;

    /**
     * DebugController constructor.
     *
     * @throws \PrestaShopException
     */
    public function __construct()
    {
        parent::__construct();

        Bootstrap::init();

        $this->bootstrap = true;
    }

    /**
     * Retrieves configuration service.
     *
     * @return ConfigurationService Configuration service instance.
     */
    protected function getConfigService()
    {
        if ($this->configService === null) {
            $this->configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        }

        return $this->configService;
    }

    /**
     * @noinspection SenselessProxyMethodInspection
     *
     * Added to suppress warning.
     *
     * @inheritDoc
     */
    protected function l($string, $class = null, $addSlashes = false, $htmlEntities = true)
    {
        /** @noinspection PhpDeprecationInspection */
        return parent::l($string, $class, $addSlashes, $htmlEntities);
    }
}
