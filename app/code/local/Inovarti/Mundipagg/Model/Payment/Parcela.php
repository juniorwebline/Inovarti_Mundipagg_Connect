<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Payment_Parcela extends Varien_Object {

    public function toOptionArray() {
        $options = array();

        $options['1'] = Mage::helper('adminhtml')->__('1x - Sem parcelamento');
        $options['2'] = Mage::helper('adminhtml')->__('2x');
        $options['3'] = Mage::helper('adminhtml')->__('3x');
        $options['4'] = Mage::helper('adminhtml')->__('4x');
        $options['5'] = Mage::helper('adminhtml')->__('5x');
        $options['6'] = Mage::helper('adminhtml')->__('6x');
        $options['7'] = Mage::helper('adminhtml')->__('7x');
        $options['8'] = Mage::helper('adminhtml')->__('8x');
        $options['9'] = Mage::helper('adminhtml')->__('9x');
        $options['10'] = Mage::helper('adminhtml')->__('10x');
        $options['11'] = Mage::helper('adminhtml')->__('11x');
        $options['12'] = Mage::helper('adminhtml')->__('12x');


        return $options;
    }

}