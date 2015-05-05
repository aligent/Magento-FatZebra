<?php

class MindArc_FatZebra_Block_Adminhtml_Widget_Grid_Column_Renderer_Fraudresult extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {

    public function render(Varien_Object $row) {        
        return $row->getFraudResult()!=""?$row->getFraudResult():"";
       
    }

}
