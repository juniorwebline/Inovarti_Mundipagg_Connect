<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_IndexController extends Mage_Core_Controller_Front_Action {

    public function indexAction() {
        $this->loadLayout();
        $this->renderLayout();
    }
    public function failureAction() {
        /*if (!Mage::getSingleton('core/session')->getData('mundipagg-transaction')) {
            $url = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            Mage::app()->getFrontController()->getResponse()->setRedirect($url);
            return;
        }*/
        
        $orderId = Mage::getSingleton('checkout/session')->getLastOrderId();
        $order = Mage::getModel('sales/order')->load($orderId);
        $payment = $order->getPayment();

        $this->loadLayout();
        $block = $this->getLayout()->getBlock('Inovarti_Mundipagg.failure');
        $this->renderLayout();
    }
}