<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Mundipagg extends Mage_Core_Block_Template {

    public function _prepareLayout() {
        return parent::_prepareLayout();
    }

    public function getMundipagg() {
        if (!$this->hasData('mundipagg')) {
            $this->setData('mundipagg', Mage::registry('mundipagg'));
        }
        return $this->getData('mundipagg');
    }

}