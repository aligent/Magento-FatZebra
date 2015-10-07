<?php

class MindArc_FatZebra_Model_Payment extends Mage_Payment_Model_Method_Cc
{

    const VERSION = "2.1.2";

    // Fraud Check Data Scrubbing...
    const RE_ANS = "/[^A-Z\d\-_',\.;:\s]*/i";
    const RE_AN = "/[^A-Z\d]/i";
    const RE_NUMBER = "/[^\d]/";

    protected $_code = 'fatzebra';
    protected $_isGateway = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canRefund = true;
    protected $_canVoid = false;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = true;
    protected $_canReviewPayment = true;
    protected $_canSaveCc = false;
    protected $_formBlockType = 'fatzebra/form';

    // Allow partial refund
    protected $_canRefundInvoicePartial = true;

    /**
     * Assign data to info model instance
     *
     * @param   mixed $data
     * @return MindArc_FatZebra_Model_Payment
     */
    public function assignData($data)
    {
        parent::assignData($data);

        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $info->setCcNumber($data->getCcNumber());
        $post = Mage::app()->getFrontController()->getRequest()->getPost();
        if (isset($post['payment']['cc_save'])) {
            Mage::getSingleton('core/session')->setFatZebraCcSave($post['payment']['cc_save']);
        }

        return $this;
    }

    /**
     * Performs a capture (full purchase transaction)
     * @param $payment the payment object to process
     * @param $amount the amount to be charged, as a decimal
     *
     * @return MindArc_FatZebra_Model_Payment
     */
    public function CardType($number)
    {
        if(preg_match("/^4/", $number))
            return "VI";
        if(preg_match("/^5/", $number))
            return "MC";
        if(preg_match("/^(34|37)/", $number))
            return "AE";
        if(preg_match("/^(36)/", $number))
            return "DIC";
        if(preg_match("/^(35)/", $number))
            return "JCB";
        if(preg_match("/^(65)/", $number))
            return "DI";
    }
    public function capture(Varien_Object $payment, $amount)
    {
        $this->setAmount($amount)->setPayment($payment);
        $info = $this->getInfoInstance();
        
        $result = $this->process_payment($payment);
        if (isset($result->successful) && $result->successful) {
            if ($result->response->successful) {
                if (isset($_POST['use_saved_card']) && $_POST['use_saved_card'] == 1) {
                         $this->getInfoInstance()->setCcOwner($result->response->card_holder)
                        ->setCcLast4(substr($result->response->card_number, 12))
                        ->setCcExpMonth(substr($result->response->card_expiry, -5, 2))                       
                        ->setCcExpYear(substr($result->response->card_expiry, 0, 4))
                        ->setCcType($this->CardType($result->response->card_number));
                        
                }
                $order = $payment->getOrder();
                if(Mage::getSingleton('core/session')->getFatZebraCcSave()==1){
                    Mage::getSingleton('core/session')->setFatZebraResult($result);
                }
                // TODO: This should set the order/payment result to 'FRAUD', whereas currently it sets the order to Processing
                // However, the code below, setting to status_fraud etc doesn't seem to do anything...
                // Make sure we have a fraud_result - if ReD is disabled by FZ (e.g. ReD unavailable etc) this will not be present
                if (property_exists($result->response, 'fraud_result') && ($result->response->fraud_result && $result->response->fraud_result == 'Challenge')) {
                    //$payment->setStatus(Mage_Sales_Model_Order::STATUS_FRAUD);
              
                   $payment->setLastTransId($result->response->id);
                   $payment->setTransactionId($result->response->id);
                   $payment->registerCaptureNotification($amount, false);
                   $payment->setIsTransactionPending(true)
                                ->setIsFraudDetected(true)
                                ->setSkipTransactionCreation(true)
                                ->setIsTransactionClosed('');
                
                    
                } else {
                    //$payment->setStatus(Mage_Sales_Model_Order::STATUS_APPROVED);
                    $payment->setLastTransId($result->response->id);
                    $payment->setTransactionId($result->response->id);
                    $invoice = $order->getInvoiceCollection()->getFirstItem();
                    if ($invoice && !$invoice->getEmailSent()) {
                        $invoice->pay(); // Mark the invoice as paid
                        $invoice->addComment("Payment made by Credit Card. Reference " . $result->response->id . ", Masked number: " . $result->response->card_number, false, true);
                        $invoice->sendEmail();
                        $invoice->save();
                    }
                }
            } else {
                Mage::throwException(Mage::helper('fatzebra')->__("Unable to process payment: %s", $result->response->message));
            }
        } else {
            $message = Mage::helper('fatzebra')->__('There has been an error processing your payment. %s', implode(", ", $result->errors));
            Mage::throwException($message);
        }
        return $this;
    }
 
