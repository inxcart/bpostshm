<?php
/**
 * Main Service God Class
 *
 * @author    Serge <serge@stigmi.eu>
 * @author    thirty bees <contact@thirtybees.com>
 * @copyright 2015 Stigmi
 * @copyright 2017 Thirty Development, LLC
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace BpostModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Service
 *
 * @package BpostModule
 */
class Service
{
    const GEO6_APP_ID = '';
    const GEO6_PARTNER = 999999;
    const WEIGHT_MIN = 10;

    /* bpost accepted min, max weights (g) */
    const WEIGHT_MAX = 30000;
    public static $cache = [];
    /**
     * @var Service
     */
    protected static $instance;
    private static $slugsInternational = [
        'World Express Pro',
        'World Business',
    ];
    public $bpost;
    private $context;
    private $geo6;

    /**
     * @param \Context $context
     */
    public function __construct(\Context $context)
    {
        $this->context = $context;

        // $this->bpost = new EontechBpostServiceTest(
        $this->bpost = new \EontechBpostService(
            \Configuration::get('BPOST_ACCOUNT_ID'),
            \Configuration::get('BPOST_ACCOUNT_PASSPHRASE'),
            \Configuration::get('BPOST_ACCOUNT_API_URL')
        );
        $this->geo6 = new \EontechModGeo6(
            self::GEO6_PARTNER,
            self::GEO6_APP_ID
        );
        $this->module = new \BpostShm();
    }

    /**
     * @param \Context $context
     *
     * @return Service
     */
    public static function getInstance(\Context $context = null)
    {
        if (!Service::$instance) {
            if (is_null($context)) {
                $context = \Context::getContext();
            }
            self::$instance = new Service($context);
        }

        return Service::$instance;
    }

    public static function isPrestashop155plus()
    {
        return version_compare(_PS_VERSION_, '1.5.5.0', '>=');
    }

    /*
        public static function isPrestashop15plus()
        {
            return version_compare(_PS_VERSION_, '1.5', '>=');
        }
    */
    public static function isPrestashop16plus()
    {
        return version_compare(_PS_VERSION_, '1.6', '>=');
    }

    public static function isPrestashop161plus()
    {
        return version_compare(_PS_VERSION_, '1.6.1.0', '>=');
    }

    public static function isValidJSON($json)
    {
        $json = trim($json);
        $valid = !empty($json) && !is_numeric($json);
        if ($valid = $valid && in_array(\Tools::substr($json, 0, 1), ['{', '['])) {
            \Tools::jsonDecode($json);
            $valid = JSON_ERROR_NONE === json_last_error();
        }

        return $valid;
    }

    public static function getOrderIDFromReference($reference = '')
    {
        $return = false;

        $ref_parts = explode('_', $reference);
        if (3 === count($ref_parts)) {
            $return = (int) $ref_parts[1];
        }

        return $return;
    }

    /**
     * @param string $reference
     * @param  bool  $is_retour
     *
     * @return bool
     */
    public static function addLabel($reference = '', $is_retour = false)
    {
        $order_bpost = OrderBpost::getByReference($reference);

        return isset($order_bpost) && $order_bpost->addLabel((bool) $is_retour);
    }

    /**
     * @param string $reference
     * @param  int   $count number of labels to add
     * @param  bool  $is_retour
     *
     * @return bool
     */
    public static function addLabels($reference = '', $count = 1, $is_retour = false)
    {
        $order_bpost = OrderBpost::getByReference($reference);

        return isset($order_bpost) && $order_bpost->addLabels((int) $count, (bool) $is_retour);
    }

    /**
     * bulkPrintLabels (1.5+ only)
     *
     * @param  array $refs order references
     *
     * @return array       label links array (optional error[reference] array)
     */
    public static function bulkPrintLabels($refs)
    {
        $links = [];
        if (empty($refs) || !is_array($refs)) {
            $links['error']['dev'][] = 'No orders to bulk print';

            return $links;
        }

        $shop_orders = OrderBpost::fetchOrdersbyRefs($refs);
        if (empty($shop_orders)) {
            $links['error']['dev'][] = 'Invalid reference(s)';

            return $links;
        }

        $orders_status = [];
        foreach ($shop_orders as $idShop => $ref_orders) {
            \Shop::setContext(\Shop::CONTEXT_SHOP, (int) $idShop);

            $svc = new self(\Context::getContext());
            foreach ($ref_orders as $order_ref) {
                if ('CANCELLED' === (string) $order_ref['status']) {
                    continue;
                }

                $order_bpost = new OrderBpost((int) $order_ref['id_order_bpost']);
                $new_links = $svc->printOrderLabels($order_bpost);

                if (!isset($new_links['error'])) {
                    $reference = (string) $order_bpost->reference;
                    try {
                        $new_status = self::getBpostOrderStatus($svc->bpost, $reference);

                    } catch (\Exception $e) {
                        $msg = 'bad XML in RetrieveOrder Service: '.$e->getMessage();
                        $links['error'][$reference][] = $msg;
                        // the order must nevertheless, be valid and printed to be here
                        $new_status = 'PRINTED';
                    }

                    if (false !== $new_status && $new_status !== $order_bpost->status) {
                        $orders_status[] = [
                            'id_order_bpost' => (int) $order_ref['id_order_bpost'],
                            'status'         => (string) $new_status,
                        ];
                    }

                }

                $links = array_merge_recursive($links, $new_links);

            }
        }
        OrderBpost::updateBulkOrderStatus($orders_status);

        return \EontechPdfManager::mergedLinks($links);
        // return $links;
    }

