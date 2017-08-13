<?php
/**
 * 2014 Stigmi
 *
 * @author    Stigmi <www.stigmi.eu>
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2014 Stigmi
 * @copyright 2017 Thirty Development, LLC
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

use BpostModule\OrderBpost;
use BpostModule\Service;

require_once __DIR__.'/../../bpostshm.php';

/**
 * Class AdminOrdersBpostController
 */
class AdminOrdersBpostController extends ModuleAdminController
{
    /**
     * Statuses
     *
     * @var array
     */
    public $statuses = [
        'OPEN',
        'PENDING',
        'CANCELLED',
        /* 'COMPLETED', */
        'ON-HOLD',
        'PRINTED',
        'ANNOUNCED',
        'IN_TRANSIT',
        'AWAITING_PICKUP',
        'DELIVERED',
        'BACK_TO_SENDER',
    ];
    protected $identifier = 'reference';

    private $tracking_url = 'http://track.bpost.be/etr/light/performSearch.do';
    private $tracking_params = [
        'searchByCustomerReference' => true,
        'oss_language'              => '',
        'customerReference'         => '',
    ];

    /**
     * AdminOrdersBpostController constructor.
     */
    public function __construct()
    {
        $this->table = 'order_bpost';
        $this->className = 'BpostModule\\OrderBpost';
        $this->lang = false;
        $this->explicitSelect = true;
        $this->deleted = false;
        $this->list_no_link = true;
        $this->context = Context::getContext();

        $isoCode = strtolower($this->context->language->iso_code);
        $isoCode = in_array($isoCode, ['de', 'fr', 'nl', 'en']) ? $isoCode : 'en';
        $this->tracking_params['oss_language'] = $isoCode;
        $this->affectAdminTranslation($isoCode);
        // the most unlikely performance boost!
        $this->l_cache = [];

        $this->bootstrap = true;
        $this->show_filters = true;
        $this->module = new BpostShm();
        // service needs to be shop context dependant.
        $service = Service::getInstance($this->context);
        $this->service = SHOP::isFeatureActive() ? false : $service;

        // cached current_row while building list
        // always false after display for any action
        $this->current_row = false;
        // $this->bpost_treated_state = (int)Configuration::get('BPOST_ORDER_STATE_TREATED');

        $this->actions = [
            'addLabel',
            'createRetour',
            'printLabels',
            'refreshStatus',
            'markTreated',
            'sendTTEmail',
            'view',
            'cancel',
        ];

        $this->bulk_actions = [
            'markTreated' => ['text' => $this->l('Mark treated'), 'confirm' => $this->l('Mark order as treated?')],
            'printLabels' => ['text' => $this->l('Print labels')],
            'sendTTEmail' => ['text' => $this->l('Send T&T e-mail'), 'confirm' => $this->l('Send Track & Trace e-mail to recipient?')],
        ];

        $this->_select = '
		a.`reference` as print,
		a.`reference` as t_t,
		a.`shm`,
		COALESCE(a.`status`, "PENDING") as status_bpost,
		CASE WHEN 0 = a.`dt_drop` THEN NULL ELSE STR_TO_DATE(a.`dt_drop`, "%Y%m%d %T") END as drop_date,
		COUNT(obl.`id_order_bpost_label`) as count,
		SUM(obl.`barcode` IS NOT NULL) AS count_printed,
		SUM(obl.`is_retour` = 0) AS count_normal,
		SUM(obl.`is_retour` = 1) AS count_retours,
		SUM(obl.`has_retour` = 1) AS count_auto_retours 
		';

        $this->_join = '
		LEFT JOIN `'._DB_PREFIX_.'order_bpost_label` obl ON (a.`id_order_bpost` = obl.`id_order_bpost`)
		LEFT JOIN `'._DB_PREFIX_.'orders` o ON (o.`id_order` = SUBSTRING(a.`reference`, 8))
		LEFT JOIN `'._DB_PREFIX_.'order_carrier` oc ON (oc.`id_order` = o.`id_order`)
		LEFT JOIN `'._DB_PREFIX_.'carrier` c ON (c.`id_carrier` = oc.`id_carrier`)
		';

        $this->_where = '
		AND DATEDIFF(NOW(), a.date_add) <= '.BpostShm::DEF_ORDER_BPOST_DAYS.'
		AND a.current_state '.$this->module->getOrderStateListSQL().'
		';

        $idBpostCarriers = array_values($this->module->getIdCarriers());
        if ($references = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            (new DbQuery())
                ->select('`id_reference`')
                ->from('carrier')
                ->where('`id_carrier` IN ('.implode(', ', array_map('intval', $idBpostCarriers)).')')
        )) {
            foreach ($references as $reference) {
                $idBpostCarriers[] = (int) $reference['id_reference'];
            }
        }
        $this->_where .= '
		AND (
		oc.id_carrier IN ("'.implode('", "', $idBpostCarriers).'")
		OR c.id_reference IN ("'.implode('", "', $idBpostCarriers).'")
		)';

