<?php
class MindArc_FatZebra_Model_Observer 
{
     public function paymentMethodIsActive(Varien_Event_Observer $observer) {
        $event           = $observer->getEvent();
        $method          = $event->getMethodInstance();
        $result          = $event->getResult();
        $fatzebraCustomer = Mage::getModel('fatzebra/customer');
        if($result->isAvailable)
        {
            if(!$fatzebraCustomer->getCustomerToken()&&$method->getCode()=="fatzebra_saved_cc"){
                    $result->isAvailable = false;
            }
        }

    }
    
    public function handleSuccessAction(Varien_Event_Observer $observer)
    {
        if(Mage::getSingleton('core/session')->getFatZebraCcSave()==1){
            $fatzebraCustomerModel = Mage::getModel('fatzebra/customer');
            $fatzebraCustomerModel->saveData(Mage::getSingleton('core/session')->getFatZebraResult());            
        }
        Mage::getSingleton('core/session')->unsFatZebraCcSave();
        Mage::getSingleton('core/session')->unsFatZebraResult();
    }
}