    /**
     * @param object $order_bpost instance
     *
     * @return array links to printed labels
     */
    public function printOrderLabels($order_bpost)
    {
        $links = [];

        // if (true) // trace error
        if (!\Validate::isLoadedObject($order_bpost)) {
            $links['error']['dev'][] = 'invalid bpost order '.(int) $order_bpost->id;

            return $links;
        }

        $errors = [];
        $reference = $order_bpost->reference;
        try {
            $pdf_manager = new \EontechPdfManager($this->module->name, 'pdf', true);
            $pdf_manager->setActiveFolder($reference);

            $shm = $order_bpost->shm;
            $isIntl = self::isInternational($shm);
            // get all unprinted labels
            $tbLabels = $order_bpost->getNewLabels();
            if (count($tbLabels)) {
                $tbOrder = OrderBpost::getPsOrderByReference($reference);

                if (isset($order_bpost->status)) {
                    $bpostOrder = $this->bpost->fetchOrder($reference);
                    $boxes = $bpostOrder->getBoxes();
                    /** @var \EontechModBpostOrderBox $box1St */
                    $box1St = $boxes[0];
                    $weight = $isIntl ?
                        $box1St->getInternationalBox()->getParcelWeight() :
                        $box1St->getNationalBox()->getWeight();
                } else {
                    $weight = 0;
                    // create a new bpost order
                    $bpostOrder = new \EontechModBpostOrder($reference);

                    // add product lines
                    if ($products = $tbOrder->getProducts()) {
                        foreach ($products as $product) {
                            $productWeight = $product['product_weight'];
                            $productQty = (int) $product['product_quantity'];
                            $productName = self::getBpostring($product['product_name']);
                            $line = new \EontechModBpostOrderLine($productName, $productQty);
                            $bpostOrder->addLine($line);
                            $weight += $productQty * $productWeight;
                        }
                    }

                    // $weight = (int)$this->getWeightGrams($weight);
                    $weightInfo = $this->getSplitBoxInfo((int) $this->getWeightGrams($weight));
                    $weight = (int) $weightInfo['weight'];

                }

                $box = [];
                foreach ($tbLabels as $hasRetour => $curLabels) {
                    // reset boxes
                    $bpostOrder->setBoxes([]);

                    foreach ($curLabels as $labelBpost) {
                        $isRetour = (bool) $labelBpost->is_retour;
                        if (!isset($box[$isRetour])) {
                            $box[$isRetour] = $this->createBox($reference, $shm, $tbOrder, $weight, $isRetour);
                        }

                        $bpostOrder->addBox($box[$isRetour]);
                    }

                    $this->bpost->createOrReplaceOrder($bpostOrder);

                    $bcc = new \EontechBarcodeCollection();
                    $bpostLabelsReturned = $this->createLabelForOrder($reference, (bool) $hasRetour);
                    // save the labels and record the barcodes
                    if (is_array($bpostLabelsReturned) && !empty($bpostLabelsReturned)) {
                        foreach ($bpostLabelsReturned as $bpostLabel) {
                            $bcc->addBarcodes($bpostLabel->getBarcodes());
                            $pdf_manager->writePdf($bpostLabel->getBytes());
                        }
                    }
                    // set local label barcodes
                    foreach ($curLabels as $labelBpost) {
                        /** @var OrderBpostLabel $labelBpost */
                        $isRetour = (bool) $labelBpost->is_retour;
                        if ($hasRetour) {
                            $barcodes = $bcc->getNextAutoReturn($isIntl);
                            $labelBpost->barcode = $barcodes[\EontechBarcodeCollection::TYPE_NORMAL];
                            $labelBpost->barcode_retour = $barcodes[\EontechBarcodeCollection::TYPE_RETURN];
                        } else {
                            $labelBpost->barcode = $bcc->getNext($isRetour, $isIntl);
                        }

                        $labelBpost->status = 'PRINTED';
                        $labelBpost->save();
                    }
                }
            }

            // Srg 3-8-16: Auto treat?
            if (!(bool) $order_bpost->treated &&
                empty($errors) &&
                (bool) \Configuration::get('BPOST_TREAT_PRINTED_ORDER')) {
                $this->module->changeOrderState($reference, 'Treated');
            }
            //

        } catch (\Exception $e) {
            // $links['error'][$reference][] = $e->getMessage();
            // return $links;
            $errors[] = $e->getMessage();
        }

        //
        if (!empty($errors)) {
            $links['error'][$reference] = $errors;

            return $links;
        }

        //

        return $pdf_manager->getLinks();
    }

    public static function isInternational($dbShm)
    {
        return \BpostShm::SHM_INTL === (int) $dbShm;
    }

    public static function getBpostring($str, $max = false)
    {
        $pattern = '/[^\pL0-9,-_\.\s\'\(\)\&]/u';
        $rpl = '-';
        $str = preg_replace($pattern, $rpl, trim($str));
        $str = str_replace(['/', '\\'], $rpl, $str);
        if (false === strpos($str, '&amp;')) {
            $str = str_replace('&', '&amp;', $str);
        }

        // Tools:: version fails miserably, so don't even...
        // return Tools::substr($str, 0, $max);
        return mb_substr($str, 0, $max ? $max : mb_strlen($str));
    }

    /**
     * bpost-WS imposed min, max box weight means
     * total weight split over initial boxes required
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param int $weightGrams
     *
     * @return array
     */
    public function getSplitBoxInfo($weightGrams = 0)
    {
        $result = [
            'weight' => $weightGrams <= self::WEIGHT_MIN ? self::WEIGHT_MIN : (int) $weightGrams,
            'boxes'  => 1,
        ];

        if ($weightGrams > self::WEIGHT_MAX) {
            $boxes = (int) ($weightGrams / self::WEIGHT_MAX) + ($weightGrams % self::WEIGHT_MAX > 0 ? 1 : 0);
            $weightGrams = (int) round($weightGrams / $boxes, 0, PHP_ROUND_HALF_UP);
            $result['weight'] = $weightGrams;
            $result['boxes'] = $boxes;
        }

        return $result;
    }

    public function getWeightGrams($weight = 0)
    {
        $weight = (empty($weight) || !is_numeric($weight)) ? 1.0 : (float) $weight;
        $weightUnit = \Tools::strtolower(\Configuration::get('PS_WEIGHT_UNIT'));
        switch ($weightUnit) {
            case 'kg':
                $weight *= 1000;
                break;

            case 'g':
                break;

            case 'lbs':
            case 'lb':
                $weight *= 453.592;
                break;

            case 'oz':
                $weight *= 28.34952;
                break;

            default:
                $weight = 1000;
                break;
        }
        $weight = (int) round($weight, 0, PHP_ROUND_HALF_UP);

        return empty($weight) ? 1 : $weight;
    }

