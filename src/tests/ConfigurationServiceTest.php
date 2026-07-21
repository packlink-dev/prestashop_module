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

    /**
     * Uses the annotation-based @before hook (not setUp()) so the fixture signature stays compatible
     * across PHPUnit 4/9/11 without a : void return type.
     *
     * @before
     */
    protected function before()
    {
        $this->configService = ConfigurationService::getInstance();
        $me = $this;
        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            function () use ($me) {
                return $me->configService;
            }
        );
    }

    /**
     * Version lockstep: the version declared in composer.json must match the version the module
     * reports at runtime, and the e-commerce identifier sent to Packlink must stay 'prestashop_2'.
     */
    public function testCorrectVersion()
    {
        $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);

        $this->assertEquals($composer['version'], $this->configService->getModuleVersion());
        $this->assertEquals('prestashop_2', $this->configService->getECommerceName());
    }
}