    /**
     * Refunds a payment
     *
     * @param $payment the payment object
     * @param $amount the amount to be refunded, as a decimal
     *
     * @return MindArc_FatZebra_Model_Payment
     */
    public function refund(Varien_Object $payment, $amount)
    {
        $result = $this->process_refund($payment, $amount);

        if (isset($result->successful) && $result->successful) {
            if ($result->response->successful) {
                $payment->setStatus(self::STATUS_SUCCESS);
                return $this;
            } else {
                Mage::throwException(Mage::helper('fatzebra')->__("Error processing refund: %s", $result->response->message));
            }
        }
        Mage::throwException(Mage::helper('fatzebra')->__("Error processing refund: %s", implode(", ", $result->errors)));
    }

    /**
     * Builds the refund payload and submits
     *
     * @param $payment the object to reference
     * @param $amount the refund amount, as a decimal
     *
     * @return StdObject response
     */
    private function process_refund($payment, $amount)
    {
        $amt = round($amount * 100);
        $amt = (int)$amt;
        $payload = array("transaction_id" => $payment->getLastTransId(),
            "amount" => (int)$amt,
            "reference" => $payment->getRefundTransactionId());

        return $this->_post("refunds", $payload, $payment->getOrder()->getStoreId());
    }

    /**
     * Builds the refund payload and submits
     *
     * @param $payment the object to reference
     *
     * @return StdObject response
     */
    private function process_payment($payment)
    {
        $amt = round($this->amount * 100);
        $amt = (int)$amt;

        $info = $this->getInfoInstance();
        $order = $payment->getOrder();
        $billing_addr = $order->getBillingAddress();
        $shipping_addr = $order->getShippingAddress();
        $reference = $order->getIncrementId();
        $customer_ip = null;
        if (!is_null($_SERVER['REMOTE_ADDR'])) {
            $ips_ = explode(',', $_SERVER['REMOTE_ADDR']);
            $customer_ip = isset($ips_[0]) && $ips_[0] != '' ? $ips_[0] : null;
        }

        $forwarded_for = null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips_ = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $forwarded_for = isset($ips_[0]) && $ips_[0] != '' ? $ips_[0] : null;
        }

        $fraud_detected = (boolean)Mage::getStoreConfig('payment/fatzebra/fraud_detected');
        if (isset($_POST['use_saved_card']) && $_POST['use_saved_card'] == 1) {
            $fatzebraCustomer = Mage::getModel('fatzebra/customer');
            $payload = array(
                "amount" => $amt,
                "currency" => Mage::app()->getStore()->getBaseCurrencyCode(),
                "reference" => $order->getIncrementId(),
                "card_token" => $fatzebraCustomer->getCustomerToken(),
                "customer_ip" => empty($forwarded_for) ? $customer_ip : $forwarded_for
            );
        } else {
            $payload = array(
                "amount" => $amt,
                "currency" => Mage::app()->getStore()->getBaseCurrencyCode(),
                "reference" => $order->getIncrementId(),
                "card_holder" => str_replace('&', '&amp;', $info->getCcOwner()),
                "card_number" => $info->getCcNumber(),
                "card_expiry" => $info->getCcExpMonth() . "/" . $info->getCcExpYear(),
                "cvv" => $info->getCcCid(),
                "customer_ip" => empty($forwarded_for) ? $customer_ip : $forwarded_for
            );
        }
        // If a token is being used replace the card details (which will be masked) with the token
        if (isset($_POST['payment']['cc_token']) && !empty($_POST['payment']['cc_token'])) {
            $payload['card_token'] = $_POST['payment']['cc_token'];
            unset($payload['card_number']);
            unset($payload['card_holder']);
            unset($payload['card_expiry']);

            // Keep the CVV if present.
            if(empty($payload['cvv'])) {
                unset($payload['cvv']);
            }
        }

        if ($order->getCustomerIsGuest() == 0) {
            $existing_customer = 'true';
            $customer_id = $order->getCustomerId();
            $customer = Mage::getModel('customer/customer')->load($customer_id);
            $customer_created_at = date('c', strtotime($customer->getCreatedAt()));

            if ($customer->getDob() != '') {
                $customer_dob = date('c', strtotime($customer->getDob()));
            } else {
                $customer_dob = '';
            }
        } else {
            $existing_customer = 'false';
            $customer_id = '';
            $customer_created_at = '';
            $customer_dob = '';
        }

