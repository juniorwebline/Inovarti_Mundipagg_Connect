<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Payment_Form extends Mage_Payment_Block_Form_Cc
{
    protected function _construct()
    {
        parent::_construct();
        
        $this->setTemplate('inovarti/mundipagg/form/payment.phtml');
    }
    public function getInfoDataAdditional($field)
    {
        return $this->htmlEscape($this->getMethod()->getInfoInstance()->getAdditionalInformation($field));
    }
}