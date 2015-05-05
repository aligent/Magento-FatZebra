<?php
$installer = $this;
$installer->startSetup();

$installer->addAttribute('customer', 'fatzebra_token', array(
    'label'         => 'Fat Zebra Token',
    'visible'       => 1,
    'required'      => 0, 
    'position'      => 1,
    'sort_order'    => 82
));
$installer->addAttribute('customer', 'fatzebra_masked_card_number', array(
    'label'         => 'Fat Zebra Masked Card Number',
    'visible'       => 1,
    'required'      => 0,
    'position'      => 1,
    'sort_order'    => 83
));
$installer->addAttribute('customer', 'fatzebra_expiry_date', array(
    'label'         => 'Fat Zebra Expriy Date',
    'visible'       => 1,
    'required'      => 0,
    'position'      => 1,
    'sort_order'    => 84 
));

$customerattrubute = Mage::getModel('customer/attribute')->loadByCode('customer', 'fatzebra_masked_card_number');
$forms=array('adminhtml_customer');
$customerattrubute->setData('used_in_forms', $forms);
$customerattrubute->save();

$installer->endSetup();

