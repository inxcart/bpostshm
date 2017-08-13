<?php
/**
 * Generic front controller
 *
 * @author    Serge <serge@stigmi.eu>
 * @authort   thirty bees <contact@thirtybees.com>
 * @version   1.40.0
 * @copyright Copyright (c), Eontech.net All rights reserved.
 * @copyright 2017 Thirty Development, LLC
 * @license   BSD License
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/../../bpostshm.php';

/**
 * Class BpostShmBpostBaseModuleFrontController
 */
class BpostShmBpostBaseModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function init()
    {
        $token = Tools::getValue('token');
        $gen_token = (bool) Tools::getValue('admin') ? Tools::getAdminToken($this->module->name) : Tools::getToken($this->module->name);
        if ($token !== $gen_token) {
            Tools::redirect('index');
        }

        // require_once(_PS_MODULE_DIR_.'bpostshm/classes/Service.php');
        require_once(_PS_MODULE_DIR_.$this->module->name.'/classes/Service.php');
    }

    public function initContent()
    {
        parent::initContent();

        $this->processContent();
    }

    protected function processContent()
    {
    }

    final protected function getBpostLink($controller = 'default', array $params = [])
    {
        return $this->context->link->getModuleLink($this->module->name, $controller, $params, true);
    }

    final protected function jsonEncode($content)
    {
        header('Content-Type: application/json');
        die(Tools::jsonEncode($content));
    }
}