        if ($fraud_detected) {
            $ordered_items = $order->getAllItems();
            foreach ($ordered_items as $item) {
                $item_name = $item->getName();
                $item_id = $item->getProductId();
                $_newProduct = Mage::getModel('catalog/product')->load($item_id);
                $item_sku = $_newProduct->getSku();

                $order_items[] = array("cost" => (float)$item->getPrice(),
                    "description" => $this->cleanForFraud($item_name, self::RE_ANS, 26),
                    "line_total" => (float)$item->getRowTotalInclTax(),
                    "product_code" => $this->cleanForFraud($item_id, self::RE_ANS, 12, 'left'),
                    "qty" => (int)$item->getQtyOrdered(),
                    "sku" => $this->cleanForFraud($item_sku, self::RE_ANS, 12, 'left'));
            }

            $billingaddress = $order->getBillingAddress();
            $shippingaddress = $order->getShippingAddress();
            $payload["fraud"] = array(
                "customer" =>
                    array(
                        "address_1" => $this->cleanForFraud($billing_addr->getStreetFull(), self::RE_ANS, 30),
                        "city" => $this->cleanForFraud($billing_addr->getCity(), self::RE_ANS, 20),
                        "country" => $this->cleanForFraud(Mage::getModel('directory/country')->load($billing_addr->getCountry())->getIso3Code(), self::RE_AN, 3),
                        "created_at" => $customer_created_at,
                        "date_of_birth" => $customer_dob,
                        "email" => $order->getCustomerEmail(),
                        "existing_customer" => $existing_customer,
                        "first_name" => $this->cleanForFraud($order->getCustomerFirstname(), self::RE_ANS, 30),
                        "home_phone" => $this->cleanForFraud($billingaddress->getTelephone(), self::RE_NUMBER, 19),
                        "id" => $this->cleanForFraud($customer_id, self::RE_ANS, 16),
                        "last_name" => $this->cleanForFraud($order->getCustomerLastname(), self::RE_ANS, 30),
                        "post_code" => $this->cleanForFraud($billing_addr->getPostcode(), self::RE_AN, 9)
                    ),
                "device_id" => isset($_POST['payment']['io_bb']) ? $_POST['payment']['io_bb'] : '',
                "items" => $order_items,
                "recipients" => array(
                    array("address_1" => $this->cleanForFraud($billingaddress->getStreetFull(), self::RE_ANS, 30),
                        "city" => $this->cleanForFraud($billingaddress->getCity(), self::RE_ANS, 20),
                        "country" => $this->cleanForFraud(Mage::getModel('directory/country')->load($billingaddress->getCountryId())->getIso3Code(), self::RE_AN, 3),
                        "email" => $billingaddress->getEmail(),
                        "first_name" => $this->cleanForFraud($billingaddress->getFirstname(), self::RE_ANS, 30),
                        "last_name" => $this->cleanForFraud($billingaddress->getLastname(), self::RE_ANS, 30),
                        "phone_number" => $this->cleanForFraud($billingaddress->getTelephone(), self::RE_NUMBER, 19),
                        "post_code" => $this->cleanForFraud($billingaddress->getPostcode(), self::RE_AN, 9),
                        "state" => $this->stateMap($billingaddress->getRegion())
                    )
                ),
                "shipping_address" => array(
                    "address_1" => $this->cleanForFraud($shippingaddress->getStreetFull(), self::RE_ANS, 30),
                    "city" => $this->cleanForFraud($shippingaddress->getCity(), self::RE_ANS, 20),
                    "country" => $this->cleanForFraud(Mage::getModel('directory/country')->load($shippingaddress->getCountryId())->getIso3Code(), self::RE_AN, 3),
                    "email" => $shippingaddress->getEmail(),
                    "first_name" => $this->cleanForFraud($shippingaddress->getFirstname(), self::RE_ANS, 30),
                    "last_name" => $this->cleanForFraud($shippingaddress->getLastname(), self::RE_ANS, 30),
                    "home_phone" => $this->cleanForFraud($shippingaddress->getTelephone(), self::RE_NUMBER, 19),
                    "post_code" => $this->cleanForFraud($shippingaddress->getPostcode(), self::RE_AN, 9),
                    "shipping_method" => $this->getFraudShippingMethod($order)
                ),
                "custom" => array("3" => "Facebook"),
                "website" => Mage::getBaseUrl()
            );
        }
        if ($existing_customer == 'false') {
            unset($payload["fraud"]['customer']['created_at']);
            unset($payload["fraud"]['customer']['date_of_birth']);
        } else if ($customer_dob == '') {
            unset($payload["fraud"]['customer']['date_of_birth']);
        }

