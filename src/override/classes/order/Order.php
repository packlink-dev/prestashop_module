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

/**
 * Class Order
 */
class Order extends OrderCore
{
    /**
     * @var string Link to order draft on Packlink.
     */
    public $packlink_order_draft;

    /**
     * @inheritdoc
     */
    public function __construct($id = null, $id_lang = null)
    {
        /** @noinspection PhpIncludeInspection */
        require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

        parent::__construct($id, $id_lang);

        $column = Packlink\PrestaShop\Classes\Repositories\OrderRepository::PACKLINK_ORDER_DRAFT_FIELD;

        self::$definition['fields'][$column] = array(
            'type' => self::TYPE_STRING,
            'validate' => 'isUrl',
        );
        $this->webserviceParameters['fields'][$column] = array();
    }
}
