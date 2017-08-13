<?php
/**
 * cart_bpost table encapsulation class
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
 * Class CartBpost
 */
class CartBpost extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see 1.5+ ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'cart_bpost',
        'primary' => 'id_cart_bpost',
        'fields'  => [
            'id_cart'           => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId', 'required' => true],
            'service_point_id'  => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'sp_type'           => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'option_kmi'        => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId'],
            'delivery_codes'    => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'upl_info'          => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'bpack247_customer' => ['type' => self::TYPE_STRING, 'validate' => 'isString'],
            'date_add'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'          => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];
    protected static $maskDate = 100000000;

    /* delivery_int bigint(15) */
    // public $delivery_int = 0;

    /** @var int[] service point choice id */
    protected static $deliveryKeys = [1, 2, 4];

    /** @var int service point type @bpost(1 or 2) @247(4) */
    /** @var integer */
    public $id_cart_bpost;

    /* int keep me informed choice value (default 0 => email) */
    /** @var integer ps_cart id */
    public $id_cart;
    public $service_point_id = 0;

    /* json encoded unregistered parcel locker customer info */
    public $sp_type = 0;

    /* json encoded parcel locker customer info */
    public $option_kmi = 0;

    /* may not need dates */
    /** @var string delivery codes */
    public $delivery_codes = '0,0,0';
    public $upl_info;

    /* delivery_int mask
     * cent-shm-day-date
     * 00000-0-0-00000000
     */
    // protected static $dmask = array(
    // 	'shm' => 10000000000,	/* 10z */
    // 	'day' => 1000000000,	/* 9z  */
    // 	'date' => 100000000,	/* 8z  */
    // );
    public $bpack247_customer;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;
    protected $delivery_cache = null;
    /**
     * @see 1.4 ObjectModel->$table
     *        ObjectModel->$identifier
     * @see 1.4 ObjectModel->$fieldsRequired
     *        ObjectModel->$fieldsValidate
     */
    protected $table = 'cart_bpost';
    protected $identifier = 'id_cart_bpost';
    protected $fieldsRequired = ['id_cart'];
    protected $fieldsValidate = [
        'id_cart'           => 'isUnsignedId',
        // 'delivery_int' => 		'isInt',
        'service_point_id'  => 'isUnsignedId',
        'sp_type'           => 'isUnsignedId',
        'option_kmi'        => 'isUnsignedId',
        'delivery_codes'    => 'isString',
        'upl_info'          => 'isString',
        'bpack247_customer' => 'isString',
    ];

    /**
     * @see  ObjectModel::$webserviceParameters
     */
    protected $webserviceParameters = [
        'fields' => [
            'id_cart' => ['required' => true, 'xlink_resource' => 'cart'],
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * Get prestashop order using reference
     *
     * @param int $tbCartId
     *
     * @return CartBpost Order
     */
    public static function getByPsCartID($tbCartId)
    {
        $result = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            (new \DbQuery())
                ->select('`id_cart_bpost`')
                ->from('cart_bpost')
                ->where('`id_cart` = '.(int) $tbCartId)
        );

        if (isset($result['id_cart_bpost'])) {
            return new CartBpost((int) $result['id_cart_bpost']);
        }

        $cartBpost = new CartBpost();
        $cartBpost->id_cart = (int) $tbCartId;
        $cartBpost->save();

        return $cartBpost;
    }

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
        return parent::save($nullValues, $autodate);
    }

    /**
     * @param bool $nullValues
     *
     * @return bool
     */
    public function update($nullValues = true)
    {
        return parent::update($nullValues);
    }

    /**
     * @return mixed
     */
    public function getFields()
    {
        parent::validateFields();

        $fields['id_cart'] = (int) $this->id_cart;
        // $fields['delivery_int'] = 		(int)$this->delivery_int;
        $fields['service_point_id'] = (int) $this->service_point_id;
        $fields['sp_type'] = (int) $this->sp_type;
        $fields['option_kmi'] = (int) $this->option_kmi;
        $fields['delivery_codes'] = pSQL($this->delivery_codes);
        $fields['upl_info'] = pSQL($this->upl_info);
        $fields['bpack247_customer'] = pSQL($this->bpack247_customer);
        $fields['date_add'] = pSQL($this->date_add);
        $fields['date_upd'] = pSQL($this->date_upd);

        return $fields;
    }

    /**
     * @return bool
     */
    public function reset()
    {
        return $this->setServicePoint(0, 0);
    }

    /**
     * @param int $id
     * @param int $type
     *
     * @return bool
     */
    public function setServicePoint($id = 0, $type = 0)
    {
        if (!is_numeric($id) || !is_numeric($type)) {
            return false;
        }

        $this->service_point_id = $id;
        $this->sp_type = $type;

        return $this->update();
    }

    /**
     * @param int $shippingMethod
     *
     * @return bool
     */
    public function validServicePointForSHM($shippingMethod = 0)
    {
        $valid = false;
        switch ((int) $shippingMethod) {
            case 1: // @home
                $valid = true; //empty($this->service_point_id);
                break;

            case 2: // @bpost
                $valid = in_array($this->sp_type, [1, 2]);
                break;

            case 4: // @24/7
                $valid = $shippingMethod == $this->sp_type;
                break;
        }

        return $valid;
    }

    /**
     * @param int $shm
     *
     * @return array|bool
     */
    public function getDeliveryCode($shm = 0)
    {
        if (is_null($this->delivery_cache)) {
            $this->delivery_cache = array_combine(static::$deliveryKeys, explode(',', $this->delivery_codes));
        }

        return isset($this->delivery_cache[$shm]) ? static::intDecodeDeliveryCode((int) $this->delivery_cache[$shm]) : false;
    }

    /**
     * @param int $deliveryCode
     *
     * @return array
     */
    public static function intDecodeDeliveryCode($deliveryCode = 0)
    {
        return [
            'cents' => $deliveryCode ? intval($deliveryCode / static::$maskDate) : 0,
            'date'  => $deliveryCode ? intval($deliveryCode % static::$maskDate) : 0,
        ];
    }

    /**
     * @param int $shm
     * @param int $date
     * @param int $cents
     */
    public function setDeliveryCode($shm = 0, $date = 0, $cents = 0)
    {
        if (is_null($this->delivery_cache)) {
            $this->delivery_cache = array_combine(static::$deliveryKeys, explode(',', $this->delivery_codes));
        }

        if (isset($this->delivery_cache[$shm])) {
            $deliveryCode = (int) static::intEncodeDeliveryCode($date, $cents);
            if ($deliveryCode !== $this->delivery_cache[$shm]) {
                $this->delivery_cache[$shm] = $deliveryCode;
                $this->delivery_codes = implode(',', array_values($this->delivery_cache));
                // $this->update();
            }
        }
    }

    /**
     * @param int $date
     * @param int $cents
     *
     * @return int
     */
    public static function intEncodeDeliveryCode($date = 0, $cents = 0)
    {
        return intval((int) $cents * static::$maskDate) + (int) $date;
    }

    /**
     * @return void
     */
    public function resetDeliveryCodes()
    {
        $this->delivery_codes = '0,0,0';
        $this->update();
    }
    /*
        public static function intEncodeDelivery($delivery = array())
        {
            if (empty($delivery))
                return 0;

            $date = isset($delivery['date']) ? (int)$delivery['date'] : 0;
            $day = isset($delivery['day']) ? (int)$delivery['day'] : 0;
            $shm = isset($delivery['shm']) ? (int)$delivery['shm'] : 0;
            $cent = isset($delivery['cent']) ? (int)$delivery['cent'] : 0;

            return (int)$date +
                intval($day * static::$dmask['date']) +
                intval($shm * static::$dmask['day']) +
                intval($cent * static::$dmask['shm']);
        }

        public static function intDecodeDelivery($delivery_int = 0)
        {
            return array(
                'date' => $delivery_int ? intval($delivery_int % static::$dmask['date']) : 0,
                'day' => $delivery_int ? intval(($delivery_int % static::$dmask['day']) / static::$dmask['date']) : 0,
                'shm' => $delivery_int ? intval(($delivery_int % static::$dmask['shm']) / static::$dmask['day']) : 0,
                'cent' => $delivery_int ? intval($delivery_int / static::$dmask['shm']) : 0,
                );
        }

        public function getDelivery()
        {
            return $this->delivery_int ? static::intDecodeDelivery($this->delivery_int) : false;
        }

        public function getDeliveryDate()
        {
            return $this->delivery_int ? intval($this->delivery_int % static::$dmask['date']) : 0;
        }

        public function getDeliveryDay()
        {
            return $this->delivery_int ? intval(($this->delivery_int % static::$dmask['day']) / static::$dmask['date']) : 0;
        }

        public function getDeliveryShm()
        {
            return $this->delivery_int ? intval(($this->delivery_int % static::$dmask['shm']) / static::$dmask['day']) : 0;
        }

        public function getDeliveryCent()
        {
            return $this->delivery_int ? intval($this->delivery_int / static::$dmask['shm']) : 0;
        }

        public function setDelivery($delivery = array())
        {
            // $date = (int)isset($delivery['date']) ? $delivery['date'] : $this->getDeliveryDate();
            // $day = (int)isset($delivery['day']) ? $delivery['day'] : $this->getDeliveryDay();
            // $shm = (int)isset($delivery['shm']) ? $delivery['shm'] : $this->getDeliveryShm();
            // $cent = (int)isset($delivery['cent']) ? $delivery['cent'] : $this->getDeliveryCent();
            $return = true;
            $delivery_int = static::intEncodeDelivery($delivery);
            if ($delivery_int !== $this->delivery_int)
            {
                $this->delivery_int = $delivery_int;
                $return = $this->save();
            }

            return $return;
        }
    */
}
