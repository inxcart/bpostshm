<?php
/**
 * Version upgrade service class
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
 * Class UpgradeService
 *
 * @package BpostModule
 */
class UpgradeService
{
    protected static $instance = null;
    protected $module;
    protected $config_key;

    protected function __construct(\Module $module)
    {
        $this->module = $module;
        $this->config_key = \Tools::strtoupper($module->name).'_LAST_UPDATE_VERSION';
    }

    public static function init(\Module $module = null)
    {
        if (!isset(static::$instance)) {
            if (is_null($module)) {
                throw new \Exception('Cannot initialise upgrade service');
            }
            static::$instance = new static($module);
        }

        return static::$instance;
    }

    public function upgradeTo($version = '')
    {
        if (!self::validVersionFormat($version)) {
            return false;
        }
        // throw new Exception('Invalid version format');

        $from_version = $this->getConfigVersion();
        if ($from_version >= $version) {
            return true;
        }

        $upgrade_methods = [];
        $cls = new \ReflectionClass($this);
        $methods = $cls->getMethods(\ReflectionMethod::IS_PRIVATE);
        foreach ($methods as $method) {
            if (false !== strpos($method->name, 'upgrade_')) {
                $func_version = preg_replace(['/^(upgrade_)/', '/\_/'], ['', '.'], $method->name);
                if ($func_version > $from_version && $func_version <= $version) {
                    $upgrade_methods[$func_version] = $method->name;
                }
            }
        }

        $return = true;
        $upgrade_version = $from_version;
        if (count($upgrade_methods)) {
            try {
                asort($upgrade_methods);
                foreach ($upgrade_methods as $ver => $upgraded) {
                    if ($return = $this->$upgraded()) {
                        $upgrade_version = $ver;
                    } else {
                        break;
                    }
                }

            } catch (\Exception $e) {
                // Log the error
                $return = false;
            }
        }
        $return = $return && $this->setConfigVersion($upgrade_version);

        return $return;
    }

    public static function validVersionFormat($version)
    {
        return (bool) preg_match('/^\d+\.\d+\.\d+$/', (string) $version);
    }

    protected function getConfigVersion()
    {
        $config_version = (string) \Configuration::get($this->config_key);

        return empty($config_version) ? '0.0.0' : $config_version;
    }

    protected function setConfigVersion($version)
    {
        if (($return = self::validVersionFormat($version)) &&
            $version > $this->getConfigVersion()) {
            $return = \Configuration::updateGlobalValue($this->config_key, $version);
        }

        return $return;
    }

    protected function __clone()
    {
    }


    private function upgrade_1_21_0()
    {
        return true;
    }

    private function upgrade_1_22_0()
    {
        $return = true;

        $configs_2remove = [
            'BPOST_HOME_DELIVERY_ONLY',
        ];
        foreach ($configs_2remove as $key) {
            if (\Validate::isConfigName($key)) {
                \Configuration::deleteByName($key);
            }
        }

        // Translate BPOST_DELIVERY_OPTIONS_LIST
        // from {"home":"330|300","bpost":"","247":"350","intl":"540"}
        // to {"1":{"360":"1.2","470":["5.0","1.75"]},"2":{"300":"1.25"},"4":{},"9":{"540":"0.0"}}
        $sql = '
SELECT `id_configuration` AS id, value
FROM `'._DB_PREFIX_.'configuration`
WHERE `name` = "BPOST_DELIVERY_OPTIONS_LIST"
		';
        if ($config_rows = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
            $id_list = [];
            $sql = '
UPDATE `'._DB_PREFIX_.'configuration`
SET `value` = CASE';
            foreach ($config_rows as $row) {
                $sql .= '
	WHEN `id_configuration` = '.(int) $row['id'].' THEN "'.pSQL($this->getNewDeliveryOptionListFrom($row['value'])).'"';
                $id_list[] = (int) $row['id'];
            }
            $sql .= '
	ELSE `value`
	END
WHERE `id_configuration` in ('.implode(',', $id_list).')';

            $return = $return && \Db::getInstance()->execute($sql);
        }

        return $return;
    }

    /***************************************/
    /*** DO NOT MODIFY THE ABOVE SECTION ***/
    /***************************************/

    /* (Module depentent) upgrade functions
     * private function upgrade_X_XX_X()
     * return bool
     */
    private function getNewDeliveryOptionListFrom($old_opt_string = '')
    {
        if (empty($old_opt_string)) {
            return false;
        } elseif (false === strpos($old_opt_string, 'home')) {
            return $old_opt_string;
        }

        $mod = $this->module;
        $old2new_tbl = [
            'home'  => \BpostShm::SHM_HOME,
            'bpost' => \BpostShm::SHM_PPOINT,
            '247'   => \BpostShm::SHM_PLOCKER,
            'intl'  => \BpostShm::SHM_INTL,
        ];
        $delivery_options = \Tools::jsonDecode($old_opt_string, true);
        foreach ($delivery_options as $dm => $opt_string) {
            $keys = explode('|', $opt_string);
            $new_opt = [];
            foreach ($keys as $opt_key) {
                if (!empty($opt_key)) {
                    $new_opt[$opt_key] = '0.0';
                }
            }

            $delivery_options[$old2new_tbl[$dm]] = $new_opt;
            unset($delivery_options[$dm]);
        }

        // Tools version is inadequate so don't ask!
        // return Tools::jsonEncode($delivery_options, JSON_FORCE_OBJECT);
        return json_encode($delivery_options, JSON_FORCE_OBJECT);
    }

