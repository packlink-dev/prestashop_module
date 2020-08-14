<?php

use Packlink\PrestaShop\Classes\Utility\PacklinkPrestaShopUtility;

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