    /**
     * @param string $reference Bpost order reference
     * @param int    $db_shm    Shipping method (regular 3 + 4th bit for international)
     * @param \Order $ps_order  Prestashop order
     * @param int    $weight
     * @param bool   $is_retour if True create a retour box
     *
     * @return \EontechModBpostOrderBox|false
     */
    public function createBox(
        $reference = '',
        $db_shm = 0,
        $ps_order = null,
        $weight = 1000,
        $is_retour = false
    ) {
        if (empty($reference) || empty($db_shm) || !isset($ps_order)) {
            return false;
        }

        $shipping_method = (int) self::getActualShm($db_shm);
        $has_service_point = !self::isAtHome($shipping_method);
        if ($has_service_point) {
            $cart_bpost = CartBpost::getByPsCartID((int) $ps_order->id_cart);
            $service_point_id = (int) $cart_bpost->service_point_id;
            $sp_type = (int) $cart_bpost->sp_type;

            if ($is_retour) // effective $shipping_method if retour is on is always @home!
            {
                $shipping_method = (int) \BpostShm::SHM_HOME;
            }
        }

        $shippers = $this->getReceiverAndSender($ps_order, $is_retour, !$has_service_point);
        $sender = $shippers['sender'];
        $receiver = $shippers['receiver'];

        $box = new \EontechModBpostOrderBox();
        $box->setStatus('OPEN');
        $box->setSender($sender);

        $option_keys = $this->getOrderDeliveryOptions((int) $db_shm, $ps_order);

        switch ($shipping_method) {
            case \BpostShm::SHM_HOME:
                if (self::isInternational($db_shm)) {
                    // @International
                    $customs_info = new \EontechModBpostOrderBoxCustomsinfoCustomsInfo();
                    // $customs_info->setParcelValue((float)$ps_order->total_paid * 100);
                    // $parcel_value = ($ps_order->total_paid - $ps_order->total_discounts) * 100;
                    $parcel_value = $ps_order->total_products_wt * 100;
                    $customs_info->setParcelValue((float) $parcel_value);
                    // $customs_info->setContentDescription(Tools::substr('ORDER '.Configuration::get('PS_SHOP_NAME'), 0, 50));
                    $customs_info->setContentDescription(self::getBpostring('ORDER '.\Configuration::get('PS_SHOP_NAME'), 50));
                    // $customs_info->setShipmentType('OTHER');
                    $customs_info->setShipmentType('GOODS');
                    $customs_info->setParcelReturnInstructions('RTS');
                    $customs_info->setPrivateAddress(false);

                    $international = new \EontechModBpostOrderBoxInternational();
                    $international->setReceiver($receiver);
                    $international->setParcelWeight($weight);
                    $international->setCustomsInfo($customs_info);

                    if ($is_retour) {
                        $international->setProduct('bpack World Easy Return');
                    } else {
                        $international->setProduct('bpack '.$this->getInternationalSlug());
                        // $delivery_options = $this->getDeliveryBoxOptions('intl');
                        $delivery_options = $this->getDeliveryBoxOptions($option_keys);
                        foreach ($delivery_options as $option) {
                            $international->addOption($option);
                        }
                    }

                    $box->setInternationalBox($international);
                } else {
                    // @Home
                    $at_home = new \EontechModBpostOrderBoxAtHome();
                    $at_home->setReceiver($receiver);
                    $at_home->setWeight($weight);
                    if ($is_retour) {
                        $at_home->setProduct('bpack Easy Retour');
                    } else {
                        $at_home->setProduct('bpack 24h Pro');

                        // $delivery_options = $this->getDeliveryBoxOptions('home');
                        $delivery_options = $this->getDeliveryBoxOptions($option_keys);
                        foreach ($delivery_options as $option) {
                            $at_home->addOption($option);
                        }
                    }

                    $box->setNationalBox($at_home);
                }
                break;

            case \BpostShm::SHM_PPOINT:
                // @Bpost
                // Never retour
                $service_point = $this->getServicePointDetails($service_point_id, $sp_type);
                $pugo_address = new \EontechModBpostOrderPugoAddress(
                    $service_point['street'],
                    $service_point['nr'],
                    null,
                    $service_point['zip'],
                    $service_point['city'],
                    'BE'
                );

                $at_bpost = new \EontechModBpostOrderBoxAtBpost();
                $at_bpost->setPugoId(sprintf('%06s', $service_point_id));
                $at_bpost->setPugoName(\Tools::substr($service_point['office'], 0, 40));
                $at_bpost->setPugoAddress($pugo_address);
                $at_bpost->setReceiverName(\Tools::substr($receiver->getName(), 0, 40));
                $at_bpost->setReceiverCompany(\Tools::substr($receiver->getCompany(), 0, 40));

                if ($iso_code = self::getCustomerSessionLangIso((int) $ps_order->id_cart)) {
                    $at_bpost->addOption(
                        new \EontechModBpostOrderBoxOptionMessaging(
                            'keepMeInformed',
                            $iso_code,
                            $receiver->getEmailAddress()
                        )
                    );
                }
                // $delivery_options = $this->getDeliveryBoxOptions('bpost');
                $delivery_options = $this->getDeliveryBoxOptions($option_keys);
                foreach ($delivery_options as $option) {
                    $at_bpost->addOption($option);
                }

                $box->setNationalBox($at_bpost);
                break;

            case \BpostShm::SHM_PLOCKER:
                // @24/7
                // Never retour
                $service_point = $this->getServicePointDetails($service_point_id, \BpostShm::SHM_PLOCKER);
                $parcels_depot_address = new \EontechModBpostOrderParcelsDepotAddress(
                    $service_point['street'],
                    $service_point['nr'],
                    'A',
                    $service_point['zip'],
                    $service_point['city'],
                    'BE'
                );

                for ($i = \Tools::strlen($service_point['id']); $i < 6; $i++) {
                    $service_point['id'] = '0'.$service_point['id'];
                }

                $at_upl = new \EontechModBpostOrderBoxAtUPL();
                $at_upl->setParcelsDepotId($service_point['id']);
                $at_upl->setParcelsDepotName($service_point['office']);
                $at_upl->setParcelsDepotAddress($parcels_depot_address);
                //
                $upl_info = \EontechBpostUPLInfo::createFromJson($cart_bpost->upl_info);
                $at_upl->setUnregisteredInfo($upl_info);
                $at_upl->setReceiverName(\Tools::substr($receiver->getName(), 0, 40));
                $at_upl->setReceiverCompany(\Tools::substr($receiver->getCompany(), 0, 40));

                // $delivery_options = $this->getDeliveryBoxOptions('247');
                $delivery_options = $this->getDeliveryBoxOptions($option_keys);
                foreach ($delivery_options as $option) {
                    $at_upl->addOption($option);
                }

                $box->setNationalBox($at_upl);
                break;
        }
        // new field to insert PS version once per box, instead of once per Order!
        // $box->setAdditionalCustomerReference((string)'PrestaShop_'._PS_VERSION_);
        $additional_customer_reference = sprintf(
            'PrestaShop_%s/V%s/D%bC%b', (string) _PS_VERSION_,
            (string) $this->module->version,
            (int) \Configuration::get('BPOST_DISPLAY_DELIVERY_DATE'),
            (int) \Configuration::get('BPOST_CHOOSE_DELIVERY_DATE')
        );
        $box->setAdditionalCustomerReference((string) $additional_customer_reference);

        return $box;
    }

