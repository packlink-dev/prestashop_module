<?php

namespace Packlink\PrestaShop\Tests;

use Logeecom\Infrastructure\ServiceRegister;
use Packlink\BusinessLogic\Configuration;
use Packlink\PrestaShop\Classes\BusinessLogicServices\ConfigurationService;
use PHPUnit\Framework\TestCase;

/**
 * Class ConfigurationServiceTest.
 *
 * @package Packlink\PrestaShop\Tests
 */
class ConfigurationServiceTest extends TestCase
{
    /** @var Configuration */
    public $configService;

    public function setUp()
    {
        $this->configService = ConfigurationSErvice::getInstance();
        $me = $this;
        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () use ($me) {
                return $me->configService;
            }
        );
    }

    public function testCorrectVersion()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertEquals($composer['version'], $this->configService->getModuleVersion());
        $this->assertEquals('prestashop_2', $this->configService->getECommerceName());
    }
}
