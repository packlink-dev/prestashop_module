<?php

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
        parent::__construct($id, $id_lang);

        $this->initializePacklinkHandler();
    }

    /**
     * Initializes Packlink module handler for extending order details page.
     */
    private function initializePacklinkHandler()
    {
        /** @noinspection PhpIncludeInspection */
        require_once rtrim(_PS_MODULE_DIR_, '/') . '/packlink/vendor/autoload.php';

        $column = Packlink\PrestaShop\Classes\Repositories\OrderRepository::PACKLINK_ORDER_DRAFT_FIELD;

        self::$definition['fields'][$column] = array(
            'type' => self::TYPE_STRING,
            'validate' => 'isUrl',
        );
        $this->webserviceParameters['fields'][$column] = array();
    }
}
