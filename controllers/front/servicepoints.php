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
 * Class BpostShmServicePointsModuleFrontController
 */
class BpostShmServicePointsModuleFrontController extends BpostShmBpostBaseModuleFrontController
{
    const GMAPS_API_KEY = 'AIzaSyAa4S8Br_5of6Jb_Gjv1WLldkobgExB2KY';

    public function processContent()
    {
        $shipping_method = Tools::getValue('shipping_method');
        if ($delivery_date = Tools::getValue('dd')) {
            $delivery_date = date('d-m-Y', strtotime($delivery_date));
        }

        $service = new Service($this->context);
        $module = $service->module;
        $cart_bpost = CartBpost::getByPsCartID((int) $this->context->cart->id);

        //a//
        if (Tools::getValue('get_nearest_service_points')) {
            $search_params = ['zone' => '',];
            $postcode = Tools::getValue('postcode');
            $city = Tools::getValue('city');
            if ($postcode) {
                $search_params['zone'] .= (int) $postcode.($city ? ' ' : '');
            }
            if ($city) {
                $search_params['zone'] .= (string) $city;
            }
            if ($delivery_date) {
                $search_params['dd'] = (string) $delivery_date;
            }

            $service_points = (BpostShm::SHM_PPOINT == $shipping_method) ?
                $service->getNearestServicePoint($search_params) :
                $service->getNearestServicePoint($search_params, $shipping_method);
            $this->jsonEncode($service_points);
        } elseif (Tools::getValue('get_service_point_hours')) {
            $service_point_id = (int) Tools::getValue('service_point_id');
            $sp_type = (int) Tools::getValue('sp_type');
            $service_point_hours = $service->getServicePointHours($service_point_id, $sp_type);
            $this->jsonEncode($service_point_hours);
        } elseif (Tools::getValue('set_service_point')) {
            $service_point_id = (int) Tools::getValue('service_point_id');
            $sp_type = (int) Tools::getValue('sp_type');
            $this->jsonEncode($cart_bpost->setServicePoint($service_point_id, $sp_type));
        } elseif (Tools::getValue('post_upl_unregister')) {
            $upl_info = (string) Tools::getValue('post_upl_info');
            $stored = $upl_info === (string) $cart_bpost->upl_info;
            if (!$stored) {
                $cart_bpost->upl_info = $upl_info;
                $stored = $cart_bpost->save();
            }

            $this->jsonEncode($stored);
        }

        //p//
        $this->context->smarty->assign('version', (Service::isPrestashop16plus() ? 1.6 : 1.5), true);
        $this->context->smarty->assign('module_dir', _MODULE_DIR_.$module->name.'/', true);
        $this->context->smarty->assign('shipping_method', $shipping_method, true);
        switch ($shipping_method) {
            case BpostShm::SHM_PPOINT:
                $named_fields = $service->getNearestValidServicePoint(3, $delivery_date);
                foreach ($named_fields as $name => $field) {
                    $this->context->smarty->assign($name, $field, true);
                }

                $get_nearest_link_params = [
                    'ajax'                       => true,
                    'get_nearest_service_points' => true,
                    'shipping_method'            => $shipping_method,
                    'token'                      => Tools::getToken($module->name),
                ];
                if ($delivery_date) {
                    $get_nearest_link_params['dd'] = $delivery_date;
                }
                $this->context->smarty->assign('url_get_nearest_service_points', $this->getBpostLink('servicepoints', $get_nearest_link_params));
                $this->context->smarty->assign(
                    'url_get_service_point_hours', $this->getBpostLink(
                    'servicepoints',
                    [
                        'ajax'                    => true,
                        'get_service_point_hours' => true,
                        'shipping_method'         => $shipping_method,
                        'token'                   => Tools::getToken($module->name),
                    ]
                )
                );
                $this->context->smarty->assign(
                    'url_set_service_point', $this->getBpostLink(
                    'servicepoints',
                    [
                        'ajax'              => true,
                        'set_service_point' => true,
                        'shipping_method'   => $shipping_method,
                        'token'             => Tools::getToken($module->name),
                    ]
                )
                );

                $this->setTemplate('map-servicepoint.tpl');
                break;

            case BpostShm::SHM_PLOCKER:
                $step = (int) Tools::getValue('step', 1);
                switch ($step) {
                    default:
                    case 1:
                        $this->context->smarty->assign('step', 1, true);

                        $delivery_address = new Address($this->context->cart->id_address_delivery, $this->context->language->id);
                        // UPL
                        $upl_info = Tools::jsonDecode($cart_bpost->upl_info, true);
                        if (!isset($upl_info)) {
                            $upl_info = [
                                'eml' => $this->context->customer->email,
                                'mob' => !empty($delivery_address->phone_mobile) ? $delivery_address->phone_mobile : '',
                                'rmz' => false,
                            ];
                        }

                        $iso_code = $this->context->language->iso_code;
                        $upl_info['lng'] = in_array($iso_code, ['fr', 'nl']) ? $iso_code : 'en';
                        $this->context->smarty->assign('upl_info', $upl_info, true);
                        //
                        $this->context->smarty->assign(
                            'url_post_upl_unregister', $this->getBpostLink(
                            'servicepoints',
                            [
                                'ajax'                => true,
                                'post_upl_unregister' => true,
                                'shipping_method'     => $shipping_method,
                                'token'               => Tools::getToken($module->name),
                            ]
                        )
                        );

                        $get_point_list_link_params = [
                            'content_only'    => true,
                            'shipping_method' => $shipping_method,
                            'step'            => 2,
                            'token'           => Tools::getToken($module->name),
                        ];
                        if ($delivery_date) {
                            $get_point_list_link_params['dd'] = $delivery_date;
                        }
                        $this->context->smarty->assign('url_get_point_list', $this->getBpostLink('servicepoints', $get_point_list_link_params));

                        $this->setTemplate('form-upl.tpl');
                        break;

                    case 2:
                        $named_fields = $service->getNearestValidServicePoint($shipping_method, $delivery_date);
                        foreach ($named_fields as $name => $field) {
                            $this->context->smarty->assign($name, $field, true);
                        }

                        $get_nearest_link_params = [
                            'ajax'                       => true,
                            'get_nearest_service_points' => true,
                            'shipping_method'            => $shipping_method,
                            'token'                      => Tools::getToken($module->name),
                        ];
                        if ($delivery_date) {
                            $get_nearest_link_params['dd'] = $delivery_date;
                        }
                        $this->context->smarty->assign('url_get_nearest_service_points', $this->getBpostLink('servicepoints', $get_nearest_link_params));
                        $this->context->smarty->assign(
                            'url_get_service_point_hours', $this->getBpostLink(
                            'servicepoints',
                            [
                                'ajax'                    => true,
                                'get_service_point_hours' => true,
                                'shipping_method'         => $shipping_method,
                                'token'                   => Tools::getToken($module->name),
                            ]
                        )
                        );
                        $this->context->smarty->assign(
                            'url_set_service_point', $this->getBpostLink(
                            'servicepoints',
                            [
                                'ajax'              => true,
                                'set_service_point' => true,
                                'shipping_method'   => $shipping_method,
                                'token'             => Tools::getToken($module->name),
                            ]
                        )
                        );

                        $this->setTemplate('map-servicepoint.tpl');
                        break;
                }
                break;
        }
    }

    public function setMedia()
    {
        parent::setMedia();

        $base_uri = __PS_BASE_URI__.'modules/'.$this->module->name.'/views/';

        $this->addCSS($base_uri.'css/servicepoint.css');
        $this->addCSS($base_uri.'css/jquery.qtip.min.css');
        //
        $this->addJS($base_uri.'js/eon.jquery.base.min.js');
        $this->addJS($base_uri.'js/eon.jquery.servicepointer.min.js');
        $this->addJS($base_uri.'js/jquery.qtip.min.js');

        $this->addJS('https://maps.googleapis.com/maps/api/js?v=3&key='.static::GMAPS_API_KEY.'&language='.$this->context->language->iso_code);
    }
}
