<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Info_Boleto extends Mage_Payment_Block_Info
{
    /**
     * Init default template for block
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('inovarti/mundipagg/info/boleto.phtml');
    }
    public function getBoletoUrl() {
        return $this->htmlEscape($this->getInfo()->getBoletoUrl());
    }
}