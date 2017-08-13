<?php
/**
 * order_bpost table encapsulation class
 *
 * @author    Serge <serge@stigmi.eu>
 * @author    thirty bees <contact@thirtybees.com>
 * @version   0.5.0
 * @copyright Copyright (c), Eontech.net. All rights reserved.
 * @copyright 2017 Thirty Development, LLC
 * @license   BSD License
 */

namespace BpostModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class OrderBpost
 */
class OrderBpost extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition;
    /** @var int $id_shop_group */
    public $id_shop_group;
    /** @var int $id_shop */
    public $id_shop;
    /** @var boolean True when order state changes to 'Treated' as per settings */
    public $treated = 0;
    /** @var integer Order State id */
    public $current_state = 0;
    /** @var string Actual Bpost order status */
    public $status;
    /** @var int shipping method (8+1) if international */
    public $shm = 0;
    /** @var integer order drop date */
    public $dt_drop = 0;
    /** @var string Displayed delivery method in delivery options */
    public $delivery_method;
    /** @var string Displayed recipient */
    public $recipient;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;
    /**
     * @var string Bpost Order reference, should be unique
     */
    public $reference;
    /**
     * @see 1.4 ObjectModel->$table
     *        ObjectModel->$identifier
     * @see 1.4 ObjectModel->$fieldsRequired
     *        ObjectModel->$fieldsValidate
     */
    protected $table = 'order_bpost';
    protected $identifier = 'id_order_bpost';
    protected $fieldsRequired = ['reference', 'current_state', 'shm', 'delivery_method', 'recipient'];
    protected $fieldsValidate = [
        'reference'       => 'isString',
        'treated'         => 'isBool',
        'current_state'   => 'isUnsignedId',
        'shm'             => 'isUnsignedId',
        'dt_drop'         => 'isUnsignedId',
        'delivery_method' => 'isString',
        'recipient'       => 'isString',
    ];
    // @codingStandardsIgnoreEnd

    /**
     * PsOrderBpost constructor.
     *
     * @param null $id
     * @param null $idLang
     */
    public function __construct($id = null, $idLang = null)
    {
        // 1.4 is retarded to the max
        if (version_compare(_PS_VERSION_, '1.5', '>=')) {
            self::$definition = [
                'table'     => 'order_bpost',
                'primary'   => 'id_order_bpost',
                'multishop' => true,
                'fields'    => [
                    'reference'       => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
                    'id_shop_group'   => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
                    'id_shop'         => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
                    'treated'         => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
                    'current_state'   => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
                    'status'          => ['type' => self::TYPE_STRING],
                    'shm'             => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
                    'dt_drop'         => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId'],
                    'delivery_method' => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
                    'recipient'       => ['type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true],
                    'date_add'        => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
                    'date_upd'        => ['type' => self::TYPE_DATE, 'validate' => 'isDate'],
                ],
            ];
        }

        parent::__construct($id, $idLang);
    }

    /**
     * Get bpost order using reference
     *
     * @param string $reference
     *
     * @return OrderBpost|false
     */
    public static function getByReference($reference)
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new \DbQuery())
                ->select('`id_order_bpost`')
                ->from('order_bpost')
                ->where('`reference` = \''.pSQL($reference).'\'')
        );

        return isset($result['id_order_bpost']) ? new OrderBpost((int) $result['id_order_bpost']) : false;
    }

    /**
     * Get bpost order using Prestashop order id
     *
     * @param int $tbOrderId
     *
     * @return OrderBpost|false if found false otherwise
     */
    public static function getByPsOrderID($tbOrderId)
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new \DbQuery())
                ->select('`id_order_bpost`')
                ->from('order_bpost')
                ->where('SUBSTRING(`reference`, 8) = '.(int) $tbOrderId)
        );

        return isset($result['id_order_bpost']) ? new OrderBpost((int) $result['id_order_bpost']) : false;
    }

    /**
     * Get prestashop order using reference
     *
     * @param string $reference
     *
     * @return \Order
     */
    public static function getPsOrderByReference($reference)
    {
        return new \Order((int) \Tools::substr($reference, 7));
    }

    /**
     * @param array $refs
     *
     * @return array
     */
    public static function fetchOrdersbyRefs($refs)
    {
        $orders = [];

        if (empty($refs)) {
            return $orders;
        }

        $refsString = (string) implode('","', $refs);
        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`id_order_bpost`, `reference`, `id_shop`, `status`, `shm`')
                ->from('order_bpost')
                ->where("`reference` IN (\"$refsString\")")
                ->orderBy('`id_shop` ASC, `id_order_bpost` ASC')
        );

        if ($rows) {
            foreach ($rows as $row) {
                $orders[(int) $row['id_shop']][] = $row;
            }
        }

        return $orders;
    }

    /**
     * @param array $refs
     *
     * @return array
     */
    public static function fetchShopOrdersbyRefs($refs)
    {
        $orders = [];

        if (empty($refs)) {
            return $orders;
        }
        $refsString = (string) implode('","', $refs);
        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`id_order_bpost`, `id_shop`')
                ->from('order_bpost')
                ->where("`reference` IN (\"$refsString\")")
                ->orderBy('`id_shop` ASC, `id_order_bpost` ASC')
        );

        if ($rows) {
            foreach ($rows as $row) {
                $orders[(int) $row['id_shop']][] = new self((int) $row['id_order_bpost']);
            }
        }

        return $orders;
    }

    /**
     * @param int $nbDays
     * @param int $idShop
     *
     * @return array|false|null|\PDOStatement
     */
    public static function fetchBulkOrderRefs($nbDays, $idShop = 1)
    {
        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`id_order_bpost`, `reference`, `id_shop`, `status`')
                ->from('order_bpost')
                ->where('`status` IS NOT NULL')
                ->where('`id_shop` = '.(int) $idShop)
                ->where('`status` NOT IN ("DELIVERED", "CANCELLED")')
                ->where('DATEDIFF(NOW(), `date_add`) <= '.(int) $nbDays)
                ->orderBy('`id_order_bpost` ASC')
        );

        return $rows;
    }

    /**
     * @param array $orderStatuses
     *
     * @return bool|int
     */
    public static function updateBulkOrderStatus($orderStatuses)
    {
        if (!is_array($orderStatuses) or empty($orderStatuses)) {
            return 0;
        }

        $ids = [];
        $cases = '';
        $sql = '
		UPDATE `'._DB_PREFIX_.'order_bpost`
		SET `status` = CASE
		';
        foreach ($orderStatuses as $row) {
            $cases .= '
				WHEN `id_order_bpost` = '.(int) $row['id_order_bpost'].' THEN "'.pSQL($row['status']).'"';
            $ids[] = (int) $row['id_order_bpost'];
        }
        $sql .= $cases.'
			ELSE `status`
			END
		WHERE `id_order_bpost` IN ('.implode(',', $ids).')';

        return \Db::getInstance()->execute($sql);
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        parent::validateFields();

        $fields['reference'] = pSQL($this->reference);
        $fields['treated'] = (int) $this->treated;
        $fields['current_state'] = (int) $this->current_state;
        $fields['status'] = pSQL($this->status);
        $fields['shm'] = (int) $this->shm;
        $fields['dt_drop'] = (int) $this->dt_drop;
        $fields['delivery_method'] = pSQL($this->delivery_method);
        $fields['recipient'] = pSQL($this->recipient);
        $fields['date_add'] = pSQL($this->date_add);
        $fields['date_upd'] = pSQL($this->date_upd);

        return $fields;
    }

    /* Cron Pair */

    /**
     * Save current object to database (add or update)
     *
     * @param bool $nullValues
     * @param bool $autodate
     *
     * @return boolean Insertion result
     */
    public function save($nullValues = true, $autodate = true)
    {
        if ((int) $this->id > 0) {
            return parent::update($nullValues);
        }

        $return = parent::add($nullValues, $autodate);

        // must manually set Prestashop 1.5+
        // id_shop, id_shop_group to take hold !
        if (self::isPs15Plus()) {
            // Context is not dependable ! Only ps_orders values are safe.
            $idOrder = (int) \Tools::substr($this->reference, 7);
            $sql = 'UPDATE `'._DB_PREFIX_.self::$definition['table'].'` ob, `'._DB_PREFIX_.'orders` o
			SET ob.`id_shop` = o.`id_shop`,
				ob.`id_shop_group` = o.`id_shop_group`
			WHERE ob.`id_order_bpost` = '.(int) $this->id.'
			AND o.`id_order` = '.(int) $idOrder;

            $return = $return && \Db::getInstance()->execute($sql);
        }

        return $return;
    }

    /**
     * add bpost labels using is_retour
     *
     * @param int    $count    number of labels to add
     * @param bool   $isRetour
     * @param string $status
     *
     * @return bool
     */
    public function addLabels($count = 1, $isRetour = false, $status = 'PENDING')
    {
        if (!(bool) $this->id || (int) $count < 1) {
            return false;
        }

        $return = true;
        $autoRetour = (bool) \Configuration::get('BPOST_AUTO_RETOUR_LABEL');

        while ((int) $count > 0) {
            $orderLabel = new OrderBpostLabel();
            $orderLabel->id_order_bpost = $this->id;
            $orderLabel->is_retour = (bool) $isRetour;
            $orderLabel->has_retour = (bool) $autoRetour;
            $orderLabel->status = (string) $status;
            $return = $return && $orderLabel->save();
            $count--;
        }

        return $return;
    }

    /**
     * add a single bpost label using is_retour
     *
     * @param bool   $isRetour
     * @param string $status
     *
     * @return bool
     */
    public function addLabel($isRetour = false, $status = 'PENDING')
    {
        if (!(bool) $this->id) {
            return false;
        }

        $autoRetour = (bool) \Configuration::get('BPOST_AUTO_RETOUR_LABEL');

        $orderLabel = new OrderBpostLabel();
        $orderLabel->id_order_bpost = $this->id;
        $orderLabel->is_retour = (bool) $isRetour;
        $orderLabel->has_retour = (bool) $autoRetour;
        $orderLabel->status = (string) $status;

        return $orderLabel->save();
    }

    /**
     * @return bool|int
     */
    public function countPrinted()
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new \DbQuery())
                ->select('COUNTY(`id_order_bpost_label`) AS `count_printed`')
                ->from('order_bpost_label')
                ->where('`id_order_bpost` = '.(int) $this->id)
                ->where('`barcode` IS NOT NULL')
        );

        return isset($result['count_printed']) ? (int) $result['count_printed'] : false;
    }

    /**
     * Get new Bpost order labels using id
     *
     * @param bool $separate if true into [1] => has_retour and [0] => hasn't
     *
     * @return array of PsOrderBpostLabel Collections
     */
    public function getNewLabels($separate = true)
    {
        $newLabels = [];

        $rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new \DbQuery())
                ->select('`id_order_bpost_label`, `has_retour`')
                ->from('order_bpost_label')
                ->where('`id_order_bpost` = '.(int) $this->id)
                ->where('`barcode` = IS NULL')
                ->orderBy('`id_order_bpost_label` ASC')
        );

        if ($rows) {
            foreach ($rows as $row) {
                $orderBpostLabel = new OrderBpostLabel((int) $row['id_order_bpost_label']);
                if ($separate) {
                    $newLabels[(int) $row['has_retour']][] = $orderBpostLabel;
                } else {
                    $newLabels[] = $orderBpostLabel;
                }

            }
        }

        return $newLabels;
    }

    /**
     * [isPs15Plus helper static function
     *
     * @return boolean True if Prestashop is 1.5+
     */
    protected static function isPs15Plus()
    {
        return (bool) version_compare(_PS_VERSION_, '1.5', '>=');
    }
}
