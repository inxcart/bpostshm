<?php
/**
 * 2016 Stigmi
 *
 * bpost Shipping Manager
 *
 * Allow your customers to choose their preferrred delivery method: delivery at home or the office, at a pick-up location or in a bpack 24/7 parcel
 * machine.
 *
 * Release v1.40.0
 *
 * @author    Serge <serge@stigmi.eu>
 * @copyright 2016 Stigmi
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

/*  object module ($this) available */
function upgrade_module_1_40_0($object)
{
	$upgrade_version = '1.40.0';
	$object->upgrade_detail[$upgrade_version] = [];
	$return = true;

/* db changes */
	$table = 'order_bpost';
	$details = [
		'add' => 'treated',
		'type' => 'TINYINT(1) unsigned NOT NULL',
		'def' => 'DEFAULT 0',
		'after' => 'id_shop',
    ];

	$sql_exists = sprintf('SHOW COLUMNS FROM `%s` LIKE "%s";', _DB_PREFIX_.$table, $details['add']);
	$sql_alter = sprintf('
ALTER TABLE `%s`
ADD COLUMN `%s` %s %s AFTER `%s`;
		', _DB_PREFIX_.$table,
		$details['add'], $details['type'], $details['def'],
		$details['after']);

	$sql = sprintf('
UPDATE `%s`
SET `%s` = 1
WHERE `current_state` = %d;
		', _DB_PREFIX_.$table,
		$details['add'],
		(int)Configuration::get('BPOST_ORDER_STATE_TREATED'));

	if (false == Db::getInstance()->ExecuteS($sql_exists))
		if ($return = Db::getInstance()->Execute($sql_alter))
			$return = Db::getInstance()->Execute($sql);

	if (false == $return)
		$object->upgrade_detail[$upgrade_version][] = sprintf($object->l('Can\'t alter %s table'), _DB_PREFIX_.$table);

/* hooks */
	$old_hooks = [
		'backOfficeHeader',			// displayBackOfficeHeader
		'beforeCarrier',			// displayBeforeCarrier
		'paymentTop',				// displayPaymentTop
		'processCarrier',			// actionCarrierProcess
		'newOrder',					// actionValidateOrder
		'postUpdateOrderStatus',	// actionOrderStatusPostUpdate
		'updateCarrier',			// actionCarrierUpdate
		'orderDetailDisplayed',		// displayOrderDetail
		'header',					// displayHeader
    ];
	$new_hooks = [
		'displayBackOfficeHeader',		// backOfficeHeader
		'displayBeforeCarrier',			// beforeCarrier
		'displayPaymentTop',			// paymentTop
		'actionCarrierProcess',			// processCarrier
		'actionValidateOrder',			// newOrder
		'actionOrderStatusPostUpdate',	// postUpdateOrderStatus
		'actionCarrierUpdate',			// updateCarrier
		'displayOrderDetail',			// orderDetailDisplayed
		'displayHeader',				// header
		'displayMobileHeader',
		'displayAdminListBefore',
    ];

	// Remove old hooks & register the new, since last version
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
