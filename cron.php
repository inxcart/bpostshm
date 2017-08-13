<?php
/**
 * bpost shm status retrieval Cron script
 *  
 * @author    Serge <serge@stigmi.eu>
 * @version   1.3.0
 * @copyright 2015 Stigmi
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

$config_file = '';
if (false !== ($pos = strripos($_SERVER['SCRIPT_FILENAME'], '/modules/')));
{
	$base_dir = substr($_SERVER['SCRIPT_FILENAME'], 0, $pos);
	$config_file = $base_dir.'/config/config.inc.php';
}
if (empty($config_file) || !file_exists($config_file))
	die('Cannot find config file');

include($config_file);
include($base_dir.'/init.php');
include(dirname(__FILE__).'/bpostshm.php');

if (Tools::substr(_COOKIE_KEY_, 34, 8) != Tools::getValue('token'))
	die('Invalid token');

ini_set('max_execution_time', 7200);
$module = new BpostShm();
$module->cronTask();
die ('OK');

