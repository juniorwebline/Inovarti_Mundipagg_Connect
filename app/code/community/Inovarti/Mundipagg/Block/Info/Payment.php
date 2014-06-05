<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Info_Payment extends Mage_Payment_Block_Info_Cc {

    protected function _construct() {
        parent::_construct();
        $this->setTemplate('inovarti/mundipagg/info/payment.phtml');
    }

    public function getCcCpf() {
        return $this->htmlEscape($this->getInfo()->getCcCpf());
    }

    public function getCcParcelamento() {
        return $this->htmlEscape($this->getInfo()->getCcParcelamento());
    }
    public function getCcAmountInCents() {
        return $this->htmlEscape($this->getInfo()->getCcValor());
    }

}
