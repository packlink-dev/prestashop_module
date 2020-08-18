<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

/** @noinspection PhpIncludeInspection */
require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

/**
 * Class ShippingZonesController
 */
class ShippingZonesController extends PacklinkBaseController
{
    /**
     * Returns available shipping zones.
     */
    public function displayAjaxGetShippingZones()
    {
        $zones = Zone::getZones(true);

        $result = array_map(
            static function ($zone) {
                return array(
                    'value' => (string)$zone['id_zone'],
                    'label' => $zone['name'],
                );
            },
            $zones
        );

        $result = array_values($result);

        PacklinkPrestaShopUtility::dieJson($result);
    }
}
