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

namespace Packlink\PrestaShop\Classes\Entities;

use Logeecom\Infrastructure\ORM\Configuration\EntityConfiguration;
use Logeecom\Infrastructure\ORM\Configuration\IndexMap;
use Logeecom\Infrastructure\ORM\Entity;

class CartCarrierDropOffMapping extends Entity
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Type of the entity.
     */
    const TYPE = 'CartCarrierDropOffMapping';
    /**
     * @var string
     */
    protected $cartId;
    /**
     * @var string
     */
    protected $carrierReferenceId;
    /**
     * @var array
     */
    protected $dropOff;
    /**
     * List of entity fields.
     *
     * @var array
     */
    protected $fields = array('id', 'cartId', 'carrierReferenceId', 'dropOff');

    /**
     * Returns entity configuration object.
     *
     * @return EntityConfiguration Configuration object.
     */
    public function getConfig()
    {
        $map = new IndexMap();
        $map->addStringIndex('cartId');
        $map->addStringIndex('carrierReferenceId');

        return new EntityConfiguration($map, self::TYPE);
    }

    /**
     * @return string
     */
    public function getCartId()
    {
        return $this->cartId;
    }

    /**
     * @param string $cartId
     */
    public function setCartId($cartId)
    {
        $this->cartId = $cartId;
    }

    /**
     * @return string
     */
    public function getCarrierReferenceId()
    {
        return $this->carrierReferenceId;
    }

    /**
     * @param string $carrierReferenceId
     */
    public function setCarrierReferenceId($carrierReferenceId)
    {
        $this->carrierReferenceId = $carrierReferenceId;
    }

    /**
     * @return array
     */
    public function getDropOff()
    {
        return $this->dropOff;
    }

    /**
     * @param array $dropOff
     */
    public function setDropOff($dropOff)
    {
        $this->dropOff = $dropOff;
    }
}