    /* 1.22.0 */

    private function upgrade_1_25_0()
    {
        $return = true;

        /* Remove old files */
        $old_files = [
            '/controllers/front/lightbox.php',
            '/controllers/front/lightbox14.php',
            '/controllers/front/servicepoint.php',
            '/views/css/lightbox.css',
            '/views/css/lightbox.scss',
            '/views/js/bpostshm.js',
            '/views/js/input-options.min.js',
            '/views/js/srgdebug.js',
            '/views/templates/admin/orders_bpost/helpers/list/list_footer14.tpl',
            '/views/templates/front/lightbox-at-247.tpl',
            '/views/templates/front/lightbox-point-list.tpl',
            '/views/templates/hook/extra-carrier.tpl',
        ];
        foreach ($old_files as $file) {
            $file = _PS_MODULE_DIR_.$this->module->name.$file;
            if (is_writable($file)) {
                $return = $return && unlink($file);
            }
        }

        return $return;
    }

    private function upgrade_1_30_0()
    {
        return true;
    }

    /* 1.25.0 (interim) */

    private function upgrade_1_30_4()
    {
        $return = true;

        // 1.30.1 (label security)
        $cache_dir = defined('_PS_CACHE_DIR_') ? _PS_CACHE_DIR_ : _PS_ROOT_DIR_.'/cache/';
        if (\Tools::file_exists_cache($cache_dir.'bpost_labels.pdf')) {
            $return = $return && unlink($cache_dir.'bpost_labels.pdf');
        }

        // 1.30.2
        // 1.30.3
        // 1.30.4
        /* Remove old upgrade files */
        $old_upgrade_files = [
            '/upgrade/install-1.30.1.php',
            '/upgrade/install-1.30.2.php',
            '/upgrade/install-1.30.3.php',
        ];
        foreach ($old_upgrade_files as $file) {
            $file = _PS_MODULE_DIR_.$this->module->name.$file;
            if (is_writable($file)) {
                $return = $return && unlink($file);
            }
        }

        return $return;
    }

    /* 1.30.0 */

    private function upgrade_1_40_0()
    {
        $return = true;
        $module = $this->module;

        // ** 1.35.0 (interim) **

        // Required defaults
        if (false == \Configuration::get('BPOST_TREATED_ORDER_STATE')) {
            $return = $return && \Configuration::updateGlobalValue('BPOST_TREATED_ORDER_STATE', (int) \BpostShm::DEF_TREATED_ORDER_STATE);
        }

        // Remove obsolete ps 1.4 related files
        $old_files = [
            '/AdminOrdersBpost.php',
            '/en.php',
            '/fr.php',
            '/nl.php',
            '/controllers/front/clientbpost.php',
            '/controllers/front/clientbpost14.php',
            '/controllers/front/FrontControllerService.php',
            '/controllers/front/EnabledCountriesService.php',
            '/controllers/front/CarrierBoxService.php',
            '/controllers/front/ServicePointService.php',
            '/views/css/admin14.css',
            '/views/img/card_en.png',
            '/views/img/card_fr.png',
            '/views/img/card_nl.png',
            // '/views/templates/hook/back-office-header.tpl',
            '/views/templates/hook/footer.tpl',
        ];

        foreach ($old_files as $file) {
            $file = _PS_MODULE_DIR_.$module->name.$file;
            if (is_writable($file)) {
                $return = $return && unlink($file);
            }
        }

        $return = $return && $this->removeFolder(_PS_MODULE_DIR_.$module->name.'/backward_compatibility');

        // ** 1.36.0 (interim) **

        /* Remove old upgrade files */
        $old_upgrade_files = [
            '/upgrade/install-1.35.0.php',
            '/upgrade/install-1.36.0.php',
        ];
        foreach ($old_upgrade_files as $file) {
            $file = _PS_MODULE_DIR_.$this->module->name.$file;
            if (is_writable($file)) {
                $return = $return && unlink($file);
            }
        }

        // ugly message defaults to on
        $return = $return && \Configuration::updateGlobalValue('BPOST_DISPLAY_ADMIN_INFO', (int) true);

        // ** 1.40.0 **

        return $return;
    }

    /* 1.30.{1-4} */

    protected function removeFolder($path)
    {
        $module_dir = _PS_MODULE_DIR_.$this->module->name;
        $return = true;
        if (!empty($path) &&
            $path !== $module_dir &&
            false !== strpos($path, $module_dir) &&
            is_dir($path)) {
            $return = $return && self::_delTree($path);
        }

        return $return;
    }

    /*
    *	1.35.0 (interim)
    * 	1.36.0 (interim)
    * 	1.40.0 (actual)
    */

    private static function _delTree($dir)
    {
        if (empty($dir)) {
            return false;
        }
        /* Start */
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $branch = $dir.DIRECTORY_SEPARATOR.$file;
            (is_dir($branch)) ? self::_delTree($branch) : unlink($branch);
        }

        return rmdir($dir);
    }
}