        try {
            $this->fzlog("{$reference}: Submitting payment for {$payload["reference"]}.");
             
            $response = $this->_post("purchases", $payload, $payment->getOrder()->getStoreId());
        } catch (Exception $e) {
            $exMessage = $e->getMessage();
            $this->fzlog("{$reference}: Payment request failed ({$exMessage}) - querying payment from Fat Zebra", Zend_Log::WARN);
            try {
                $response = $this->_fetch("purchases", $reference);
            } catch (Exception $e) {
                $exMessage = $e->getMessage();
                $this->fzlog("{$reference}: Payment request failed after query ({$exMessage}).", Zend_Log::ERR);
            }

            return false;
        }

        if ($response->successful) {
            $success = (bool)$response->successful && (bool)$response->response->successful;
            $txn_result = $response->response->message;
            $fz_id = $response->response->id;
            $reference = $response->response->reference;
            $order_id = Mage::getModel('sales/order')->loadByIncrementId($reference)->getId();
            $model = Mage::getModel('fatzebra/fraud')->loadByOrderId($order_id);
            if (!$model->getId()) {
                // Make sure we have a fraud_result - if ReD is disabled by FZ (e.g. ReD unavailable etc) this will not be present
                if (property_exists($response->response, 'fraud_result')) {
                    $fraud_result = $response->response->fraud_result;
                    $fraud_fraud_messages = $response->response->fraud_messages;
                    $model->setCreatedAt(now());
                    $model->setOrderId($order_id);
                    $model->setFraudResult($fraud_result);
                    $model->setFraudMessagesTitle(isset($fraud_fraud_messages[0]) ? $fraud_fraud_messages[0] : "");
                    $model->setFraudMessagesDetail(isset($fraud_fraud_messages[1]) ? $fraud_fraud_messages[1] : "");
                    $model->save();
                }
            }
            
            $this->fzlog("{$reference}: Payment outcome: Successful, Result - {$txn_result}, Fat Zebra ID - {$fz_id}.");
        }

