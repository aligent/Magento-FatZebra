<?php
class MindArc_FatZebra_Block_Form extends Mage_Payment_Block_Form_Cc
{
      protected function _construct()
      {
        parent::_construct();
        $this->setTemplate('mindarc/fatzebra/form.phtml');
      }
      
      public function canSave() {
        $cansave = Mage::getStoreConfig('payment/fatzebra/can_save');
        $isLoggedIn = Mage::getSingleton('customer/session')->isLoggedIn();        
        $isRegister= Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod() == "register";
        
        
        return ($cansave && ($isLoggedIn || $isRegister));
      }
      public function hasCustomerToken(){
         $fatzebraCustomer = Mage::getModel('fatzebra/customer');
          return $fatzebraCustomer->getCustomerToken();
      }
      public function getMaskedCardNumber() {
          $fatzebraCustomer = Mage::getModel('fatzebra/customer');
          return $fatzebraCustomer->getMaskedCardNumber();
     }

    public function getSavedCardExpiryDate() {
          $fatzebraCustomer = Mage::getModel('fatzebra/customer');
          return date_parse($fatzebraCustomer->getSavedCardExpiryDate());
     }

     public function getSavedCardNotExpired() {
          $fatzebraCustomer = Mage::getModel('fatzebra/customer');
          $date = date_parse($fatzebraCustomer->getSavedCardExpiryDate());
          return ($date['year'] > date('Y')) || (
              $date['year'] == date('Y') && $date['month'] >= date('m')
            );
     }

     public function getStoredCardType() {
       $number = $this->getMaskedCardNumber();

       $prefix = substr($number, 0, 2);
       switch(substr($prefix, 0, 1)) {
        case '4':
          return 'VI';
          break;
        case '5':
          return 'MC';
          break;
        case '6':
          return 'DI';
          break;
        case '3':
          switch(substr($prefix, 1, 1)) {
            case '4':
            case '7':
              return 'AE';
              break;
            case '6':
              return 'DIC';
              break;
            case '5':
              return 'JCB';
              break;
          }
          break;
       }
     }
}