    public static function getActualShm($db_shm)
    {
        // actual shipping method in 1st 3-bits
        return $db_shm & 7;
    }

    public static function isAtHome($shm)
    {
        $is_athome = $shm & \BpostShm::SHM_HOME;

        return (bool) $is_athome;
    }

    /**
     * @param \Order $ps_order
     * @param bool   $is_retour
     * @param int    $shm_at_home bpost address field limits require differences for @home !
     *
     * @return array 'sender' & 'receiver' + formatted 'recipient'
     */
    public function getReceiverAndSender($ps_order, $is_retour = false, $shm_at_home = false)
    {
        $customer = new \Customer((int) $ps_order->id_customer);
        $delivery_address = new \Address($ps_order->id_address_delivery, $this->context->language->id);
        // $invoice_address = new Address($ps_order->id_address_invoice, $this->context->language->id);
        $company = self::getBpostring($delivery_address->company);
        $client_line1 = self::getBpostring($delivery_address->address1);
        $client_line2 = self::getBpostring($delivery_address->address2);

        // $shippers = array(
        // 	'client' => array(
        // 		'name'		=> $delivery_address->firstname.' '.$delivery_address->lastname,
        // 		'address1' 	=> $client_line1,
        // 		'address2' 	=> $client_line2,
        // 		'city' 		=> $delivery_address->city,
        // 		'postcode' 	=> $delivery_address->postcode,
        // 		'id_country'=> $delivery_address->id_country,
        // 		'email'		=> $customer->email,
        // 		'phone'		=> !empty($delivery_address->phone) ? $delivery_address->phone : $delivery_address->phone_mobile,
        // 	),
        // 	'shop' =>  array(
        // 		'name'		=> self::getBpostring(Configuration::get('PS_SHOP_NAME')),
        // 		'address1' 	=> Configuration::get('PS_SHOP_ADDR1'),
        // 		'address2' 	=> Configuration::get('PS_SHOP_ADDR2'),
        // 		'city' 		=> Configuration::get('PS_SHOP_CITY'),
        // 		'postcode' 	=> Configuration::get('PS_SHOP_CODE'),
        // 		'id_country'=> Configuration::get('PS_SHOP_COUNTRY_ID'),
        // 		'email' 	=> Configuration::get('PS_SHOP_EMAIL'),
        // 		'phone'		=> Configuration::get('PS_SHOP_PHONE'),
        // 	),
        // );

        // $client = $this->getBpostShipper($shippers['client']);
        $client = [
            'name'       => $delivery_address->firstname.' '.$delivery_address->lastname,
            'address1'   => $client_line1,
            'address2'   => $client_line2,
            'city'       => $delivery_address->city,
            'postcode'   => $delivery_address->postcode,
            'id_country' => $delivery_address->id_country,
            'email'      => $customer->email,
            'phone'      => !empty($delivery_address->phone) ? $delivery_address->phone : $delivery_address->phone_mobile,
        ];
        $client = $this->getBpostShipper($client);
        $recipient = $client['name'];
        if (!empty($client['line2']) && (bool) $shm_at_home) {
            $company = !empty($company) ? ' ('.$company.')' : '';
            $company = $client['name'].$company;
            $client['name'] = $client['line2'];
            $recipient = $company;
        }
        $client['company'] = $company;
        // $shop = $this->getBpostShipper($shippers['shop']);
        $shop = json_decode(\Configuration::get('BPOST_STORE_DETAILS'), true);
        if (isset($shop['name'])) {
            $shop = $this->getBpostShipper($shop);
        } else {
            $shop = [
                'name'       => self::getBpostring(\Configuration::get('PS_SHOP_NAME')),
                'address1'   => \Configuration::get('PS_SHOP_ADDR1'),
                'address2'   => \Configuration::get('PS_SHOP_ADDR2'),
                'city'       => \Configuration::get('PS_SHOP_CITY'),
                'postcode'   => \Configuration::get('PS_SHOP_CODE'),
                'id_country' => \Configuration::get('PS_SHOP_COUNTRY_ID'),
                'email'      => \Configuration::get('PS_SHOP_EMAIL'),
                'phone'      => \Configuration::get('PS_SHOP_PHONE'),
            ];
            $shop = $this->getBpostShipper($shop);
        }

        $sender = $shop;
        $receiver = $client;
        if ($is_retour) {
            $sender = $client;
            $receiver = $shop;
        }

        // sender
        $address = new \EontechModBpostOrderAddress();
        $address->setNumber(\Tools::substr($sender['number'], 0, 8));
        $address->setStreetName(self::getBpostring($sender['street'], 40));
        $address->setPostalCode(self::getBpostring($sender['postcode'], 32));
        $address->setLocality(self::getBpostring($sender['locality'], 40));
        $address->setCountryCode($sender['countrycode']);

        $bpost_sender = new \EontechModBpostOrderSender();
        $bpost_sender->setAddress($address);
        $bpost_sender->setName(self::getBpostring($sender['name'], 40));
        if (!empty($sender['company'])) {
            $bpost_sender->setCompany(self::getBpostring($sender['company'], 40));
        }
        $sender_phone = \Tools::substr($sender['phone'], 0, 20);
        if (!(empty($sender_phone))) {
            $bpost_sender->setPhoneNumber($sender_phone);
        }
        // $bpost_sender->setEmailAddress(Tools::substr($sender['email'], 0, 50));
        $sender_email = \Tools::substr($sender['email'], 0, 50);
        if (!(empty($sender_email))) {
            $bpost_sender->setEmailAddress($sender_email);
        }

        // receiver
        $address = new \EontechModBpostOrderAddress();
        $address->setNumber(\Tools::substr($receiver['number'], 0, 8));
        $address->setStreetName(self::getBpostring($receiver['street'], 40));
        $address->setPostalCode(self::getBpostring($receiver['postcode'], 32));
        $address->setLocality(self::getBpostring($receiver['locality'], 40));
        $address->setCountryCode($receiver['countrycode']);

        $bpost_receiver = new \EontechModBpostOrderReceiver();
        $bpost_receiver->setAddress($address);
        $bpost_receiver->setName(self::getBpostring($receiver['name'], 40));
        if (!empty($receiver['company'])) {
            $bpost_receiver->setCompany(self::getBpostring($receiver['company'], 40));
        }
        $receiver_phone = \Tools::substr($receiver['phone'], 0, 20);
        if (!(empty($receiver_phone))) {
            $bpost_receiver->setPhoneNumber($receiver_phone);
        }
        // $bpost_receiver->setEmailAddress(Tools::substr($receiver['email'], 0, 50));
        $receiver_email = \Tools::substr($receiver['email'], 0, 50);
        if (!(empty($receiver_email))) {
            $bpost_receiver->setEmailAddress($receiver_email);
        }

        // recipient continued (* only when not retour *)
        if (false === $is_retour) {
            $nb = $address->getNumber();
            $country_code = \Tools::strtoupper($address->getCountryCode());
            // $nb_part = is_numeric($nb) ? ' '.$nb : '';
            $nb_part = 'BE' === $country_code && ',' !== $nb ? ' '.$nb : '';
            $street2 = empty($client['line2']) ? '' : ', '.$client['line2'];
            $recipient .= ', '.$address->getStreetName().$nb_part.$street2
                .' '.$address->getPostalCode().' '.$address->getLocality().' ('.$country_code.')';
        }

        return [
            'receiver'  => $bpost_receiver,
            'sender'    => $bpost_sender,
            'recipient' => html_entity_decode($recipient),
        ];
    }

