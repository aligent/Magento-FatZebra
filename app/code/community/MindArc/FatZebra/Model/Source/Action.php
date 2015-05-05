<?php

class MindArc_FatZebra_Model_Source_Action {

    public function toOptionArray() {
        return array(
            array(
                'value' => Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('core')->__('Authorise & Capture')
            )
        );
    }

}
