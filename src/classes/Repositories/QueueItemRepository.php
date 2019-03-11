<?php
/**
 * 2019 Packlink
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    Packlink <support@packlink.com>
 * @copyright 2019 Packlink Shipping S.L
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace Packlink\PrestaShop\Classes\Repositories;

use Logeecom\Infrastructure\ORM\Interfaces\QueueItemRepository as QueueItemRepositoryInterface;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException;
use Logeecom\Infrastructure\TaskExecution\QueueItem;

class QueueItemRepository extends BaseRepository implements QueueItemRepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;

    /**
     * Finds list of earliest queued queue items per queue. Following list of criteria for searching must be satisfied:
     *      - Queue must be without already running queue items
     *      - For one queue only one (oldest queued) item should be returned
     *
     * @param int $limit Result set limit. By default max 10 earliest queue items will be returned
     *
     * @return QueueItem[] Found queue item list
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \PrestaShopException
     */
    public function findOldestQueuedItems($limit = 10)
    {
        $queuedItems = array();

        try {
            $runningQueueNames = $this->getRunningQueueNames();
            $queuedItems = $this->getQueuedItems($runningQueueNames, $limit);
        } catch (\PrestaShopDatabaseException $exception) {
            // In case of database exception return empty result set.
        }

        return $queuedItems;
    }

    /**
     * Creates or updates given queue item. If queue item id is not set, new queue item will be created otherwise
     * update will be performed.
     *
     * @param QueueItem $queueItem Item to save
     * @param array $additionalWhere List of key/value pairs that must be satisfied upon saving queue item. Key is
     *  queue item property and value is condition value for that property. Example for MySql storage:
     *  $storage->save($queueItem, array('status' => 'queued')) should produce query
     *  UPDATE queue_storage_table SET .... WHERE .... AND status => 'queued'
     *
     * @return int Id of saved queue item
     * @throws QueueItemSaveException if queue item could not be saved
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function saveWithCondition(QueueItem $queueItem, array $additionalWhere = array())
    {
        $savedItemId = null;
        try {
            $itemId = $queueItem->getId();
            if ($itemId === null || $itemId <= 0) {
                $savedItemId = $this->save($queueItem);
            } else {
                $this->updateQueueItem($queueItem, $additionalWhere);
            }
        } catch (\PrestaShopDatabaseException $exception) {
            throw new QueueItemSaveException(
                'Failed to save queue item. SQL error: ' . \Db::getInstance()->getMsgError(),
                0,
                $exception
            );
        }

        return $savedItemId ?: $itemId;
    }

    /**
     * Updates queue item.
     *
     * @param QueueItem $queueItem
     * @param array $additionalWhere
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \Logeecom\Infrastructure\TaskExecution\Exceptions\QueueItemSaveException
     * @throws \PrestaShopDatabaseException
     */
    private function updateQueueItem($queueItem, array $additionalWhere)
    {
        $filter = new QueryFilter();
        $filter->where('id', Operators::EQUALS, $queueItem->getId());

        foreach ($additionalWhere as $name => $value) {
            $filter->where($name, Operators::EQUALS, $value === null ? '' : $value);
        }

        /** @var QueueItem $item */
        $item = $this->selectOne($filter);
        if ($item === null) {
            throw new QueueItemSaveException("Can not update queue item with id {$queueItem->getId()} .");
        }

        $this->update($queueItem);
    }

    /**
     * Returns names of queues containing items that are currently in progress.
     *
     * @return array Names of queues containing items that are currently in progress.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \PrestaShopDatabaseException
     */
    private function getRunningQueueNames()
    {
        $filter = new QueryFilter();
        $filter->where('status', Operators::EQUALS, pSQL(QueueItem::IN_PROGRESS));
        $filter->setLimit(10000);

        /** @var QueueItem[] $runningQueueItems */
        $runningQueueItems = $this->select($filter);

        return array_map(
            function (QueueItem $runningQueueItem) {
                return $runningQueueItem->getQueueName();
            },
            $runningQueueItems
        );
    }

    /**
     * Returns all queued items.
     *
     * @param array $runningQueueNames Array of queues containing items that are currently in progress.
     * @param int $limit Maximum number of records that can be retrieved.
     *
     * @return QueueItem[] Array of queued items.
     *
     * @throws \PrestaShopException
     */
    private function getQueuedItems(array $runningQueueNames, $limit)
    {
        $queuedItems = array();
        $queueNameIndex = $this->getIndexMapping('queueName');

        try {
            $condition = sprintf(
                ' %s',
                $this->buildWhereString(array(
                    'type' => 'QueueItem',
                    $this->getIndexMapping('status') => QueueItem::QUEUED,
                ))
            );

            if (!empty($runningQueueNames)) {
                $condition .= sprintf(
                    ' AND ' . $queueNameIndex . " NOT IN ('%s')",
                    implode("', '", array_map('pSQL', $runningQueueNames))
                );
            }

            $queueNamesQuery = new \DbQuery();
            $queueNamesQuery->select($queueNameIndex . ', MIN(id) AS id')
                ->from(static::TABLE_NAME)
                ->where($condition)
                ->groupBy($queueNameIndex)
                ->limit($limit);

            $query = 'SELECT queueTable.id,queueTable.data'
                . ' FROM (' . $queueNamesQuery->build() . ') AS queueView'
                . ' INNER JOIN ' . bqSQL(_DB_PREFIX_ . static::TABLE_NAME) . ' AS queueTable'
                . ' ON queueView.id = queueTable.id';

            $records = \Db::getInstance()->executeS($query);
            $queuedItems = $this->unserializeEntities($records);
        } catch (\PrestaShopDatabaseException $exception) {
            // In case of exception return empty result set
        }

        return $queuedItems;
    }

    /**
     * Build properly escaped where condition string based on given key/value parameters.
     * String parameters will be sanitized with pSQL method call and other fields will be cast to integer values
     *
     * @param array $whereFields Key value pairs of where condition
     *
     * @return string Properly sanitized where condition string
     */
    private function buildWhereString(array $whereFields = array())
    {
        $where = array();
        foreach ($whereFields as $field => $value) {
            $where[] = bqSQL($field) . Operators::EQUALS . "'" . pSQL($value) . "'";
        }

        return implode(' AND ', $where);
    }
}