    /**
     * Rearrange address fields depending on Address2! because of stingy WS 40 char max fields
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  array $person shop or client
     *
     * @return array|false Bpost formatted shipper
     */
    protected function getBpostShipper($person = '')
    {
        if (empty($person)) {
            return false;
        }

        $address = [
            'nr'     => ',',
            'street' => $person['address1'],
            'line2'  => isset($person['address2']) ? $person['address2'] : '',
        ];

        $iso_code = isset($person['id_country']) ? \Tools::strtoupper(\Country::getIsoById($person['id_country'])) : 'BE';
        /* if ('BE' === $iso_code)
            $address = $this->getAddressStreetNr($address);
        */

        $shipper = [
            'name'        => $person['name'],
            'company'     => isset($person['company']) ? $person['company'] : '',
            'number'      => $address['nr'],
            'street'      => $address['street'],
            'line2'       => $address['line2'],
            'postcode'    => $person['postcode'],
            'locality'    => $person['city'],
            'countrycode' => $iso_code,
            'phone'       => $person['phone'],
            'email'       => $person['email'],
        ];

        return $shipper;
    }

    /**
     * [getOrderDeliveryOptions list of options stored | triggered by order]
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  int    $shm      shipping method
     * @param  \Order $ps_order Prestashop order
     *
     * @return array            option keys
     */
    private function getOrderDeliveryOptions($shm, $ps_order)
    {
        $opts = [];
        if (\Validate::isLoadedObject($ps_order)) {
            if ($bpost_order = OrderBpost::getByPsOrderID($ps_order->id)) {
                if (isset($bpost_order->delivery_method)) {
                    $delivery_method = explode(':', $bpost_order->delivery_method);
                    if (count($delivery_method) > 1) {
                        $opts = explode('|', $delivery_method[1]);
                    }
                }
            } else {
                $dates = $this->getDropDeliveryDates($shm, $ps_order);
                $opts = $this->getEffectiveDeliveryOptions($shm, $ps_order->total_products, $dates['sat']);
            }
        }

        return $opts;
    }

    private function getDropDeliveryDates($shm, $ps_order)
    {
        $dates = [
            'drop'     => 0,
            'delivery' => 0,
            'sat'      => false,
        ];
        if (\BpostShm::SHM_INTL !== (int) $shm &&
            \Configuration::get('BPOST_DISPLAY_DELIVERY_DATE') &&
            \Validate::isLoadedObject($ps_order) &&
            !$this->hidingOOS($ps_order)) {
            $cart_bpost = CartBpost::getByPsCartID((int) $ps_order->id_cart);
            if ($delivery = $cart_bpost->getDeliveryCode($shm)) {
                $dt_delivery = $dates['delivery'] = (int) $delivery['date'];
                $date_service = new \EontechDateService();
                $dates['sat'] = (bool) $date_service->isSaturday($dt_delivery);
                $dates['drop'] = (int) $date_service->getDropDate($dt_delivery);
            }
        }

        return $dates;
    }

    /**
     * hide OOS products ?
     * for drop & delivery date calculation
     *
     * @param  object $cartOrOrder either a PS Cart or Order object with a public getProducts method
     *
     * @return bool  yes or no
     */
    public function hidingOOS($tbCartOrOrder)
    {
        if ($hide_oos = (bool) \Configuration::get('BPOST_HIDE_DATE_OOS')) {
            if (\Validate::isLoadedObject($tbCartOrOrder) && method_exists($tbCartOrOrder, 'getProducts')) {
                $one_oos = false;
                $products = $tbCartOrOrder->getProducts();
                foreach ($products as $product) {
                    if ($one_oos = ($one_oos ||
                        (isset($product['quantity_available']) && $product['quantity_available'] <= 0) ||
                        (isset($product['product_quantity']) && $product['product_quantity'] <= 0))) {
                        break;
                    }
                }
                $hide_oos = $one_oos;
            }
        }

        return (bool) $hide_oos;
    }

    /**
     * [getEffectiveDeliveryOptions list of options triggered by order products total]
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  int   $shm            shipping method
     * @param  float $total_products (order total before shipping & tax)
     * @param  bool  $sat_delivery   is delivery date a Saturday ?
     *
     * @return array    option keys
     */
    private function getEffectiveDeliveryOptions($shm, $total_products, $sat_delivery = false)
    {
        $opts = [];
        if ($options_list = \Configuration::get('BPOST_DELIVERY_OPTIONS_LIST')) {
            $options_list = \Tools::jsonDecode($options_list, true);
            if (isset($options_list[$shm])) {
                foreach ($options_list[$shm] as $key => $from) {
                    if (is_array($from)) {
                        if (!$sat_delivery) {
                            continue;
                        }
                        $from = $from[0];
                    }
                    if ((float) $from <= (float) $total_products) {
                        $opts[] = $key;
                    }
                }
            }

        }

        return $opts;
    }

    private function getInternationalSlug()
    {
        $setting = (int) \Configuration::get('BPOST_INTERNATIONAL_DELIVERY');

        return self::$slugsInternational[$setting];
    }

