<?php

/**
 * Customer model
 * 
 * @category   MindArc
 * @package    MindArc_Fatzebra
 * @author     (mindarc.com.au)
 * 
*/
 class MindArc_FatZebra_Model_Customer extends Mage_Core_Model_Abstract
{
    protected $_customer = null;
    public function __construct() {
        parent::__construct();
        $customerId = Mage::getSingleton('customer/session')->getId();
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if (is_object($customer) || $customer->getId()) {
            $this->_customer = $customer;
        }
    }
    public function saveData($result)
    {
         try {
            if (!is_null($this->_customer)) {
         
                $this->_customer->setData('fatzebra_token', $result->response->card_token);
                $this->_customer->setData('fatzebra_masked_card_number', $result->response->card_number);
                $this->_customer->setData('fatzebra_expiry_date', $result->response->card_expiry);
                
                $this->_customer->save();
            }
            }
         catch (Exception $e) {
            Mage::throwException($e->getMessage());
            
            }
        
    }
    public function getCustomerToken() {
        if (!is_null($this->_customer)) {
            return $this->_customer->getData('fatzebra_token');
        }
        return false;
    }
    
    public function getMaskedCardNumber() {
        if (!is_null($this->_customer)) {
            return $this->_customer->getData('fatzebra_masked_card_number');
        }
        return false;
    }

    public function getSavedCardExpiryDate() {
        if (!is_null($this->_customer)) {
            return $this->_customer->getData('fatzebra_expiry_date');
        }
        return false;
    }
}