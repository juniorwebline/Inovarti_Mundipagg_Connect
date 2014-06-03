<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Source_Cctype extends Mage_Payment_Model_Source_Cctype
{
    public function getAllowedTypes()
    {
        return array('AE', 'VI', 'MC', 'DN', 'EL', 'HI');
    }
    public function toOptionArray()
    {
        $allowed = $this->getAllowedTypes();
        $options = array();

        foreach (Mage::getSingleton('payment/config')->getCcTypes() as $code => $name) {
            if (in_array($code, $allowed) || !count($allowed)) {
                $options[] = array(
                   'value' => $code,
                   'label' => $name
                );
            }
        }

        return $options;
    }
    
}
