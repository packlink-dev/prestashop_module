<?php

namespace Packlink\PrestaShop\Classes\Utility;

use Logeecom\Infrastructure\Logger\Logger;

class EmployeeUtility
{
    /**
     * Impersonates an employee by setting it to the current request context.
     *
     * @param int | null $id
     */
    public static function impersonate($id = null)
    {
        $employee = static::getEmployee($id);
        if (!$employee) {
            Logger::logError('Failed to impersonate an employee.', 'Integration');

            return;
        }

        $context = \ContextCore::getContext();
        $context->employee = $employee;
    }

    /**
     * Retrieves an employee.
     *
     * @param int | null $id
     *
     * @return \EmployeeCore | null
     */
    private static function getEmployee($id = null)
    {
        if ($id) {
            return new \EmployeeCore($id);
        }

        return static::getMockPacklinkEmployee();
    }

    /**
     * Retrieves a mock employee that.
     *
     * @return \EmployeeCore|null
     */
    private static function getMockPacklinkEmployee()
    {
        $employees = static::getEmployees();
        if (empty($employees[0]['id_employee']) || empty($employees[0]['id_lang'])) {
            return null;
        }

        $employee = new \EmployeeCore($employees[0]['id_employee'], $employees[0]['id_lang']);
        // We are setting the different name to denote that the action are performed
        // automatically by the Packlink plugin.
        $employee->firstname = 'Automation';
        $employee->lastname = 'Packlink';

        return $employee;
    }

    /**
     * Retrieves first employee.
     *
     * @return array
     */
    private static function getEmployees()
    {
        return \Db::getInstance()->executeS('
			SELECT `id_employee`, `id_lang`
			FROM `'._DB_PREFIX_.'employee` 
			WHERE `active` = 1 
			LIMIT 1
		');
    }
}