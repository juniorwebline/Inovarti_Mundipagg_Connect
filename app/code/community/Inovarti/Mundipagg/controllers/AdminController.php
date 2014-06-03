<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_AdminController extends Mage_Adminhtml_Controller_Action {

    protected $_service = NULL;

    public function consultAction() {
        // verifica se o usuario estah logado na administracao do magento
        Mage::getSingleton('core/session', array('name' => 'adminhtml'));
        $session = Mage::getSingleton('admin/session');

        if (!$session->isLoggedIn()) {
            return;
        }

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

        $consulta = $this->getService()->QueryOrder($parametros);

        $msg = $this->__('Mundipagg Information:');
        $msg .= '<br />' . $this->__('Pedido: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderReference . '</strong>');
        $msg .= '<br />' . $this->__('CC Partial: xxxx-%s', '<strong>' . $payment->getCcLast4() . '</strong>');
        $msg .= '<br />' . $this->__('Status: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderStatusEnum . '</strong>');
        $msg .= '<br />' . $this->__('CID Status: %s', '<strong>' . $payment->getCcCidStatus() . '</strong>');
        echo $msg;
        echo '<br /><pre>';

        print_r($consulta->QueryOrderResult);
    }

    /**
     * 
     * Funcao responsavel por conferir se usuario pode realizar a acao
     * 
     */
    protected function _isAllowed() {
        $action = 'sales/order/actions/mundipagg-' . $this->getRequest()->getActionName();

        return Mage::getSingleton('admin/session')->isAllowed($action);
    }

    protected function _debug($debugData) {
        if (Mage::getStoreConfig('payment/mundipaggsettings/debug')) {
            Mage::log($debugData, null, 'mundipagg_admin_action.log', true);
        }
    }

    protected function getService() {
        if (!is_object($this->_service)) {
            try {
                // start SOAP client
                set_time_limit(0);
                $conexao = Mage::getStoreConfig('payment/mundipaggsettings/service');
                $soap_opt = array(
                    'encoding' => 'UTF-8',
                    'trace' => true,
                    'exceptions' => true
                );
                $service = new SoapClient($conexao, $soap_opt);
                // connection successfull established
                $this->_service = $service;
            } catch (Exception $e) {
                $this->_debug('connection failed: ' . $e->getMessage());
                Mage::throwException(
                        Mage::helper('mundipagg')->__('Não é possível conectar serviços de pagamento. Por favor, tente novamente mais tarde.')
                );
            }
        }
        return $this->_service;
    }

}
