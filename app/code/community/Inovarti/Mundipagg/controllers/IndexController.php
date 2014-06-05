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
    public function consultAction() {
        // pega pedido correspondente
        $orderId = $this->getRequest()->getParam('order');
        $order = Mage::getModel('sales/order')->load($orderId);

        // pega os dados para requisicao e realiza a consulta
        $payment = $order->getPayment();
        $Api = Mage::getSingleton('mundipagg/api');


        ini_set('soap.wsdl_cache_enabled', '0');

        $parametros = array(
            'queryOrderRequest' => array(
                'MerchantKey' => $Api->getmerchantKey(),
                'OrderKey' => $payment->getOrderKey(),
                'OrderReference' => $payment->getOrderReference()
            )
        );

        $consulta = $Api->getService()->QueryOrder($parametros);

        $msg = $this->__('Mundipagg Information:');
        $msg .= '<br />' . $this->__('Pedido: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderReference . '</strong>');
        $msg .= '<br />' . $this->__('CC Partial: xxxx-%s', '<strong>' . $payment->getCcLast4() . '</strong>');
        $msg .= '<br />' . $this->__('Status: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderStatusEnum . '</strong>');
        $msg .= '<br />' . $this->__('CID Status: %s', '<strong>' . $payment->getCcCidStatus() . '</strong>');
        echo $msg;
        echo '<br /><pre>';

        print_r($consulta->QueryOrderResult);
    }
}