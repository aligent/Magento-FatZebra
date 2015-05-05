<?php

class MindArc_FatZebra_Model_Filterfraud extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
    }
    public function getFilter()
    {
        $resource = Mage::getSingleton('core/resource');
        $readConnection = $resource->getConnection('core_read');
        $query = "SELECT  `fraud_result` FROM  `fatzebrafraud_data` GROUP BY  `fraud_result` ";
        $results = $readConnection->fetchAll($query);
        $options = array();
        foreach($results as $status)
            $options[$status['fraud_result']]=$status['fraud_result'];
        return $options;
    }
}