    /**
     * [getDeliveryBoxOptions provide delivery options in optimal order]
     *
     * @param  array $option_keys numeric order
     *
     * @return array option xml classes in effctive optimal order '330|350(540)|300|470'
     */
    private function getDeliveryBoxOptions($option_keys = '')
    {
        $options = [];
        if (!empty($option_keys) && is_array($option_keys)) {
            $sequence = [330, 350, 540, 300, 470];
            foreach ($sequence as $key)
                if (in_array($key, $option_keys))
                    switch ($key) {
                        case 300: // Signature has to be at the end !?
                            $options[] = new \EontechModBpostOrderBoxOptionSigned();
                            break;

                        case 330: // 2nd Presentation
                            $options[] = new \EontechModBpostOrderBoxOptionAutomaticSecondPresentation();
                            break;

                        case 350:
                        case 540: // Insurance
                            $options[] = new \EontechModBpostOrderBoxOptionInsured('basicInsurance');
                            break;

                        case 470: // Saturday delivery
                            $options[] = new \EontechModBpostOrderBoxOptionSaturdayDelivery();
                            break;

                        // default:
                        // 	throw new Exception('Not a valid delivery option');
                        // break;
                    }
        }

        return $options;
    }

    /**
     * @param int $service_point_id
     * @param int $type
     *
     * @return array
     */
    public function getServicePointDetails($service_point_id = 0, $type = 3)
    {
        $service_point_details = [];
        try {
            if ($poi = $this->geo6->getServicePointDetails($service_point_id, $this->context->language->iso_code, $type)) {
                $service_point_details['id'] = $poi->getId();
                $service_point_details['office'] = $poi->getOffice();
                $service_point_details['street'] = $poi->getStreet();
                $service_point_details['nr'] = $poi->getNr();
                $service_point_details['zip'] = $poi->getZip();
                $service_point_details['city'] = $poi->getCity();
            }
        } catch (\EontechModException $e) {
            $service_point_details = [];
            if (2 === $type) {
                $service_point_details = $this->getServicePointDetails($service_point_id, 1);
            }

        }

        return $service_point_details;
    }

    public static function getCustomerSessionLangIso($id_cart = 0)
    {
        if (empty($id_cart)) {
            return false;
        }

        $iso_code = false;
        $cart = new \Cart((int) $id_cart);
        if (\Validate::isLoadedObject($cart)) {
            $iso_code = \Tools::strtoupper(\Language::getIsoById((int) $cart->id_lang));
            // language must default to EN if not in allowed values
            if (!in_array($iso_code, ['EN', 'NL', 'FR', 'DE'])) {
                $iso_code = 'EN';
            }
        }

        return $iso_code;
    }

    /**
     * @param null|string $reference
     *
     * @return bool
     */
    public function createLabelForOrder($reference = null, $with_return_labels = false)
    {
        $response = false;

        if (!is_null($reference)) {
            $reference = \Tools::substr($reference, 0, 50);
            $format = \Configuration::get('BPOST_LABEL_PDF_FORMAT');

            try {
                $response = $this->bpost->createLabelForOrder($reference, $format, $with_return_labels, true);
            } catch (\EontechModException $e) {
                $response = false;
            }

        }

        return $response;
    }

    /**
     * @param string|null $reference
     * @param object|null $bpost
     *
     * @return string|false
     */
    public static function getBpostOrderStatus($bpost = null, $reference = null)
    {
        if (is_null($bpost) || is_null($reference)) {
            return false;
        }

        $status = false;
        // $reference = Tools::substr($reference, 0, 50);

        try {
            /** @var OrderBpost $bpost_order */
            $bpost_order = $bpost->fetchOrder((string) $reference);
            $boxes = $bpost_order->getBoxes();
            foreach ($boxes as $box) {
                $box_status = (string) $box->getStatus();
                if (false === $status) {
                    $status = $box_status;
                } elseif ($status !== $box_status) {
                    $status = 'MULTIPLE';
                }
            }

        } catch (\EontechModException $e) {
            $status = false;
            throw $e;
            // self::logError('getOrderStatus Ref: '.$reference, $e->getMessage(), $e->getCode(), 'Order', isset($order->id) ? $order->id : 0);
        }

        return $status;
    }

    public static function refreshBulkOrderBpostStatus()
    {
        $orders_status = [];
        $shops_id = \Shop::getCompleteListOfShopsID();
        foreach ($shops_id as $id_shop) {
            $proceed = (bool) \Configuration::get('BPOST_USE_PS_LABELS', null, null, (int) $id_shop);
            if ($proceed &&
                ($order_refs = OrderBpost::fetchBulkOrderRefs((int) \BpostShm::DEF_ORDER_BPOST_DAYS, (int) $id_shop))) {
                try {
                    $settings = [
                        'BPOST_ACCOUNT_ID',
                        'BPOST_ACCOUNT_PASSPHRASE',
                        'BPOST_ACCOUNT_API_URL',
                    ];

                    $settings = \Configuration::getMultiple($settings, null, null, (int) $id_shop);
                    $bpost = new \EontechBpostService(
                        $settings['BPOST_ACCOUNT_ID'],
                        $settings['BPOST_ACCOUNT_PASSPHRASE'],
                        $settings['BPOST_ACCOUNT_API_URL']
                    );
                    foreach ($order_refs as $order_ref) {
                        if ($new_status = self::getBpostOrderStatus($bpost, $order_ref['reference'])) {
                            if ($new_status !== (string) $order_ref['status']) {
                                $orders_status[] = [
                                    'id_order_bpost' => $order_ref['id_order_bpost'],
                                    'status'         => (string) $new_status,
                                ];
                            }
                        }
                    }

                } catch (\Exception $e) {
                    $msg = 'refreshBulkOrderBpostStatus';
                    if (isset($order_ref['reference'])) {
                        $msg .= ' Ref:'.$order_ref['reference'];
                    }
                    self::logError($msg, $e->getMessage(), $e->getCode(), 'OrderBpost', 0);
                }
            }
        }

        OrderBpost::updateBulkOrderStatus($orders_status);
    }

    public static function logError($func, $msg, $err_code, $obj, $obj_id)
    {
        $msg_format = 'BpostSHM::Service: '.$func.' - '.$msg;
        \Logger::addLog(
            $msg_format,
            3,
            $err_code,
            $obj,
            (int) $obj_id,
            true
        );
    }

    public static function refreshBpostStatus($bpost = null, $reference = null)
    {
        try {
            if ($status = self::getBpostOrderStatus($bpost, $reference)) {
                if ($bpost_order = OrderBpost::getByReference($reference)) {
                    if ((string) $status !== $bpost_order->status) {
                        $bpost_order->status = $status;
                        $bpost_order->update();
                    }
                }
            }

        } catch (\Exception $e) {
            $status = false;
            self::logError('refreshBpostStatus Ref:'.$reference, $e->getMessage(), $e->getCode(), 'OrderBpost', 0);
        }

        return $status;
    }

