<?php
/**
 * Generic front controller
 *
 * @author    Serge <serge@stigmi.eu>
 * @author    thirty bees <contact@thirtybees.com>
 * @version   1.40.0
 * @copyright Copyright (c), Eontech.net All rights reserved.
 * @copyright 2017 Thirty Development, LLC
 * @license   BSD License
 */

use BpostModule\Service;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/bpostbase.php';

/**
 * Class BpostShmEnabledCountriesModuleFrontController
 */
class BpostShmEnabledCountriesModuleFrontController extends BpostShmBpostBaseModuleFrontController
{
    protected function processContent()
    {
        $id_shop = Tools::getValue('id_shop', false);
        if ($id_shop) {
            Shop::setContext(Shop::CONTEXT_SHOP, (int) $id_shop);
        }

        $service = new Service($this->context);
        if (Tools::getValue('get_enabled_countries')) {
            $available_countries = $service->getProductCountries();
            $this->jsonEncode($available_countries);
        }
    }
}