        $this->_group = 'GROUP BY(a.`reference`)';
        if (!Tools::getValue($this->table.'Orderby')) {
            $this->_orderBy = 'o.id_order';
        }

        if (!Tools::getValue($this->table.'Orderway')) {
            $this->_orderWay = 'DESC';
        }

        $this->external_sort_filter = Tools::getValue($this->table.'Orderby') || Tools::getValue($this->table.'Orderway');
        $this->inc_drop_date = (bool) Configuration::get('BPOST_DISPLAY_DELIVERY_DATE');

        $this->fields_list = [
            'print'           => [
                'title'    => '',
                'align'    => 'center',
                'callback' => 'getPrintIcon',
                'search'   => false,
                'orderby'  => false,
            ],
            't_t'             => [
                'title'    => '',
                'align'    => 'center',
                'callback' => 'getTTIcon',
                'search'   => false,
                'orderby'  => false,
            ],
            'reference'       => [
                'title'      => $this->l('Reference'),
                'align'      => 'left',
                'filter_key' => 'a!reference',
            ],
            'delivery_method' => [
                'title'    => $this->l('Delivery method'),
                'search'   => false,
                'callback' => 'getDeliveryMethod',
            ],
            'recipient'       => [
                'title'      => $this->l('Recipient'),
                'filter_key' => 'a!recipient',
            ],
            'status_bpost'    => [
                'title'    => $this->l('Status'),
                'callback' => 'getCurrentStatus',
            ],
            'date_add'        => [
                'title'      => $this->l('Creation date'),
                'align'      => 'right',
                'type'       => 'datetime',
                'filter_key' => 'a!date_add',
            ],
            'drop_date'       => [
                'title'        => $this->l('Drop date'),
                'align'        => 'right',
                // 1.5 doesnot use callback if type is date. must go manual
                'type'         => 'date',
                'havingFilter' => true,
            ],
            'count'           => [
                'title'    => $this->l('Labels'),
                'align'    => 'center',
                'callback' => 'getLabelsCount',
                'search'   => false,
                'orderby'  => false,
            ],
            'treated'         => [
                'title'   => 'T',
                // 'title' => $this->l('Treated'),
                'search'  => false,
                'orderby' => false,
                'class'   => 'treated_col',
            ],
        ];
        if (!$this->inc_drop_date) {
            unset($this->fields_list['drop_date']);
        }

        $this->shopLinkType = 'shop';
        $this->shopShareDatas = Shop::SHARE_ORDER;

