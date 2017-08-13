<?php
/**
 * 2016 Stigmi
 *
 * bpost Shipping Manager
 *
 * Allow your customers to choose their preferrred delivery method: delivery at home or the office, at a pick-up location or in a bpack 24/7 parcel
 * machine.
 *
 * Release candidate for validation towards v1.30
 *
 * @author    Serge <serge@stigmi.eu>
 * @copyright 2016 Stigmi
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

/*  object module ($this) available */
function upgrade_module_1_25_0($object)
{
	$upgrade_version = '1.25.0';
	$object->upgrade_detail[$upgrade_version] = [];
	$return = true;

/* db changes */

	$db_changes = [
        'cart_bpost' => [
			'add' => 'delivery_codes',
			'type' => 'varchar(50) NOT NULL',
			'def' => 'DEFAULT "0,0,0"',
			'after' => 'option_kmi',
        ],
        'order_bpost' => [
			'add' => 'dt_drop',
			'type' => 'int(10) unsigned NOT NULL',
			'def' => 'DEFAULT 0',
			'after' => 'shm',
        ],
    ];

	foreach ($db_changes as $table => $details)
	{
		$sql_exists = sprintf('SHOW COLUMNS FROM `%s` LIKE "%s";', _DB_PREFIX_.$table, $details['add']);
		$sql = sprintf('
ALTER TABLE `%s`
ADD COLUMN `%s` %s %s AFTER `%s`;
			', _DB_PREFIX_.$table,
			$details['add'], $details['type'], $details['def'],
			$details['after']);
		if (false == Db::getInstance()->ExecuteS($sql_exists))
			if (false == Db::getInstance()->Execute($sql))
				$object->upgrade_detail[$upgrade_version][] = sprintf($object->l('Can\'t alter %s table'), _DB_PREFIX_.$table);
	}

/* hooks */
	$old_hooks = [
		'extraCarrier',				// displayCarrierList
    ];
	$new_hooks = [
			'header',					// displayHeader
    ];
	// 1.5+
	if ((bool)version_compare(_PS_VERSION_, '1.5', '>='))
		$new_hooks = array_merge($new_hooks, [
			'displayMobileHeader',
        ]
        );
	else
		$new_hooks = array_merge($new_hooks, [
			'footer',
        ]
        );

	/*  Remove old hooks & register the new, since last version */
	foreach ($old_hooks as $hook)
			if ($object->isRegisteredInHook($hook))
				$return = $return && $object->unregisterHook($hook);

	foreach ($new_hooks as $hook)
			if (!$object->isRegisteredInHook($hook))
				$return = $return && $object->registerHook($hook);

	$return = $return && empty($object->upgrade_detail[$upgrade_version]);
	$return = $return && $object->upgradeTo($upgrade_version);

	return $return;
}
