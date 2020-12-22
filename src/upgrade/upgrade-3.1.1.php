<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Updates module to version 3.1.1.
 *
 * @return boolean
 *
 * @noinspection PhpUnused
 */
function upgrade_module_3_1_1()
{
    $query = new \DbQuery();
    $query->select('id, data')
        ->from(bqSQL('packlink_entity'))
        ->where('index_1="defaultParcel"');

    $records = \Db::getInstance()->executeS($query);
    foreach ($records as $record) {
        if (empty($record)) {
            continue;
        }

        $data = json_decode($record['data'], true);
        if (!empty($data['value']['weight'])) {
            $weight = (float)$data['value']['weight'];
            $data['value']['weight'] = !empty($weight) ? $weight : 1;
        }

        foreach (array('length', 'height', 'width') as $field) {
            if (!empty($data['value'][$field])) {
                $fieldValue = (int)$data['value'][$field];
                $data['value'][$field] = !empty($fieldValue) ? $fieldValue : 10;
            }
        }

        if (!empty($record['id'])) {
            \Db::getInstance()->update(
                'packlink_entity',
                array(
                    'data' => pSQL(json_encode($data), true)
                ),
                '`id` = ' . $record['id']
            );
        }
    }

    return true;
}
