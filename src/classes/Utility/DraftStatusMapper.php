<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Packlink\BusinessLogic\ShipmentDraft\Utility\DraftStatus;

/**
 * Class DraftStatusMapper.
 *
 * Maps core V2 draft statuses to the display status the admin order templates expect.
 *
 * @package Packlink\PrestaShop\Classes\Utility
 */
class DraftStatusMapper
{
    /**
     * Display status for a draft that is still being created; the order-draft template renders it as
     * an in-progress spinner.
     */
    const QUEUED = 'queued';

    /**
     * Collapses the "being created" draft states (PROCESSING, DELAYED) to a single 'queued' display
     * status, and passes every other status through unchanged.
     *
     * @param string $status A core DraftStatus value.
     *
     * @return string
     */
    public static function toDisplayStatus($status)
    {
        return in_array($status, array(DraftStatus::PROCESSING, DraftStatus::DELAYED), true)
            ? self::QUEUED
            : $status;
    }
}
