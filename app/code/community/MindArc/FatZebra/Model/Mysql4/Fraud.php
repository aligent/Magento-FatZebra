<?php


class MindArc_FatZebra_Model_Mysql4_Fraud extends Mage_Core_Model_Mysql4_Abstract
{
    protected function _construct()
    {
        $this->_init('fatzebra/fraud', 'entity_id');
    }
}