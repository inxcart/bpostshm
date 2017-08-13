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
use BpostModule\CartBpost;

if (!defined('_TB_VERSION_')) {
    exit;
}

require_once __DIR__.'/bpostbase.php';

/**
 * Class BpostShmCarrierBoxModuleFrontController
 */
class BpostShmCarrierBoxModuleFrontController extends BpostShmBpostBaseModuleFrontController
{
    protected function processContent()
    {
        $shipping_methods = explode(',', (string) Tools::getValue('shipping_method'));
        if (empty($shipping_methods)) {
            $this->jsonEncode(['error' => 'No shipping method']);
        }

        $shm = (int) $shipping_methods[0];

        $service = new Service($this->context);
        $module = $service->module;
        $tpl_dir = _PS_MODULE_DIR_.$module->name.'/views/templates/front/';
        $cart = $this->context->cart;
        $cart_bpost = CartBpost::getByPsCartID((int) $this->context->cart->id);

        //a//
        if (Tools::getValue('set_delivery_date')) {
            $response = [];
            $delivery_code = (int) Tools::getValue('delivery_code');
            $delivery = CartBpost::intDecodeDeliveryCode($delivery_code);
            $cart_bpost->setDeliveryCode($shm, $delivery['date'], $delivery['cents']);
            $response['saved'] = $shm === (int) $this->getShmSpType((int) $cart_bpost->sp_type) ? $cart_bpost->reset() : $cart_bpost->update();
            if ($delivery['cents']) {
                $response['extra'] = (float) $delivery['cents'] / 100;
            }

            $this->jsonEncode($response);
        } elseif (Tools::getValue('get_cbox')) {
            $response = [];
            $has_cost = false;
            $def_sat = false;

            // factored out
            $iso_code = Tools::strtoupper($this->context->language->iso_code);
            $lang_code = Tools::strtolower($iso_code).'_'.$iso_code;
            $locale1 = $lang_code.'.UTF8';
            $locale2 = $lang_code.'.UTF-8';
            // setlocale(LC_TIME, $lang_code); //en_EN
            setlocale(LC_TIME, $locale1, $locale2); //en_EN
            $hiding_oos = (bool) $service->hidingOOS($cart);

            $this->context->smarty->assign('version', (Service::isPrestashop16plus() ? 1.6 : 1.5), true);
            $this->context->smarty->assign('module_dir', _MODULE_DIR_.$module->name.'/', true);
            foreach ($shipping_methods as $shm) {
                $shm = (int) $shm;
                $this->context->smarty->assign('shipping_method', $shm, true);

                $button = false;
                $cbox = [
                    'address'  => false,
                    'button'   => false,
                    'delivery' => false,
                ];

                $intl = false;
                if (BpostShm::SHM_HOME == $shm) {
                    $delivery_address = new Address((int) $cart->id_address_delivery);
                    if (Validate::isLoadedObject($delivery_address)) {
                        $country = new Country((int) $delivery_address->id_country);
                        $intl = 'BE' != $country->iso_code;
                        $display_address = $delivery_address->address1
                            .(empty($delivery_address->address2) ? '' : ' '.$delivery_address->address2)
                            .', '.$delivery_address->postcode.' '.$delivery_address->city;

                        $cbox['address'] = ['body' => $display_address,];
                    }
                } else {
                    $sp_type = $this->getShmSpType((int) $cart_bpost->sp_type);
                    $button = [
                        'title' => '',
                        'class' => '',
                        'link'  => '',
                    ];
                    $btn_title = (int) $shm;
                    if ($sp_type == $shm) {
                        $sp_details = $service->getServicePointDetails($cart_bpost->service_point_id, $cart_bpost->sp_type);
                        $address = [
                            'title' => $sp_details['office'],
                            'body'  => $sp_details['street'].' '.$sp_details['nr'].', '.$sp_details['zip'].' '.$sp_details['city'],
                        ];
                        $cbox['address'] = $address;
                        $btn_title = $btn_title | 1;
                        $button['class'] = 'sp-change';
                    } else {
                        $is_mobile = (bool) Tools::getValue('mobile');
                        $button['class'] = $is_mobile ? 'ui-btn ui-btn-inner ui-btn-up-c ui-shadow' : 'button exclusive_large';
                    }

                    $button['title'] = (int) $btn_title;
                }

                $default_date = !$intl && !$hiding_oos && (bool) Configuration::get('BPOST_DISPLAY_DELIVERY_DATE');
                if ($default_date) {
                    $sat_info = $service->getSaturdayDeliveryOption($shm, $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING));
                    $inc_sat = false !== $sat_info;
                    $date_service = new EontechDateService($inc_sat);
                    $delivery_dates = $date_service->getDeliveryDates();
                    //
                    if (count($delivery_dates)) {
                        $default_date = $delivery_dates[0];
                        if ($delivery = $cart_bpost->getDeliveryCode($shm)) {
                            if (!empty($delivery['date']) && in_array($delivery['date'], $delivery_dates)) {
                                $default_date = $delivery['date'];
                            } else {
                                $store_date = $store_cents = 0;
                                $def_is_sat = $date_service->isSaturday($default_date);
                                $def_sat = $def_sat || $def_is_sat;
                                if ($def_is_sat) {
                                    $store_date = $default_date;
                                    $store_cents = $sat_info['cost'] * 100;
                                }

                                $cart_bpost->setDeliveryCode($shm, $store_date, (int) $store_cents);
                            }
                        }

                        $def_key = 0;
                        $dates = [];
                        foreach ($delivery_dates as $dt) {
                            $cents = $date_service->isSaturday($dt) ? $sat_info['cost'] * 100 : 0;
                            $has_cost = $has_cost || $cents > 0;
                            $date_key = CartBpost::intEncodeDeliveryCode($dt, (int) $cents);
                            $dates[$date_key] = strftime('%A %e %B %Y', strtotime((string) $dt));
                            if ($dt == $default_date) {
                                $def_key = $date_key;
                            }
                        }

                        $cbox['delivery'] = [
                            'dates'   => $dates,
                            'def'     => $def_key,
                            'def_sat' => $date_service->isSaturday($default_date),
                        ];
                    }
                }

                // finish button info
                if (!empty($button)) {
                    $link_params = [
                        'content_only'    => true,
                        'shipping_method' => $shm,
                    ];
                    if ((bool) $default_date) {
                        $link_params['dd'] = $default_date;
                    }
                    $link_params['token'] = Tools::getToken($module->name);
                    $button['link'] = $this->getBpostLink('servicepoints', $link_params);
                    $cbox['button'] = $button;
                }

                $this->context->smarty->assign('cbox', $cbox, true);
                $this->context->smarty->assign(
                    'url_set_delivery_date',
                    $this->getBpostLink(
                        'carrierbox',
                        [
                            'ajax'              => true,
                            'set_delivery_date' => true,
                            'shipping_method'   => $shm,
                            'token'             => Tools::getToken($module->name),
                        ]
                    )
                );

                $response['content'][$shm] = $this->context->smarty->fetch($tpl_dir.'carrier-box.tpl');
            }

            $cart_bpost->update();
            $response['has_cost'] = $has_cost;
            $response['def_sat'] = $def_sat;
            $this->jsonEncode($response);
        }

        //p//
    }

    protected function getShmSpType($sp_type = 0)
    {
        $sp_type = (int) $sp_type;

        return (BpostShm::SHM_PLOCKER === $sp_type) ? BpostShm::SHM_PLOCKER : ($sp_type > 0 ? BpostShm::SHM_PPOINT : 0);
    }
}
