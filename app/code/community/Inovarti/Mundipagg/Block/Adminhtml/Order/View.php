<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Adminhtml_Order_View extends Mage_Adminhtml_Block_Sales_Order_View {

    public function __construct() {
       
        $payment = $this->getOrder()->getPayment();
        $method = $payment->getMethodInstance()->getCode();
        $OrderKey = $payment->getOrderKey();

        if (!empty($OrderKey) && ($method == "mundipagg" || $method == "mundipagg_boleto")) {
            if ($this->_isAllowedAction("mundipagg-consult")) {
                $this->_addButton('inovarti_mundipagg_consult', array
                    (
                    'label' => Mage::helper('mundipagg')->__('Consult WebService'),
                    'onclick' => "loadMundipaggWebServiceData('" . $OrderKey . "', " . $this->getOrder()->getId() . ");",
                    'class' => 'go'
                ));
            }
        }
         parent::__construct();
    }

}