        if (!empty($response->errors)) {
            foreach ($response->errors as $err) {
                $this->fzlog("{$reference}: Error - {$err}", Zend_Log::ERR);
            }
        }
        return $response;
    }

    /**
     * Fetch the URL from the Fat Zebra Gateway
     * @param $path the URI to fetch the data from (e.g. purchases, refunds etc)
     * @param $payload string ID for the transaction
     *
     * @return StdObject response
     */
    private function _fetch($path, $id)
    {
        $path = $path . "/" . urlencode($id);
        return $this->_request($path, Zend_Http_Client::GET);
    }

    /**
     * Posts the request to the Fat Zebra gateway
     * @param $path the URI to post the data to (e.g. purchases, refunds etc)
     * @param $payload assoc. array for the payload
     *
     * @return StdObject response
     */
    private function _post($path, $payload, $store_id)
    {
        return $this->_request($path, Zend_Http_Client::POST, $payload, $store_id);
    }

    private function _request($path, $method = Zend_Http_Client::GET, $payload = null, $store_id)
    {
        $username = Mage::getStoreConfig('payment/fatzebra/username', $store_id);
        $token = Mage::getStoreConfig('payment/fatzebra/token', $store_id);
        $sandbox = (boolean)Mage::getStoreConfig('payment/fatzebra/sandbox', $store_id);
        $testmode = (boolean)Mage::getStoreConfig('payment/fatzebra/testmode', $store_id);

        $url = $sandbox ? "https://gateway.sandbox.fatzebra.com.au" : "https://gateway.fatzebra.com.au";

        if ($testmode)
            $payload["test"] = true;
        $uri = $url . "/v1.0/" . $path;

        $client = new Varien_Http_Client();
        $client->setUri($uri);
        $client->setAuth($username, $token);
        $client->setMethod($method);
        if ($method == Zend_Http_Client::POST) {
            $client->setRawData(json_encode($payload));
        }
        $client->setConfig(array('maxredirects' => 0,
            'timeout' => 30,
            'useragent' => 'User-Agent: Fat Zebra Magento Library ' . self::VERSION
        ));

        try {
            $response = $client->request();
        } catch (Exception $e) {
            $exMessage = $e->getMessage();
            $this->fzlog("{$path}: Fetching purchase failed: {$exMessage}", Zend_Log::ERR);
            Mage::logException($e);
            Mage::throwException(Mage::helper('fatzebra')->__("Gateway Error: %s", $e->getMessage()));
        }

        $responseBody = $response->getBody();
        $response = json_decode($responseBody);
        if (is_null($response)) {
            $response = array("successful" => false,
                "result" => null);
            $err = json_last_error();
            if ($err == JSON_ERROR_SYNTAX) {
                $result["errors"] = array("JSON Syntax error. JSON attempted to parse: " . $data);
            } elseif ($err == JSON_ERROR_UTF8) {
                $result["errors"] = array("JSON Data invalid - Malformed UTF-8 characters. Data: " . $data);
            } else {
                $result["errors"] = array("JSON parse failed. Unknown error. Data:" . $data);
            }
        }
        return $response;
    }

    /**
     *
     * Log a message to the gateway log
     * @param $message string the message to be logged
     * @param $level int the log level, from Zend_Log::* (http://framework.zend.com/manual/1.12/en/zend.log.overview.html#zend.log.overview.builtin-priorities)
     */
    function fzlog($message, $level = Zend_Log::INFO)
    {
        Mage::log($message, $level, "FatZebra_gateway.log");
    }

    /** Cleans the data for fraud check which has some data restrictions
     * @param $data string the input data
     * @param $pattern string the regex pattern to use for scrubbing
     * @param $maxlen int the maximum length of tha data (to truncate)
     * @param $trimDirection string the direction of truncation - right (default) or left (trim from the start).
     */
    function cleanForFraud($data, $pattern, $maxlen, $trimDirection = 'right')
    {
        $data = preg_replace($pattern, '', $this->toASCII($data));
        $data = preg_replace('/[\r\n]/', ' ', $data);
        if (strlen($data) > $maxlen) {
            if ($trimDirection == 'right') {
                return substr($data, 0, $maxlen);
            } else {
                return substr($data, -1, $maxlen);
            }
        } else {
            return $data;
        }
    }

    // Borrowed from http://stackoverflow.com/questions/3542717/how-to-remove-accents-and-turn-letters-into-plain-ascii-characters
    /** Translates accented characters, ligatures etc to the latin equivalent.
     * @param $str string the input to be translated
     * @return string output once translated
     */
    function toASCII( $str )
    {
        return strtr(utf8_decode($str),
            utf8_decode(
                'ŠŒŽšœžŸ¥µÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýÿ'),
            'SOZsozYYuAAAAAAACEEEEIIIIDNOOOOOOUUUUYsaaaaaaaceeeeiiiionoooooouuuuyy');
    }

    /**
     * Validate payment method information object
     *
     * @param   Mage_Payment_Model_Info $info
     * @return  Mage_Payment_Model_Abstract
     */
    public function validate()
    {
       if (isset($_POST['use_saved_card'])) {
            return $this;
        }
        if (isset($_POST['payment']['cc_token']) && !empty($_POST['payment']['cc_token'])) {
            // Bypass if we are tokenized...
            return $this;
        }

        return parent::validate();
    }

   public function acceptPayment(Mage_Payment_Model_Info $payment)
   {
        Mage::log("acceptPayment");
        parent::acceptPayment($payment);
        return true;
   }

    public function denyPayment(Mage_Payment_Model_Info $payment) {
        Mage::log("denyPayment");
        parent::denyPayment($payment);
        return true;
    }

    public function getFraudShippingMethod(Mage_Sales_Model_Order $order) {
        // Load Configs
        // See which method is mapped to which code
        // Return code or 'other'

        $shipping = $order->getShippingMethod();

        $method_lowcost = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_lowcost'));
        $method_overnight = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_overnight'));
        $method_sameday = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_sameday'));
        $method_pickup = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_pickup'));
        $method_express = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_express'));
        $method_international = explode(',', Mage::getStoreConfig('payment/fatzebra/fraud_ship_international'));

        if (in_array($shipping, $method_lowcost)) {
            return 'low_cost';
        }

        if (in_array($shipping, $method_overnight)) {
            return 'overnight';
        }

        if (in_array($shipping, $method_sameday)) {
            return 'same_day';
        }

        if (in_array($shipping, $method_pickup)) {
            return 'pickup';
        }

        if (in_array($shipping, $method_express)) {
            return 'express';
        }

        if (in_array($shipping, $method_international)) {
            return 'international';
        }

        return 'other';
    }

    // Maps AU States to the codes... otherwise return the state scrubbed for fraud....
    public function stateMap($stateName) {
        $states = array('Australia Capital Territory' => 'ACT',
                        'New South Wales' => 'NSW',
                        'Northern Territory' => 'NT',
                        'Queensland' => 'QLD',
                        'South Australia' => 'SA',
                        'Tasmania' => 'TAS',
                        'Victoria' => 'VIC',
                        'Western Australia' => 'WA');

        if (array_key_exists($stateName, $states)) {
            return $states[$stateName];
        } else {
            return $this->cleanForFraud($stateName, self::RE_AN, 10);
        }
    }

}
