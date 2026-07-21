<?php

namespace Packlink\PrestaShop\Tests\Tasks;

use Packlink\PrestaShop\Classes\Tasks\UpgradeShopOrderDetailsTask;
use PHPUnit\Framework\TestCase;

/**
 * Class UpgradeShopOrderDetailsTaskTest.
 *
 * Guards the V2 BusinessTask serialization contract for the migration task: the task is stored to a
 * LONGTEXT row and re-hydrated by the TaskExecutor, so toArray()/fromArray() must round-trip losslessly.
 *
 * @package Packlink\PrestaShop\Tests\Tasks
 */
class UpgradeShopOrderDetailsTaskTest extends TestCase
{
    /**
     * @return array
     */
    private function sampleOrders()
    {
        return array(
            array('id_order' => 1, 'draft_reference' => 'REF1', 'date_add' => '2026-07-01 10:00:00'),
            array('id_order' => 2, 'draft_reference' => 'REF2', 'date_add' => '2026-07-02 11:30:00'),
        );
    }

    public function testToArrayHoldsTheOrdersAndCounts()
    {
        $orders = $this->sampleOrders();

        $task = new UpgradeShopOrderDetailsTask($orders);
        $data = $task->toArray();

        $this->assertSame($orders, $data['ordersToSync']);
        $this->assertSame(2, $data['numberOfOrders']);
        $this->assertArrayHasKey('batchSize', $data);
        $this->assertArrayHasKey('currentProgress', $data);
    }

    public function testSerializationRoundTrip()
    {
        $task = new UpgradeShopOrderDetailsTask($this->sampleOrders());
        $data = $task->toArray();

        $restored = UpgradeShopOrderDetailsTask::fromArray($data);

        $this->assertInstanceOf(UpgradeShopOrderDetailsTask::class, $restored);
        $this->assertEquals($data, $restored->toArray());
    }

    public function testEmptyOrderSetRoundTrips()
    {
        $task = new UpgradeShopOrderDetailsTask(array());
        $data = $task->toArray();

        $this->assertSame(0, $data['numberOfOrders']);
        $this->assertSame($data, UpgradeShopOrderDetailsTask::fromArray($data)->toArray());
    }
}
