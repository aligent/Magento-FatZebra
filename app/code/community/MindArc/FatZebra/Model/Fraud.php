<?php
class MindArc_FatZebra_Model_Fraud extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        $this->_init('fatzebra/fraud');
    }
       function loadByOrderId($orderId)
    {
    	$this->load($orderId, 'order_id');
    	return $this;
    }
}