    /**
     * Mimic 1.5+ order reference field for 1.4
     *
     * @return String
     */
    public static function generateReference()
    {
        return \Tools::strtoupper(\Tools::passwdGen(9, 'NO_NUMERIC'));
    }

    /**
     * @param int          $type
     * @param string|false $delivery_date dd-mm-YYYY
     *
     * @return array
     */
    public function getNearestValidServicePoint($type = 3, $delivery_date = false)
    {
        $delivery_address = new \Address($this->context->cart->id_address_delivery, $this->context->language->id);
        $result = [
            'city'     => $delivery_address->city,
            'postcode' => $delivery_address->postcode,
        ];

        $try_zones = [
            2 => $delivery_address->postcode.' '.$delivery_address->city,
            1 => $delivery_address->postcode,
            0 => '1000', // last resort brussels
        ];

        $search_params = [
            'street' => '',
            'nr'     => '',
        ];

        if ($delivery_date) {
            $search_params['dd'] = (string) $delivery_date;
        }
        foreach ($try_zones as $valid_key => $zone) {
            $search_params['zone'] = $zone;
            $service_points = $this->getNearestServicePoint($search_params, $type);
            if (!empty($service_points)) {
                break;
            }

        }
        $result['is_valid'] = (bool) $valid_key;
        $result['servicePoints'] = $service_points;

        return $result;
    }

    /**
     * @param array $search_params
     * @param int   $type
     *
     * @return array
     */
    public function getNearestServicePoint($search_params = [], $type = 3)
    {
        $limit = 10;
        $service_points = [];

        $search_params = array_merge(
            [
                'street' => '',
                'nr'     => '',
                'zone'   => '',
                'dd'     => null,
            ], $search_params
        );

        try {
            if ($response = $this->geo6->getNearestServicePoint(
                $search_params['street'], $search_params['nr'], $search_params['zone'],
                $this->context->language->iso_code, $type, $limit, $search_params['dd']
            )) {
                foreach ($response as $row) {
                    $service_points['coords'][] = [
                        $row['poi']->getLatitude(),
                        $row['poi']->getLongitude(),
                    ];
                    $service_points['list'][] = [
                        'id'     => $row['poi']->getId(),
                        'type'   => $row['poi']->getType(),
                        'office' => $row['poi']->getOffice(),
                        'street' => $row['poi']->getstreet(),
                        'nr'     => $row['poi']->getNr(),
                        'zip'    => $row['poi']->getZip(),
                        'city'   => $row['poi']->getCity(),
                    ];
                    $service_points['distance'] = $row['distance'];
                }
            }
        } catch (\EontechModException $e) {
            $service_points = [];
        }

        return $service_points;
    }

    /**
     * @param int $service_point_id
     * @param int $type
     *
     * @return array
     */
    public function getServicePointHours($service_point_id = 0, $type = 3)
    {
        $service_point_hours = [];

        try {
            if ($response = $this->geo6->getServicePointDetails($service_point_id, $this->context->language->iso_code, $type)) {
                if ($service_point_days = $response->getHours()) {
                    foreach ($service_point_days as $day) {
                        $service_point_hours[$day->getDay()] = [
                            'am_open'  => $day->getAmOpen(),
                            'am_close' => $day->getAmClose(),
                            'pm_open'  => $day->getPmOpen(),
                            'pm_close' => $day->getPmClose(),
                        ];
                    }
                }
            }
        } catch (\EontechModException $e) {
            $service_point_hours = [];
        }

        return $service_point_hours;
    }

    /**
     * extract number, street and line2 from address fields
     *
     * @author Serge <serge@stigmi.eu>
     *
     * @param  array $address
     *
     * @return array $address
     */
    public function getAddressStreetNr($address = '')
    {
        if (empty($address) || !is_array($address)) {
            return false;
        }

        $line2 = $address['line2'];
        preg_match('#([0-9]+)?[, ]*([\pL&;\'\. -]+)[, ]*([0-9]+[a-z]*)?[, ]*(.*)?#iu', $address['street'], $matches);
        if (!empty($matches[1])) {
            $nr = $matches[1];
        } elseif (!empty($matches[3])) {
            $nr = $matches[3];
        } elseif (!empty($line2) && is_numeric($line2)) {
            $nr = $line2;
            $line2 = '';
        }

        $address['nr'] = $nr;
        $address['line2'] = !empty($matches[4]) ? $matches[4].(!empty($line2) ? ', '.$line2 : '') : $line2;
        if (!empty($matches[2])) {
            $address['street'] = $matches[2];
        }

        return $address;
    }

