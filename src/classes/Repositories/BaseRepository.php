<?php

namespace Packlink\PrestaShop\Classes\Repositories;

use Logeecom\Infrastructure\Logger\Logger;
use Logeecom\Infrastructure\ORM\Entity;
use Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException;
use Logeecom\Infrastructure\ORM\Interfaces\RepositoryInterface;
use Logeecom\Infrastructure\ORM\QueryFilter\Operators;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryCondition;
use Logeecom\Infrastructure\ORM\QueryFilter\QueryFilter;
use Logeecom\Infrastructure\ORM\Utility\IndexHelper;
use Packlink\PrestaShop\Classes\Utility\TranslationUtility;

/**
 * Class BaseRepository
 *
 * @package Packlink\PrestaShop\Classes\Repositories
 */
class BaseRepository implements RepositoryInterface
{
    /**
     * Fully qualified name of this class.
     */
    const THIS_CLASS_NAME = __CLASS__;
    /**
     * Name of the base entity table in database.
     */
    const TABLE_NAME = 'packlink_entity';
    /**
     * Number of indexes in Packlink entity table.
     */
    const NUMBER_OF_INDEXES = 7;
    /**
     * @var string
     */
    protected $entityClass;
    /**
     * @var array
     */
    private $indexMapping;

    /**
     * Returns full class name.
     *
     * @return string Full class name.
     */
    public static function getClassName()
    {
        return static::THIS_CLASS_NAME;
    }

    /**
     * Sets repository entity.
     *
     * @param string $entityClass Repository entity class.
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
    }

    /**
     * Executes select query.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity[] A list of resulting entities.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function select(QueryFilter $filter = null)
    {
        /** @var Entity $entity */
        $entity = new $this->entityClass;

        $fieldIndexMap = IndexHelper::mapFieldsToIndexes($entity);
        $groups = $filter ? $this->buildConditionGroups($filter, $fieldIndexMap) : array();
        $type = $entity->getConfig()->getType();

        $typeCondition = "type='" . pSQL($type) . "'";
        $whereCondition = $this->buildWhereCondition($groups, $fieldIndexMap);
        $result = $this->getRecordsByCondition(
            $typeCondition . (!empty($whereCondition) ? ' AND ' . $whereCondition : ''),
            $filter
        );

