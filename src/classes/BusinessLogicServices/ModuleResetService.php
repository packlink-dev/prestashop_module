<?php

namespace Packlink\PrestaShop\Classes\BusinessLogicServices;

use Logeecom\Infrastructure\Logger\Logger;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\IntegrationRegistrationDataProviderInterface;
use Packlink\BusinessLogic\IntegrationRegistration\Interfaces\ModuleResetServiceInterface;

class ModuleResetService implements ModuleResetServiceInterface
{
    /** @var IntegrationRegistrationDataProviderInterface $dataProvider */
    private $dataProvider;

    public function __construct($dataProvider)
    {
        $this->dataProvider = $dataProvider;
    }

    /**
     * Erases integration data keeping the module installed
     * in the target shop system, available for a new Packlink connection.
     *
     * Nulls out integrationId, integrationGuid, and webhookSecret
     * from the database via ConfigurationService.
     *
     * @return bool
     */
    public function resetModule()
    {
        try {
            $this->dataProvider->deleteIntegrationData();
            $this->dataProvider->deleteToken();

            return true;
        } catch (\Exception $e) {
            Logger::logError(
                'Failed to reset module integration data: ' . $e->getMessage(),
                'Prestashop_module',
                array('trace' => $e->getTraceAsString())
            );

            return false;
        }
    }
}