    /**
     * @author Serge <serge@stigmi.eu>
     *
     * @param int $id_order
     *
     * @return boolean
     */
    public function prepareBpostOrder($id_order = 0)
    {
        if (empty($id_order) || !is_numeric($id_order)) {
            return false;
        }

        $response = true;
        $ps_order = new \Order((int) $id_order);

        // create a unique reference
        $ref = $ps_order->reference;
        $reference = \Configuration::get('BPOST_ACCOUNT_ID').'_'.\Tools::substr($ps_order->id, 0, 42).'_'.$ref;

        $shm = $this->module->getShmFromCarrierID($ps_order->id_carrier);
        $dm_text = $this->module->shipping_methods[$shm]['slug'];
        // $delivery_method = $this->module->shipping_methods[$shm]['slug'];
        switch ($shm) {
            case \BpostShm::SHM_HOME:
                // service point type & id are no longer valid
                $cart_bpost = CartBpost::getByPsCartID((int) $ps_order->id_cart);
                $cart_bpost->reset();
                //
                if ($address = \Address::getCountryAndState((int) $ps_order->id_address_delivery)) {
                    $country = new \Country((int) $address['id_country']);
                    if ('BE' != $country->iso_code) {
                        // $delivery_method = '@international';
                        $shm = \BpostShm::SHM_INTL;
                        $dm_text = $this->getInternationalSlug();
                    }
                }
                break;

            case \BpostShm::SHM_PPOINT:
                // $delivery_method .= $this->getDeliveryOptionsList('bpost', ':');
                break;

            case \BpostShm::SHM_PLOCKER:
                // $delivery_method .= $this->getDeliveryOptionsList('247', ':');
                $dm_text = 'Parcel locker';
                break;

        }
        $dates = $this->getDropDeliveryDates($shm, $ps_order);
        $options_keys = $this->getEffectiveDeliveryOptions($shm, $ps_order->total_products, $dates['sat']);

        $weight = 0;
        if ((bool) \Configuration::get('BPOST_USE_PS_LABELS')) {
            // Labels managed are within Prestashop
            $shippers = $this->getReceiverAndSender($ps_order, false, self::isAtHome($shm));
            $recipient = $shippers['recipient'];

            $order_bpost = new OrderBpost();
            $order_bpost->reference = (string) $reference;
            $order_bpost->recipient = (string) $recipient;
            $order_bpost->shm = (int) $shm;
            // set drop date if applicable
            if ($dates['drop']) {
                $order_bpost->dt_drop = (int) $dates['drop'];
            }

            $order_bpost->delivery_method = (string) $this->getDeliveryMethodString($dm_text, $options_keys);
            $response = $response && $order_bpost->save();

            // Weight dependant initial quantity of boxes/labels
            if ($products = $ps_order->getProducts()) {
                foreach ($products as $product) {
                    $product_weight = $product['product_weight'];
                    $product_qty = (int) $product['product_quantity'];
                    $weight += $product_qty * $product_weight;
                }
            }

            $weight_info = $this->getSplitBoxInfo((int) $this->getWeightGrams($weight));
            $response = $response && $order_bpost->addLabels((int) $weight_info['boxes']);
            // for ($n_boxes = (int)$weight_info['boxes']; $n_boxes > 0; $n_boxes--)
            // 	$response = $response && $order_bpost->addLabel();

        } else {
            // Send order for SHM only processing
            $bpost_order = new \EontechModBpostOrder($reference);

            // add product lines
            if ($products = $ps_order->getProducts()) {
                foreach ($products as $product) {
                    $product_weight = $product['product_weight'];
                    $product_qty = (int) $product['product_quantity'];
                    $product_name = self::getBpostring($product['product_name']);
                    $line = new \EontechModBpostOrderLine($product_name, $product_qty);
                    $bpost_order->addLine($line);
                    $weight += $product_qty * $product_weight;
                }
            }

            $weight_info = $this->getSplitBoxInfo((int) $this->getWeightGrams($weight));
            $weight = (int) $weight_info['weight'];
            for ($n_boxes = (int) $weight_info['boxes']; $n_boxes > 0; $n_boxes--) {
                $box = $this->createBox($reference, $shm, $ps_order, $weight);
                $bpost_order->addBox($box);
            }

            try {
                $response = $this->bpost->createOrReplaceOrder($bpost_order);

            } catch (\Exception $e) {
                self::logError('prepareBpostOrder Ref: '.$reference, $e->getMessage(), $e->getCode(), 'Order', $id_order);
                $response = false;

            }
        }

        return $response;
    }

    private function getDeliveryMethodString($dm_text, $dm_options)
    {
        $dms = (string) $dm_text;
        if (!empty($dm_options)) {
            $dms .= ':'.implode('|', $dm_options);
        }

        return $dms;
    }

    /**
     * @param null|string $barcode
     *
     * @return bool
     */
    public function createLabelForBox($barcode = null, $with_return_labels = false)
    {
        $response = false;

        if (!is_null($barcode)) {
            $format = \Configuration::get('BPOST_LABEL_PDF_FORMAT');

            try {
                $response = $this->bpost->createLabelForBox($barcode, $format, $with_return_labels, true);
            } catch (\EontechModException $e) {
                $response = false;
            }

        }

        return $response;
    }

    /**
     * @author Serge <serge@stigmi.eu>
     *
     * @param  int         $shm        shipping method
     * @param  float|false $total_cart (cart total before shipping & tax)
     *
     * @return mixed        setting options array | false
     */
    public function getSaturdayDeliveryOption($shm, $total_cart = false)
    {
        $return = false;
        if ($options_list = \Configuration::get('BPOST_DELIVERY_OPTIONS_LIST')) {
            $options_list = \Tools::jsonDecode($options_list, true);
            if (isset($options_list[$shm])) // foreach ($options_list[$shm] as $key => $option)
            {
                foreach ($options_list[$shm] as $option) {
                    if (is_array($option) &&
                        (false === $total_cart ||
                            (float) $option[0] <= (float) $total_cart)) {
                        $return = [
                            'from' => $option[0],
                            'cost' => $option[1],
                        ];
                    }
                }
            }

        }

        return $return;
    }

    public function getDeliveryOptions($selection)
    {
        return $this->module->getDeliveryOptions($selection);
    }

    /**
     * getBpostLink universal getModuleLink for this module
     *
     * @param  array  $params     request params
     * @param  string $controller name
     *
     * @return string                Module front controller link
     */
    /*	public function getBpostLink(array $params = array(), $controller = 'clientbpost', $ssl = null, $id_lang = null, $id_shop = null)
        {
            $ssl = true;
            if (isset($params['mode']))
            {
                $controller = str_replace('-', '', $params['mode']);
                unset($params['mode']);
            }

            return $this->context->link->getModuleLink($this->module->name, $controller, $params, $ssl, $id_lang, $id_shop);
        }
    */
    /**
     * get full list bpost enabled countries
     *
     * @return array
     */
    public function getProductCountries()
    {
        $product_countries_list = 'BE';

        try {
            if ($product_countries = $this->bpost->getProductCountries()) {
                $product_countries_list = implode('|', $product_countries);
            }

        } catch (\Exception $e) {
            return ['Error' => $e->getMessage()];

        }

        return $this->explodeCountryList($product_countries_list);
    }

    /**
     * [explodeCountryList]
     *
     * @param  string $iso_list delimited list of iso country codes
     * @param  string $glue     delimiter
     *
     * @return array            assoc array of ps_countries [iso => name]
     */
    public function explodeCountryList($iso_list, $glue = '|')
    {
        $iso_list = str_replace($glue, "','", pSQL($iso_list));
        $query = '
SELECT
	c.id_country AS id, c.iso_code AS iso, cl.name
FROM
	`'._DB_PREFIX_.'country` c, `'._DB_PREFIX_.'country_lang` cl
WHERE
	cl.id_lang = '.(int) $this->context->language->id.'
AND
	c.id_country = cl.id_country
AND
	c.iso_code IN (\''.$iso_list.'\')
ORDER BY
	name
		';

        $countries = [];
        try {
            $db = \Db::getInstance(_PS_USE_SQL_SLAVE_);
            if ($results = $db->ExecuteS($query)) {
                foreach ($results as $row) {
                    $countries[$row['iso']] = $row['name'];
                }
            }

        } catch (\Exception $e) {
            $countries = [];
        }

        return array_filter($countries);
    }

}