        return $this->unserializeEntities($result);
    }

    /**
     * Executes select query and returns first result.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return Entity|null First found entity or NULL.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function selectOne(QueryFilter $filter = null)
    {
        if ($filter === null) {
            $filter = new QueryFilter();
        }

        $filter->setLimit(1);
        $results = $this->select($filter);

        return empty($results) ? null : $results[0];
    }

    /**
     * Executes insert query and returns ID of created entity. Entity will be updated with new ID.
     *
     * @param Entity $entity Entity to be saved.
     *
     * @return int Identifier of saved entity.
     *
     * @throws \PrestaShopDatabaseException
     */
    public function save(Entity $entity)
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);
        $record['type'] = pSQL($entity->getConfig()->getType());

        $result = \Db::getInstance()->insert(static::TABLE_NAME, $record);

        if (!$result) {
            $message = TranslationUtility::__(
                'Entity %s cannot be inserted. Error: %s',
                array($entity->getConfig()->getType(), \Db::getInstance()->getMsgError())
            );
            Logger::logError($message);

            throw new \RuntimeException($message);
        }

        $entity->setId((int)\Db::getInstance()->Insert_ID());

        return $entity->getId();
    }

    /**
     * Counts records that match filter criteria.
     *
     * @param QueryFilter $filter Filter for query.
     *
     * @return int Number of records that match filter criteria.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    public function count(QueryFilter $filter = null)
    {
        return count($this->select($filter));
    }

    /**
     * Executes update query and returns success flag.
     *
     * @param Entity $entity Entity to be updated.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     */
    public function update(Entity $entity)
    {
        $indexes = IndexHelper::transformFieldsToIndexes($entity);
        $record = $this->prepareDataForInsertOrUpdate($entity, $indexes);

        $id = (int)$entity->getId();
        $result = \Db::getInstance()->update(static::TABLE_NAME, $record, "id = $id");
        if (!$result) {
            $message = TranslationUtility::__(
                'Entity %s with ID %d cannot be updated.',
                array(
                    $entity->getConfig()->getType(),
                    $id,
                )
            );
            Logger::logError($message);
        }

        return $result;
    }

    /**
     * Executes delete query and returns success flag.
     *
     * @param Entity $entity Entity to be deleted.
     *
     * @return bool TRUE if operation succeeded; otherwise, FALSE.
     */
    public function delete(Entity $entity)
    {
        $id = (int)$entity->getId();
        $result = \Db::getInstance()->delete(static::TABLE_NAME, "id = $id");

        if (!$result) {
            Logger::logError(
                TranslationUtility::__(
                    'Could not delete entity %s with ID %d.',
                    array(
                        $entity->getConfig()->getType(),
                        $entity->getId(),
                    )
                )
            );
        }

        return $result;
    }

    /**
     * Translates database records to Packlink entities.
     *
     * @param array $records Array of database records.
     *
     * @return Entity[]
     */
    protected function unserializeEntities($records)
    {
        $entities = array();
        foreach ($records as $record) {
            /** @var Entity $entity */
            $entity = $this->unserializeEntity($record['data']);
            if ($entity !== null) {
                $entity->setId((int)$record['id']);
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Returns index mapped to given property.
     *
     * @param string $property Property name.
     *
     * @return string Index column in Packlink entity table.
     */
    protected function getIndexMapping($property)
    {
        if ($this->indexMapping === null) {
            $this->indexMapping = IndexHelper::mapFieldsToIndexes(new $this->entityClass);
        }

        if (array_key_exists($property, $this->indexMapping)) {
            return 'index_' . $this->indexMapping[$property];
        }

        return null;
    }

    /**
     * Returns columns that should be in the result of a select query on Packlink entity table.
     *
     * @return array Select columns.
     */
    protected function getSelectColumns()
    {
        return array('id', 'data');
    }

    /**
     * Builds condition groups (each group is chained with OR internally, and with AND externally) based on query
     * filter.
     *
     * @param QueryFilter $filter Query filter object.
     * @param array $fieldIndexMap Map of property indexes.
     *
     * @return array Array of condition groups..
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function buildConditionGroups(QueryFilter $filter, array $fieldIndexMap)
    {
        $groups = array();
        $counter = 0;
        $fieldIndexMap['id'] = 0;
        foreach ($filter->getConditions() as $condition) {
            if (!empty($groups[$counter]) && $condition->getChainOperator() === 'OR') {
                $counter++;
            }

            // Only index columns can be filtered.
            if (!array_key_exists($condition->getColumn(), $fieldIndexMap)) {
                throw new QueryFilterInvalidParamException(
                    TranslationUtility::__('Field %s is not indexed!', array($condition->getColumn()))
                );
            }

            $groups[$counter][] = $condition;
        }

        return $groups;
    }

    /**
     * Builds WHERE statement of SELECT query by separating AND and OR conditions.
     * Output format: (C1 AND C2) OR (C3 AND C4) OR (C5 AND C6 AND C7)
     *
     * @param array $groups Array of condition groups.
     * @param array $fieldIndexMap Map of property indexes.
     *
     * @return string Fully formed WHERE statement.
     */
    private function buildWhereCondition(array $groups, array $fieldIndexMap)
    {
        $whereStatement = '';
        foreach ($groups as $groupIndex => $group) {
            $conditions = array();
            foreach ($group as $condition) {
                $conditions[] = $this->addCondition($condition, $fieldIndexMap);
            }

            $whereStatement .= '(' . implode(' AND ', $conditions) . ')';

            if (\count($groups) !== 1 && $groupIndex < count($groups) - 1) {
                $whereStatement .= ' OR ';
            }
        }

        return $whereStatement;
    }

    /**
     * Filters records by given condition.
     *
     * @param QueryCondition $condition Query condition object.
     * @param array $indexMap Map of property indexes.
     *
     * @return string A single WHERE condition.
     */
    private function addCondition(QueryCondition $condition, array $indexMap)
    {
        $column = $condition->getColumn();
        $columnName = $column === 'id' ? 'id' : 'index_' . $indexMap[$column];
        if ($column === 'id') {
            $conditionValue = (int)$condition->getValue();
        } else {
            $conditionValue = IndexHelper::castFieldValue($condition->getValue(), $condition->getValueType());
        }

        if (in_array($condition->getOperator(), array(Operators::NOT_IN, Operators::IN), true)) {
            $values = array_map(function ($item) {
                if (is_string($item)) {
                    return "'$item'";
                }

                if (is_int($item)) {
                    $val = IndexHelper::castFieldValue($item, 'integer');
                    return "'{$val}'";
                }

                $val = IndexHelper::castFieldValue($item, 'double');

                return "'{$val}'";
            }, $condition->getValue());
            $conditionValue = '(' . implode(',', $values) . ')';
        } else {
            $conditionValue = "'" . pSQL($conditionValue, true) . "'";
        }

        return $columnName . ' ' . $condition->getOperator()
            . (!in_array($condition->getOperator(), array(Operators::NULL, Operators::NOT_NULL), true)
                ? $conditionValue : ''
            );
    }

    /**
     * Returns Packlink entity records that satisfy provided condition.
     *
     * @param string $condition Condition in format: KEY OPERATOR VALUE
     * @param QueryFilter $filter Query filter object.
     *
     * @return array Array of Packlink entity records.
     *
     * @throws \PrestaShopDatabaseException
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     * @throws \PrestaShopException
     */
    private function getRecordsByCondition($condition, QueryFilter $filter = null)
    {
        $query = new \DbQuery();
        $query->select(implode(',', $this->getSelectColumns()))
            ->from(bqSQL(static::TABLE_NAME))
            ->where($condition);
        $this->applyLimitAndOrderBy($query, $filter);

        $result = \Db::getInstance()->executeS($query);

        return !empty($result) ? $result : array();
    }

    /**
     * Applies limit and order by statements to provided SELECT query.
     *
     * @param \DbQuery $query SELECT query.
     * @param QueryFilter $filter Query filter object.
     *
     * @throws \Logeecom\Infrastructure\ORM\Exceptions\QueryFilterInvalidParamException
     */
    private function applyLimitAndOrderBy(\DbQuery $query, QueryFilter $filter = null)
    {
        if ($filter) {
            $limit = (int)$filter->getLimit();

            if ($limit) {
                $query->limit($limit, $filter->getOffset());
            }

            $orderByColumn = $filter->getOrderByColumn();
            if ($orderByColumn) {
                $indexedColumn = $orderByColumn === 'id' ? 'id' : $this->getIndexMapping($orderByColumn);
                if (empty($indexedColumn)) {
                    throw new QueryFilterInvalidParamException(
                        TranslationUtility::__(
                            'Unknown or not indexed OrderBy column %s',
                            array($filter->getOrderByColumn())
                        )
                    );
                }

                $query->orderBy($indexedColumn . ' ' . $filter->getOrderDirection());
            }
        }
    }

    /**
     * Prepares data for inserting a new record or updating an existing one.
     *
     * @param Entity $entity Packlink entity object.
     * @param array $indexes Array of index values.
     *
     * @return array Prepared record for inserting or updating.
     */
    private function prepareDataForInsertOrUpdate(Entity $entity, array $indexes)
    {
        $record = array('data' => pSQL($this->serializeEntity($entity), true));

        foreach ($indexes as $index => $value) {
            $record['index_' . $index] = $value !== null ? pSQL($value, true) : null;
        }

        return $record;
    }

    /**
     * Serializes Entity to string.
     *
     * @param Entity $entity Entity object to be serialized
     *
     * @return string Serialized entity
     */
    private function serializeEntity(Entity $entity)
    {
        return json_encode($entity->toArray());
    }

    /**
     * Unserializes entity form given string.
     *
     * @param string $data Serialized entity as string.
     *
     * @return \Logeecom\Infrastructure\ORM\Entity Created entity object.
     */
    private function unserializeEntity($data)
    {
        $jsonEntity = json_decode($data, true);
        if (array_key_exists('class_name', $jsonEntity)) {
            $entity = new $jsonEntity['class_name'];
        } else {
            $entity = new $this->entityClass;
        }

        /** @var Entity $entity */
        $entity->inflate($jsonEntity);

        return $entity;
    }
}