        parent::__construct();
    }

    /**
     * insert this controllers translation strings into
     * globally retrieved AdminTab translations
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  string $isoCode
     *
     * @return void
     */
    private function affectAdminTranslation($isoCode = 'en')
    {
        global $_LANGADM;

        if (!(bool) preg_match('/^([a-z]{2})$/', $isoCode)) {
            return;
        }

        // $class_name = get_class($this);
        $className = BpostShm::ADMIN_CTLR;
        $module = isset($this->module) ? $this->module : 'bpostshm';
        $needle = Tools::strtolower($className).'_';
        $langFile = _PS_MODULE_DIR_.$module.'/translations/'.$isoCode.'.php';
        if (file_exists($langFile)) {
            $_MODULE = [];
            require $langFile;
            foreach ($_MODULE as $key => $value) {
                if (strpos($key, $needle)) {
                    $_LANGADM[str_replace($needle, $className, strip_tags($key))] = $value;
                }
            }

        }
    }

    /**
     * override PS controllers broken translation
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  string $string string to translate
     *
     * @return string                translated string if found or $string
     */
    protected function l($string, $class = BpostShm::ADMIN_CTLR, $addslashes = false, $htmlentities = true)
    {
        global $_LANGADM;

        $htmlentities = false; // always
        $key = $class.md5(str_replace('\'', '\\\'', $string));
        if (!isset($this->l_cache[$key])) {
            $this->l_cache[$key] = isset($_LANGADM[$key]) ?
                $_LANGADM[$key] :
                Translate::getAdminTranslation($string, $class, $addslashes, $htmlentities);
        }

        return $this->l_cache[$key];
    }

    public function addFiltersToBreadcrumbs()
    {
        $brainWorks = true;

        // Silence is golden
        return $brainWorks ? '' : parent::addFiltersToBreadcrumbs();
    }

    /**
     * @return string
     */
    public function initContent()
    {
        if (!$this->viewAccess()) {
            $this->_errors[] = Tools::displayError('You do not have permission to view this.');

            return '';
        }

        $this->getLanguages();
        $this->initToolbar();
        if (method_exists($this, 'initTabModuleList')) { // method not in earlier PS 1.5 < .6.2
            $this->initTabModuleList();
        }


        if ($this->display == 'view') {
            // Some controllers use the view action without an object
            if ($this->className) {
                $this->loadObject(true);
            }
            $this->content .= $this->renderView();
        } else {
            parent::initContent();
        }

        $this->addJqueryPlugin(['idTabs']);
    }

    public function initToolbar()
    {
        parent::initToolbar();

        if (isset($this->toolbar_btn['new'])) {
            $this->toolbar_btn['new'] = false;
        }
    }

    public function initProcess()
    {
        parent::initProcess();

        $reference = (string) Tools::getValue('reference');
        if (empty($this->errors) && !empty($reference)) {
            $response = [];
            $errors = [];
            $service = $this->getContextualService($reference);

            try {
                if (Tools::getIsset('addLabel'.$this->table)) {
                    // if (!$response = $service->addLabel($reference))
                    if (!$response = Service::addLabel($reference)) { // $response['errors'] = 'Unable to add Label to order ['.$reference.'] Please check logs for errors.';
                        $errors[$reference][] = 'Unable to add Label to order ['.$reference.'] Please check logs for errors.';
                    }

                } elseif (Tools::getIsset('createRetour'.$this->table)) {
                    // SRG: 14-8-16 -
                    $labelCount = (Tools::getIsset('count')) ? (int) Tools::getValue('count') : 1;
                    if (!$response = Service::addLabels($reference, $labelCount, true))
                        // if (!$response = Service::addLabel($reference, true))
                        //
                    {
                        $errors[$reference][] = 'Unable to add Retour Label to order ['.$reference.'] Please check logs for errors.';
                    }

                } elseif (Tools::getIsset('printLabels'.$this->table)) {
                    $links = Service::bulkPrintLabels([$reference]);
                    if (isset($links['error'])) {
                        $errors = $links['error'];
                        unset($links['error']);
                    }

                    if (!empty($links)) {
                        $response['links'] = $links;
                    }

                    if (Configuration::get('BPOST_LABEL_TT_INTEGRATION') && !empty($links)) {
                        $this->sendTTEmail($reference);
                    }

                } elseif (Tools::getIsset('refreshStatus'.$this->table)) {
                    if (!$response = Service::refreshBpostStatus($service->bpost, $reference)) {
                        $errors[$reference][] = 'Unable to refresh status for order ['.$reference.'] Please check logs for errors.';
                    }

                } elseif (Tools::getIsset('markTreated'.$this->table)) {
                    $service->module->changeOrderState($reference, 'Treated');
                } // }
                elseif (Tools::getIsset('sendTTEmail'.$this->table)) {
                    if (!$response = $this->sendTTEmail($reference)) {
                        $errors[$reference][] = $this->_errors;
                    }

                } elseif (Tools::getIsset('cancel'.$this->table)) {
                    $service->module->changeOrderState($reference, 'Cancelled');
                }
            } catch (Exception $e) {
                $errors[$reference][] = $e->getMessage();

            }

            if (!empty($errors)) {
                $response['errors'] = $errors;
            }

            $this->jsonEncode($response);
        } elseif (Tools::getIsset('removeInfo'.$this->table)) {
            $response = [];
            if ((bool) Configuration::updateGlobalValue('BPOST_DISPLAY_ADMIN_INFO', (int) false)) {
                $response[] = 'ok';
            } else {
                $response['errors']['Dev'][] = 'Cannot switch off admin info panel';
            }

            $this->jsonEncode($response);
        }
    }

    /**
     * retrieve service with correct shop context
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  string $reference
     *
     * @return void
     */
    private function getContextualService($reference)
    {
        // service needs the correct row shop context when multistore
        $service = $this->service;
        if (false === $service) {
            $this->setRowContext($reference);
            $service = new Service($this->context);
        }

        return $service;
    }

    protected function setRowContext($reference)
    {
        $order_bpost = OrderBpost::getByReference($reference);
        Shop::setContext(Shop::CONTEXT_SHOP, (int) $order_bpost->id_shop);
    }

    /**
     * @param string $reference
     *
     * @return bool
     */
    private function sendTTEmail($reference = '')
    {
        if (empty($reference)) {
            return false;
        }

        $order_bpost = OrderBpost::getByReference($reference);
        if (!$order_bpost->countPrinted()) {

            $this->_errors[$reference][] = $this->l('Unable to send tracking email until after the order is printed.');

            return false;
        }

        $tracking_url = $this->tracking_url;
        $params = $this->tracking_params;
        $params['customerReference'] = $reference;
        $tracking_url .= '?'.http_build_query($params);

        $ps_order = new Order((int) Service::getOrderIDFromReference($reference));
        // $ps_cart = new Cart((int)$ps_order->id_cart);
        // Serge 27 Oct 2015: message can't be constructed here with Admin language.
        $message = $this->l('Your order').' '.$ps_order->reference.' '.$this->l('can now be tracked here :')
            .' <a href="'.$tracking_url.'">'.$tracking_url.'</a>';

        $customer = new Customer($ps_order->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            $this->_errors[] = Tools::displayError('The customer is invalid.');
        } else {
            //check if a thread already exist
            $id_customer_thread = CustomerThread::getIdCustomerThreadByEmailAndIdOrder($customer->email, $ps_order->id);
            if (!$id_customer_thread) {
                $customer_thread = new CustomerThread();
                $customer_thread->id_contact = 0;
                $customer_thread->id_customer = (int) $ps_order->id_customer;
                $customer_thread->id_shop = (int) $this->context->shop->id;
                $customer_thread->id_order = (int) $ps_order->id;
                // $customer_thread->id_lang = (int)$this->context->language->id;
                $customer_thread->id_lang = (int) $ps_order->id_lang;
                $customer_thread->email = $customer->email;
                $customer_thread->status = 'open';
                $customer_thread->token = Tools::passwdGen(12);
                $customer_thread->add();
            } else {
                $customer_thread = new CustomerThread((int) $id_customer_thread);
            }

            $customer_message = new CustomerMessage();
            $customer_message->id_customer_thread = $customer_thread->id;
            $customer_message->id_employee = (int) $this->context->employee->id;
            $customer_message->message = $message;
            $customer_message->private = false;

            try {
                if (!$customer_message->add()) {
                    $this->_errors[] = 'Ref '.$reference.': '.Tools::displayError('An error occurred while saving the message.');
                } else {
                    $message = $customer_message->message;
                    if (Configuration::get('PS_MAIL_TYPE', null, null, $ps_order->id_shop) != Mail::TYPE_TEXT) {
                        $message = Tools::nl2br($customer_message->message);
                    }

                    $vars_tpl = [
                        '{lastname}'   => $customer->lastname,
                        '{firstname}'  => $customer->firstname,
                        '{id_order}'   => $ps_order->id,
                        '{order_name}' => $ps_order->getUniqReference(),
                        '{message}'    => $message,
                    ];

                    Mail::Send(
                        (int) $ps_order->id_lang, 'order_merchant_comment',
                        Mail::l('New message regarding your order', (int) $ps_order->id_lang), $vars_tpl, $customer->email,
                        $customer->firstname.' '.$customer->lastname, null, null, null, null, _PS_MAIL_DIR_, true, (int) $ps_order->id_shop
                    );
                }

            } catch (Exception $e) {
                $this->_errors[] = $e->getMessage();

            }
        }

        return (bool) empty($this->_errors);
    }

    /**
     * @param mixed $content
     */
    private function jsonEncode($content)
    {
        header('Content-Type: application/json');
        die(Tools::jsonEncode($content));
    }

    /**
     * Function used to render the list to display for this controller
     */
    public function renderList()
    {
        if (!($this->fields_list && is_array($this->fields_list))) {
            return false;
        }
        $this->getList($this->context->language->id);

        $helper = new HelperList();
        $helper->module = new BpostShm();

        // Empty list is ok
        if (!is_array($this->_list)) {
            $this->displayWarning($this->l('Bad SQL query', 'Helper').'<br />'.htmlspecialchars($this->_list_error));

            return false;
        } elseif (empty($this->_list)) {
            $this->bulk_actions = [];
        }

        $list_vars = [
            'str_tabs'    =>
                [
                    'open'    => $this->l('Open'),
                    'treated' => $this->l('Treated'),
                ],
            'reload_href' => self::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminOrdersBpost'),
        ];

        if ((bool) Configuration::get('BPOST_DISPLAY_ADMIN_INFO')) {
            $list_vars['remove_info_link'] = self::$currentIndex.'&removeInfo'.$this->table.'&token='.Tools::getAdminTokenLite('AdminOrdersBpost');
        }

        $this->tpl_list_vars = array_merge($this->tpl_list_vars, $list_vars);

        $this->setHelperDisplay($helper);
        $helper->tpl_vars = $this->tpl_list_vars;
        $helper->tpl_delete_link_vars = $this->tpl_delete_link_vars;

        // For compatibility reasons, we have to check standard actions in class attributes
        foreach ($this->actions_available as $action) {
            if (!in_array($action, $this->actions) && isset($this->$action) && $this->$action) {
                $this->actions[] = $action;
            }
        }
        $helper->is_cms = $this->is_cms;
        $list = $helper->generateList($this->_list, $this->fields_list);

        return $list;
    }

    public function getList($id_lang, $order_by = null, $order_way = null, $start = 0, $limit = null, $id_lang_shop = false)
    {
        // if (!empty($this->_filter))
        //  	$this->_filter = (string)str_replace('`status_bpost`', 'a.`status`', $this->_filter);
        if (!empty($this->_filter)) {
            $srch_stat = preg_match('`status_bpost`', $this->_filter);
            if (!empty($srch_stat)) {
                $str_filter = (string) str_replace('`status_bpost`', 'a.`status`', $this->_filter);
                // if (preg_match('/\%(pend[i]?[n]?[g]?)\%/i', $str_filter, $matches))
                if (preg_match('/\%(\bpe(n(d(i(n(g)?)?)?)?)?\b)\%/is', $str_filter, $matches)) {
                    $srch_str = sprintf("a.`status` LIKE '%s'", (string) $matches[0]);
                    $str_filter = (string) str_replace($srch_str, sprintf('(%s OR a.`status` IS NULL)', $srch_str), $str_filter);
                }
                $this->_filter = (string) $str_filter;
            }
        }
        //

        parent::getList($id_lang, $order_by, $order_way, $start, $limit, $id_lang_shop);

        if (!Tools::getValue($this->list_id.'_pagination')) {
            $this->context->cookie->{$this->list_id.'_pagination'} = 50;
        }

        // Serge changes: 27 Aug 2015
        // Default Order By handling sucks!
        if ($this->inc_drop_date && !$this->external_sort_filter) {
            $dt_today = (int) date('Ymd');
            // $this->_listsql = preg_replace('/^\s*(ORDER BY)(.+)$/m', '\1 CASE WHEN 0 = a.`dt_drop` THEN 1 ELSE 0 END, a.`dt_drop` ASC,\2', $this->_listsql);
            $this->_listsql = preg_replace(
                '/^\s*(ORDER BY)(.+)$/m',
                '\1 CASE WHEN (a.`dt_drop` > 0 AND a.`dt_drop` <= '.$dt_today.') THEN 0 ELSE 1 END, a.`dt_drop` ASC,\2', $this->_listsql
            );

            $this->_listTotal = 0;
            if (!($this->_list = Db::getInstance()->executeS($this->_listsql))) {
                $this->_list_error = Db::getInstance()->getMsgError();
            } else {
                $this->_listTotal = Db::getInstance()->getValue('SELECT FOUND_ROWS() AS `'._DB_PREFIX_.$this->table.'`');
            }
        }
    }

    public function processbulkmarktreated()
    {
        if (empty($this->boxes) || !is_array($this->boxes)) {
            return false;
        }

        $errors = [];
        $shop_orders = OrderBpost::fetchOrdersbyRefs($this->boxes);
        if (empty($shop_orders)) {
            $errors['dev'][] = 'Invalid reference(s)';
        } else {
            $cur_context = Shop::getContext();
            foreach ($shop_orders as $id_shop => $ref_orders) {
                Shop::setContext(Shop::CONTEXT_SHOP, (int) $id_shop);

                $svc = new Service(Context::getContext());
                foreach ($ref_orders as $order_ref) {
                    if ('CANCELLED' === (string) $order_ref['status']) {
                        continue;
                    }

                    $reference = (string) $order_ref['reference'];
                    try {
                        $svc->module->changeOrderState($reference, 'Treated');

                    } catch (Exception $e) {
                        $errors[$reference][] = $e->getMessage();

                    }
                }
            }
            // Shop::setContext(Shop::CONTEXT_ALL);
            Shop::setContext($cur_context);
        }

        if (!empty($errors)) {
            $this->context->smarty->assign('errors', $errors);
        }

        return empty($errors);
    }

    public function processbulkprintlabels()
    {
        if (empty($this->boxes) || !is_array($this->boxes)) {
            return false;
        }

        $cur_context = Shop::getContext();
        $labels = Service::bulkPrintLabels($this->boxes);
        // Shop::setContext(Shop::CONTEXT_ALL);
        Shop::setContext($cur_context);

        if (isset($labels['error'])) {
            $this->context->smarty->assign('errors', $labels['error']);
            unset($labels['error']);
        }

        if (!empty($labels)) {
            $this->context->smarty->assign('labels', $labels);
        }

        return true;
    }

    public function processbulksendttemail()
    {
        if (empty($this->boxes) || !is_array($this->boxes)) {
            return false;
        }

        $response = true;
        foreach ($this->boxes as $reference) {
            $response &= $response && $this->sendTTEmail($reference);
        }

        if (!$response) {
            $this->context->smarty->assign('errors', $this->_errors);
        }

        return $response;
    }

    /**
     * @param string $delivery_method as stored
     *
     * @return string
     */
    public function getDeliveryMethod($delivery_method = '')
    {
        if (empty($delivery_method)) {
            return;
        }

        // format: slug[:option list]*
        // @bpost or @home:300|330
        $dm_options = explode(':', $delivery_method);
        $tpl_vars = [
            'dm' => $dm_options[0],
        ];
        if (isset($dm_options[1])) {
            $service = Service::getInstance($this->context);
            $dm_options = $service->getDeliveryOptions($dm_options[1]);
            $tpl_vars['options'] = $dm_options;
        }

        $tpl = $this->createTemplate('order_bpost_delivery_method.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param string $status as stored
     *
     * @return string
     */
    public function getCurrentStatus($status = '')
    {
        $fields_list = $this->current_row;
        if (empty($fields_list)) {
            return;
        }

        $cls_late = $print_count = '';
        if (($count_printed = (int) $fields_list['count_printed']) &&
            'PRINTED' == $status) {
            $print_count = $count_printed.' / '.(int) $fields_list['count'];
        }
        //
        if (Validate::isDate($drop_date = $fields_list['drop_date'])) {
            $drop_time = strtotime($drop_date);
            // $display_date = $drop_date;
            $dt_drop = date('Ymd', $drop_time);
            $dt_today = date('Ymd');
            $cls_late = $dt_drop < $dt_today ? 'urgent' : ($dt_drop == $dt_today ? 'late' : '');
        }

        $tpl_vars = [
            'status'      => $status,
            'cls_late'    => $cls_late,
            'print_count' => $print_count,
        ];

        $tpl = $this->createTemplate('order_bpost_status.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param string $count
     *
     * @return string
     */
    public function getLabelsCount($count = '')
    {
        $fields_list = $this->current_row;
        if (empty($count) || empty($fields_list)) {
            return;
        }

        $count_retours = (int) $fields_list['count_retours'];
        // $count_normal = $count - $count_retours;
        $count_normal = (int) $fields_list['count_normal'];

        $reduced_size = $count_normal ? 'font-size:10px;' : '';
        $plus = $count_normal ? ' +' : '';
        $disp_retours = '<span style="'.$reduced_size.'color:silver;">'.$plus.$count_retours.'R</span>';

        $current_count = $count_normal ?
            $count_normal.($count_retours ? $disp_retours : '') :
            $disp_retours;

        return $current_count;
    }

    /**
     * @param string $reference
     *
     * @return string
     */
    public function getPrintIcon($reference = '')
    {
        if (empty($reference)) {
            return;
        }

        return '<img class="print" src="'._MODULE_DIR_.'bpostshm/views/img/icons/print.png"
			 data-labels="'.Tools::safeOutput(self::$currentIndex.'&reference='.$reference.'&printLabels'.$this->table.'&token='.$this->token).'"/>';
    }

    /**
     * @param string $reference
     *
     * @return string
     */
    public function getTTIcon($reference = '')
    {
        $fields_list = $this->current_row;
        if (empty($reference) || empty($fields_list) || empty($fields_list['count_printed'])) //(!$fields_list['count_printed']))
        {
            return;
        }

        $tracking_url = $this->tracking_url;
        $params = $this->tracking_params;
        $params['customerReference'] = $reference;

        $tracking_url .= '?'.http_build_query($params);

        return '<a href="'.$tracking_url.'" target="_blank" title="'.$this->l('View Track & Trace status').'">
			<img class="t_t" src="'._MODULE_DIR_.'bpostshm/views/img/icons/track_and_trace.png" /></a>';
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayAddLabelLink($token = null, $reference = '')
    {
        if (empty($reference)) {
            return;
        }

        // This is the 1st method called so store currentRow & set rowContext
        $this->setCurrentRow($reference);
        if (false === $this->service) {
            $this->setRowContext($reference);
        }

        $fields_list = $this->current_row;
        $tpl_vars = [
            'action' => $this->l('Add label'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&addLabel'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        if ('CANCELLED' == $fields_list['status_bpost']) {
            $tpl_vars['disabled'] = $this->l('Order is Cancelled at bpost SHM');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * [setCurrentRow]
     *
     * @param  string $reference
     * currentRow cached in member var current_row
     * usefull while building the list since not all
     * callbacks are called wth $reference.
     */
    protected function setCurrentRow($reference = '')
    {
        // needs to be placed in the 1st method called
        // currently that's displayAddLabelLink in 1.5+
        // as the 1st action item added
        $current_row = [];
        foreach ($this->_list as $row) {
            if ($reference == $row['reference']) {
                $current_row = $row;
                break;
            }
        }

        if (!empty($current_row)) {
            $this->current_row = $current_row;
        }
        // now we have it
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayPrintLabelsLink($token = null, $reference = '')
    {
        if (empty($reference)) {
            return;
        }

        $fields_list = $this->current_row;
        $tpl_vars = [
            'action' => $this->l('Print labels'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&printLabels'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        if ('CANCELLED' == $fields_list['status_bpost']) {
            $tpl_vars['disabled'] = $this->l('Order is Cancelled at bpost SHM');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayCreateRetourLink($token = null, $reference = '')
    {
        // Do not display if retours are automatically generated
        if (empty($reference) || (bool) Configuration::get('BPOST_AUTO_RETOUR_LABEL')) {
            return;
        }

        $fields_list = $this->current_row;
        $tpl_vars = [
            'action' => $this->l('Create retour'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&createRetour'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];
        // SRG: 14-8-16 - enabled only up to normal label count
        $count_diff = (int) $fields_list['count_normal'] - (int) $fields_list['count_retours'];
        if ($count_diff > 0) {
            $tpl_vars['href'] .= '&count='.$count_diff;
        } else {
            $tpl_vars['disabled'] = $this->l('Retour count cannot exeed normal labels');
        }
        //
        if ('CANCELLED' == $fields_list['status_bpost']) {
            $tpl_vars['disabled'] = $this->l('Order is Cancelled at bpost SHM');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayRefreshStatusLink($token = null, $reference = '')
    {
        $fields_list = $this->current_row;
        if (empty($reference) || empty($fields_list)) {
            return;
        }

        $tpl_vars = [
            'action' => $this->l('Refresh status'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&refreshStatus'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        // disable if labels are not PRINTED
        if (empty($fields_list['count_printed'])) {
            $tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');
        }
        if ('CANCELLED' == $fields_list['status_bpost']) {
            $tpl_vars['disabled'] = $this->l('Order is Cancelled at bpost SHM');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayMarkTreatedLink($token = null, $reference = '')
    {
        $fields_list = $this->current_row;
        if (empty($reference) || empty($fields_list)) {
            return;
        }

        $tpl_vars = [
            'action' => $this->l('Mark treated'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&markTreated'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        // disable if labels are not PRINTED
        if (empty($fields_list['count_printed'])) {
            $tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');
        } // elseif ($this->bpost_treated_state == (int)$fields_list['current_state'])
        elseif ((bool) $fields_list['treated']) {
            $tpl_vars['disabled'] = $this->l('Order is already treated.');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displaySendTTEmailLink($token = null, $reference = '')
    {
        // Do not display if T&T mails are automatically sent
        if (empty($reference) || (bool) Configuration::get('BPOST_LABEL_TT_INTEGRATION')) {
            return;
        }

        $fields_list = $this->current_row;
        $tpl_vars = [
            'action' => $this->l('Send Track & Trace e-mail'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&sendTTEmail'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        // disable if labels are not PRINTED
        if (empty($fields_list['count_printed'])) {
            $tpl_vars['disabled'] = $this->l('Actions are only available for orders that are printed.');
        }
        if ('CANCELLED' == $fields_list['status_bpost']) {
            $tpl_vars['disabled'] = $this->l('Order is Cancelled at bpost SHM');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayViewLink($token = null, $reference = '')
    {
        if (empty($reference)) {
            return;
        }

        $tpl_vars = [
            'action' => $this->l('Open order'),
            'target' => '_blank',
        ];

        $ps_order = new Order((int) Service::getOrderIDFromReference($reference));
        $token = Tools::getAdminTokenLite('AdminOrders');
        $tpl_vars['href'] = 'index.php?tab=AdminOrders&vieworder&id_order='.(int) $ps_order->id.'&token='.$token;

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }

    /**
     * @param null|string $token
     * @param string      $reference
     *
     * @return mixed
     */
    public function displayCancelLink($token = null, $reference = '')
    {
        $fields_list = $this->current_row;
        if (empty($reference)) {
            return;
        }

        $tpl_vars = [
            'action' => $this->l('Cancel order'),
            'href'   => Tools::safeOutput(
                self::$currentIndex.'&reference='.$reference.'&cancel'.$this->table
                .'&token='.($token != null ? $token : $this->token)
            ),
        ];

        // disable if labels have already been PRINTED
        if ((bool) $fields_list['count_printed']) {
            $tpl_vars['disabled'] = $this->l('Only open orders can be cancelled.');
        }

        $tpl = $this->createTemplate('helpers/list/list_action_option.tpl');
        $tpl->assign($tpl_vars);

        return $tpl->fetch();
    }
}
