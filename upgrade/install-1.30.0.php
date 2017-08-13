<?php
/**
 * 2016 Stigmi
 *
 * bpost Shipping Manager
 *
 * Allow your customers to choose their preferrred delivery method: delivery at home or the office, at a pick-up location or in a bpack 24/7 parcel
 * machine.
 *
 * @author    Serge <serge@stigmi.eu>
 * @copyright 2016 Stigmi
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_PS_VERSION_'))
	exit;

/*  object module ($this) available */
function upgrade_module_1_30_0($object)
{
	$upgrade_version = '1.30.0';
	$object->upgrade_detail[$upgrade_version] = [];
	$return = true;

	$return = $return && empty($object->upgrade_detail[$upgrade_version]);
	$return = $return && $object->upgradeTo($upgrade_version);

	return $return;
}
