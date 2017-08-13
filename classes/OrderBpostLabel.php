<?php
/**
 * cart_bpost_label table encapsulation class
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
 * Class OrderBpostLabel
 *
 * @package BPostModule
 */
class OrderBpostLabel extends \ObjectModel
{
    // @codingStandardsIgnoreStart
    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [
        'table'   => 'order_bpost_label',
        'primary' => 'id_order_bpost_label',
        'fields'  => [
            'id_order_bpost' => ['type' => self::TYPE_INT,    'validate' => 'isUnsignedId',  'required' => true],
            'is_retour'      => ['type' => self::TYPE_BOOL,   'validate' => 'isBool',        'required' => true],
            'has_retour'     => ['type' => self::TYPE_BOOL,   'validate' => 'isBool',        'required' => true],
            'status'         => ['type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true],
            'barcode'        => ['type' => self::TYPE_STRING],
            'barcode_retour' => ['type' => self::TYPE_STRING],
            'date_add'       => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
            'date_upd'       => ['type' => self::TYPE_DATE,   'validate' => 'isDate'],
        ],
    ];
    /** @var integer */
    public $id_order_bpost_label;
    /** @var integer Bpost order id */
    public $id_order_bpost;
    /** @var boolean True if retour label */
    public $is_retour = 0;
    /** @var boolean True if Auto Retour is on */
    public $has_retour = 0;
    /** @var string Bpost Order Box / Label status */
    public $status;
    /** @var string bpost label barcode */
    public $barcode;
    /** @var string bpost label barcode if has_retour is True */
    public $barcode_retour;
    /** @var string Object creation date */
    public $date_add;
    /** @var string Object last modification date */
    public $date_upd;
    /**
     * @see 1.4 ObjectModel->$table
     *        ObjectModel->$identifier
     * @see 1.4 ObjectModel->$fieldsRequired
     *        ObjectModel->$fieldsValidate
     */
    protected $table = 'order_bpost_label';
    protected $identifier = 'id_order_bpost_label';
    protected $fieldsRequired = ['id_order_bpost', 'is_retour', 'has_retour', 'status'];
    protected $fieldsValidate = [
        'id_order_bpost' => 'isUnsignedId',
        'is_retour'      => 'isBool',
        'has_retour'     => 'isBool',
        'status'         => 'isString',
    ];
    /**
     * @see  ObjectModel::$webserviceParameters
     */
    protected $webserviceParameters = [
        'fields' => [
            'id_order_bpost' => ['required' => true, 'xlink_resource' => 'order_bpost'],
        ],
    ];
    // @codingStandardsIgnoreEnd

    /**
     * @return mixed
     */
    public function getFields()
    {
        parent::validateFields();

        $fields['id_order_bpost'] = (int) $this->id_order_bpost;
        $fields['is_retour'] = (int) $this->is_retour;
        $fields['has_retour'] = (int) $this->has_retour;
        $fields['status'] = pSQL($this->status);
        $fields['barcode'] = pSQL($this->barcode);
        $fields['barcode_retour'] = pSQL($this->barcode_retour);
        $fields['date_add'] = pSQL($this->date_add);
        $fields['date_upd'] = pSQL($this->date_upd);

        return $fields;
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
}
