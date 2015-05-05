<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class MindArc_FatZebra_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
         $session = $this->getRequest()->getParam('io_bb');
        if($session!=null && $session!="")
            Mage::getSingleton('core/session')->setFatzebraFraud($session);
        $this->loadLayout();
        $this->renderLayout();
    }
    public function sessionAction(){
        echo Mage::getSingleton('core/session')->getFatzebraFraud();
    }
}