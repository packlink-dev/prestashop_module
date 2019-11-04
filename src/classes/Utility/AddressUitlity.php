<?php

namespace Packlink\PrestaShop\Classes\Utility;

class AddressUitlity
{
    /**
     * Creates drop-off address for a specific order based on the provided drop-off data.
     *
     * @param \Order $order
     * @param array $dropOffData
     *
     * @throws \PrestaShopException
     */
    public static function createDropOffAddress($order, $dropOffData)
    {
        $address = new \Address($order->id_address_delivery);
        $clone = clone $address;
        $clone->id = null;
        $clone->address1 = $dropOffData['address'];
        $clone->postcode = $dropOffData['zip'];
        $clone->city = $dropOffData['city'];
        $clone->company = $dropOffData['name'];
        $clone->alias = TranslationUtility::__('Drop-Off delivery address');
        $clone->other = TranslationUtility::__('Drop-Off delivery address');
        $clone->save();
        $order->id_address_delivery = $clone->id;

        $order->update();
    }
}