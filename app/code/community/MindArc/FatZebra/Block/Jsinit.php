<?php

class MindArc_FatZebra_Block_Jsinit extends Mage_Adminhtml_Block_Template
{
    /**
     * Include JS in head if section is fatzebra
     */
    protected function _prepareLayout()
    {
        $section = $this->getAction()->getRequest()->getParam('section', false);
        if ($section == 'payment') {
            $this->getLayout()
                ->getBlock('head')
                ->addJs('fatzebra/fatzebra.js');
        }
        parent::_prepareLayout();
    }

    /**
     * Print init JS script into body
     * @return string
     */
    protected function _toHtml()
    {
        $section = $this->getAction()->getRequest()->getParam('section', false);
        if ($section == 'payment') {
            return parent::_toHtml();
        } else {
            return '';
        }
    }
}
