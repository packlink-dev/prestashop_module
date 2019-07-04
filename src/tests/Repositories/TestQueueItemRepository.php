<?php

namespace Packlink\PrestaShop\Tests\Repositories;

use Packlink\PrestaShop\Classes\Repositories\QueueItemRepository;

class TestQueueItemRepository extends QueueItemRepository
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'packlink_test';
}
