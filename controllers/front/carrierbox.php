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
    public $display_header = false;
    public $display_footer = false;
    public $content_only = true;
    public $ssl = true;

    public function initContent()
    {
        $this->processContent();
    }

    protected function processContent()
    {
        if (!isset($this->context->cart)) {
            $this->context->cart = new Cart();
        }

        $shippingMethods = explode(',', (string) Tools::getValue('shipping_method'));
        if (empty($shippingMethods)) {
            $this->jsonEncode(['error' => 'No shipping method']);
        }

        $shm = (int) $shippingMethods[0];

        $service = new Service($this->context);
        $module = $service->module;
        $cart = Context::getContext()->cart;
        $cartBpost = CartBpost::getByPsCartID((int) $cart->id);

        //a//
        if (Tools::getValue('set_delivery_date')) {
            $response = [];
            $deliveryCode = (int) Tools::getValue('delivery_code');
            $delivery = CartBpost::intDecodeDeliveryCode($deliveryCode);
            $cartBpost->setDeliveryCode($shm, $delivery['date'], $delivery['cents']);
            $response['saved'] = $shm === (int) $this->getShmSpType((int) $cartBpost->sp_type) ? $cartBpost->reset() : $cartBpost->update();
            if ($delivery['cents']) {
                $response['extra'] = (float) $delivery['cents'] / 100;
            }

            $this->jsonEncode($response);
        } elseif (Tools::getValue('get_cbox')) {
            $response = [];
            $hasCost = false;
            $defSat = false;

            // factored out
            $isoCode = Tools::strtoupper($this->context->language->iso_code);
            $langCode = Tools::strtolower($isoCode).'_'.$isoCode;
            $locale1 = $langCode.'.UTF8';
            $locale2 = $langCode.'.UTF-8';
            // setlocale(LC_TIME, $lang_code); //en_EN
            setlocale(LC_TIME, $locale1, $locale2); //en_EN
            $hidingOos = (bool) $service->hidingOOS($cart);

            Context::getContext()->smarty->assign('version', (Service::isPrestashop16plus() ? 1.6 : 1.5), true);
            Context::getContext()->smarty->assign('module_dir', _MODULE_DIR_.$module->name.'/', true);
            foreach ($shippingMethods as $shm) {
                $shm = (int) $shm;
                Context::getContext()->smarty->assign('shipping_method', $shm, true);

                $button = false;
                $cbox = [
                    'address'  => false,
                    'button'   => false,
                    'delivery' => false,
                ];

                $intl = false;
                if (BpostShm::SHM_HOME == $shm) {
                    $deliveryAddress = new Address((int) $cart->id_address_delivery);
                    if (Validate::isLoadedObject($deliveryAddress)) {
                        $country = new Country((int) $deliveryAddress->id_country);
                        $intl = 'BE' != $country->iso_code;
                        $displayAddress = $deliveryAddress->address1.(empty($deliveryAddress->address2) ? '' : ' '.$deliveryAddress->address2).', '.$deliveryAddress->postcode.' '.$deliveryAddress->city;
                        $cbox['address'] = ['body' => $displayAddress];
                    }
                } else {
                    $spType = $this->getShmSpType((int) $cartBpost->sp_type);
                    $button = [
                        'title' => '',
                        'class' => '',
                        'link'  => '',
                    ];
                    $btnTitle = (int) $shm;
                    if ($spType == $shm) {
                        $spDetails = $service->getServicePointDetails($cartBpost->service_point_id, $cartBpost->sp_type);
                        $address = [
                            'title' => $spDetails['office'],
                            'body'  => $spDetails['street'].' '.$spDetails['nr'].', '.$spDetails['zip'].' '.$spDetails['city'],
                        ];
                        $cbox['address'] = $address;
                        $btnTitle = $btnTitle | 1;
                        $button['class'] = 'sp-change';
                    } else {
                        $isMobile = (bool) Tools::getValue('mobile');
                        $button['class'] = $isMobile ? 'ui-btn ui-btn-inner ui-btn-up-c ui-shadow' : 'button exclusive_large';
                    }

                    $button['title'] = (int) $btnTitle;
                }

                $defaultDate = !$intl && !$hidingOos && (bool) Configuration::get('BPOST_DISPLAY_DELIVERY_DATE');
                if ($defaultDate) {
                    $satInfo = $service->getSaturdayDeliveryOption($shm, $cart->getOrderTotal(false, Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING));
                    $incSat = false !== $satInfo;
                    $dateService = new EontechDateService($incSat);
                    $deliveryDates = $dateService->getDeliveryDates();
                    //
                    if (count($deliveryDates)) {
                        $defaultDate = $deliveryDates[0];
                        if ($delivery = $cartBpost->getDeliveryCode($shm)) {
                            if (!empty($delivery['date']) && in_array($delivery['date'], $deliveryDates)) {
                                $defaultDate = $delivery['date'];
                            } else {
                                $storeDate = $storeCents = 0;
                                $defIsSat = $dateService->isSaturday($defaultDate);
                                $defSat = $defSat || $defIsSat;
                                if ($defIsSat) {
                                    $storeDate = $defaultDate;
                                    $storeCents = $satInfo['cost'] * 100;
                                }

                                $cartBpost->setDeliveryCode($shm, $storeDate, (int) $storeCents);
                            }
                        }

                        $defKey = 0;
                        $dates = [];
                        foreach ($deliveryDates as $dt) {
                            $cents = $dateService->isSaturday($dt) ? $satInfo['cost'] * 100 : 0;
                            $hasCost = $hasCost || $cents > 0;
                            $dateKey = CartBpost::intEncodeDeliveryCode($dt, (int) $cents);
                            $dates[$dateKey] = strftime('%A %e %B %Y', strtotime((string) $dt));
                            if ($dt == $defaultDate) {
                                $defKey = $dateKey;
                            }
                        }

                        $cbox['delivery'] = [
                            'dates'   => $dates,
                            'def'     => $defKey,
                            'def_sat' => $dateService->isSaturday($defaultDate),
                        ];
                    }
                }

                // finish button info
                if (!empty($button)) {
                    $linkParams = [
                        'content_only'    => true,
                        'shipping_method' => $shm,
                    ];
                    if ((bool) $defaultDate) {
                        $linkParams['dd'] = $defaultDate;
                    }
                    $linkParams['token'] = Tools::getToken($module->name);
                    $button['link'] = $this->getBpostLink('servicepoints', $linkParams);
                    $cbox['button'] = $button;
                }

                $context = Context::getContext();

                $context->smarty->assign('cbox', $cbox, true);
                $context->smarty->assign(
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

                $response['content'][$shm] = $this->module->display(_PS_MODULE_DIR_."{$this->module->name}/{$this->module->name}.php", 'views/templates/front/carrier-box.tpl');
            }

            $cartBpost->update();
            $response['has_cost'] = $hasCost;
            $response['def_sat'] = $defSat;
            $this->jsonEncode($response);
        }
    }

    protected function getShmSpType($sp_type = 0)
    {
        $sp_type = (int) $sp_type;

        return (BpostShm::SHM_PLOCKER === $sp_type) ? BpostShm::SHM_PLOCKER : ($sp_type > 0 ? BpostShm::SHM_PPOINT : 0);
    }
}
