<?php

class MindArc_FatZebra_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid {
 

    public function addAfterColumn($columnId, $column, $indexColumn) {
        $columns = array();
        foreach ($this->_columns as $gridColumnKey => $gridColumn) {
            $columns[$gridColumnKey] = $gridColumn;
            if ($gridColumnKey == $indexColumn) {
                $columns[$columnId] = $this->getLayout()->createBlock('adminhtml/widget_grid_column')
                        ->setData($column)
                        ->setGrid($this);
                $columns[$columnId]->setId($columnId);
            }
        }
        $this->_columns = $columns;
        return $this;
    }

    protected function _prepareColumns() {
        $return = parent::_prepareColumns();

        $this->addAfterColumn('fraud_result', array(
            'header' => Mage::helper('sales')->__('Fraud Detected'),
            'width' => '15px',
            'type' => 'options',
            'index' => 'fraud_result',
            'filter_condition_callback' => array($this, '_filterFraudResult'),
            'align' => 'center',
            'filter' => 'adminhtml/widget_grid_column_filter_select',
            'options'   => Mage::getSingleton('fatzebra/filterfraud')->getFilter(),
            'renderer' => 'MindArc_FatZebra_Block_Adminhtml_Widget_Grid_Column_Renderer_Fraudresult',
                ), 'status');

        return $return;
    }

    public function setCollection($collection) {
 
        // 1.6.1	
        if ($collection instanceof Mage_Sales_Model_Resource_Order_Grid_Collection) {
           
            $collection->getSelect()
                    ->joinLeft(array('fatzebrafraud_data' => $collection->getTable('fatzebra/fraud')), 'fatzebrafraud_data.order_id=main_table.entity_id', 'fraud_result');
        } else if ($collection instanceof Mage_Core_Model_Mysql4_Collection_Abstract) { // 1.4.1
           
            $collection->getSelect()
                    ->joinLeft(array('fatzebrafraud_data' => $collection->getTable('fatzebra/fraud')), 'fatzebrafraud_data.order_id=main_table.entity_id', 'fraud_result');
        } else if ($collection instanceof Mage_Eav_Model_Entity_Collection_Abstract) {
          
            $collection->joinTable('fatzebra/fraud', 'order_id=entity_id', array("fraud_result" => "fraud_result"), null, "left");
        }

        return parent::setCollection($collection);
    }

    protected function _filterFraudResult($collection, $column) {


        // 1.6.1	
        if ($collection instanceof Mage_Sales_Model_Resource_Order_Grid_Collection) {

            // we have to change this so the join doesn't get reset
            $collection->addFieldToFilter('`fatzebrafraud_data`.fraud_result', $column->getFilter()->getCondition());
            // 1.4.1
        } else if ($collection instanceof Mage_Core_Model_Mysql4_Collection_Abstract) {


            // we have to change this so the join doesn't get reset
            $collection->addFieldToFilter('`fatzebrafraud_data`.fraud_result', $column->getFilter()->getCondition());
        } else {


            $collection->addFieldToFilter($column->getIndex(), $column->getFilter()->getCondition());
        }
    }

}
