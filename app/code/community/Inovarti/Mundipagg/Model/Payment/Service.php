<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Payment_Service extends Varien_Object
{
    static public function toOptionArray()
    {
        return array(
            array(
            	'value' => '', 
            	'label' => 'Produção'),
            array(
            	'value' => '1', 
            	'label' => 'Homologação'),
            array(
            	'value' => '0', 
            	'label' => 'Tef'),
        );
    }
}