<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_AdminController extends Mage_Adminhtml_Controller_Action {

    protected $_service = NULL;

    /**
     * 
     * Funcao responsavel por consultar o status de uma transacao no WebService da 
     * Cielo
     * 
     */
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

        $this->_debug('consulta():QueryOrderRequest=' . print_r($parametros, 1));
        $consulta = $this->getService()->QueryOrder($parametros);
        $this->_debug('consulta():$resultado=' . print_r($consulta, 1));

        
        
        $msg = $this->__('Mundipagg Information:');
        $msg .= '<br />' . $this->__('Pedido: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderReference. '</strong>');
        $msg .= '<br />' . $this->__('CC Partial: xxxx-%s', '<strong>' . $payment->getCcLast4() . '</strong>');
        $msg .= '<br />' . $this->__('Status: %s', '<strong>' . $consulta->QueryOrderResult->OrderDataCollection->OrderData->OrderStatusEnum. '</strong>');
        $msg .= '<br />' . $this->__('CID Status: %s', '<strong>' . $payment->getCcCidStatus() . '</strong>');
        echo $msg;
        echo '<br /><pre>';

        //echo $this->get_PaymentReference($consulta);

        print_r($consulta->QueryOrderResult);

        /*
          foreach ($consulta->QueryOrderResult as $transKey => $value) {
          $resultado = $transKey." : ".$value;

          if ($transKey=='OrderDataCollection'){
          foreach ($transKey->OrderDataCollection->OrderData as $transKey => $value) {
          $resultado = $transKey." : ".$value;
          if ($transKey=='CreditCardTransactionDataCollection') {
          foreach ($transKey->CreditCardTransactionDataCollection->CreditCardTransactionData as $transKey => $value) {
          $resultado = $transKey . " : " . $value;
          }
          }
          }
          }
          echo UTF8_Encode("</br>".$resultado);
          } */
        //$xml = new SimpleXMLElement($consulta->QueryOrderResult);
        //$this->getResponse()->setBody(Mage::helper('mundipagg')->xmlToHtml($xml));
    }

    /**
     * 
     * Funcao responsavel por enviar o pedido de captura para o WebService da Cielo
     * 
     */
    public function captureAction() {
        // verifica se o usuario estah logado na administracao do magento
        Mage::getSingleton('core/session', array('name' => 'adminhtml'));
        $session = Mage::getSingleton('admin/session');

        if (!$session->isLoggedIn()) {
            return;
        }

        // pega pedido correspondente
        $orderId = $this->getRequest()->getParam('order');
        $order = Mage::getModel('sales/order')->load($orderId);

        $payment = $order->getPayment();
        $Api = Mage::getSingleton('mundipagg/api');

        if ($payment->getTransactionStatus() == 'Captured') {
            return $this;
        }


        //Prepare data in order to capture
        if ($payment->getOrderKey()) {

            ini_set('soap.wsdl_cache_enabled', '0');

            $parametros = array(
                'manageOrderRequest' => array(
                    'MerchantKey' => $Api->getmerchantKey(),
                    'OrderKey' => $payment->getOrderKey(),
                    'OrderReference' => $payment->getOrderReference(),
                    'ManageOrderOperationEnum' => 'Capture'
                )
            );

            $this->_debug('capture():manageOrderRequest=' . print_r($parametros, 1));
            $capture = $this->getService()->ManageOrder($parametros);
            $this->_debug('capture():$resultado=' . print_r($capture, 1));

            $total = count($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
            //DIMINUIR -1 O FOR COMEÇA COM 0
            $totalKey = count($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult) - 1;
        
            if (isset($capture->ManageOrderResult->Success) && $capture->ManageOrderResult->Success == true) {

                if ($total == 1) {
                    $Api->_addTransaction($payment, $capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                    if ($capture->ManageOrderResult->OrderStatusEnum == 'Paid') {
                        //GERA FATURA
                        if ($order->canInvoice() && !$order->hasInvoices()) {
                            $this->_debug('generateInvoice():createOrderRequest=$order->canInvoice()');
                            $invoice = Mage::getModel('sales/service_order', $payment->getOrder())->prepareInvoice(array());
                            $invoice->register();

                            // Set capture case to online and register the invoice.
                            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                            $invoice->setTransactionId($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey);
                            $invoice->setCanVoidFlag(true);
                            $invoice->getOrder()->setIsInProcess(true);
                            //$invoice->capture();
                            $invoice->save();
                            $payment->setTransactionId($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey);
                        }
                        
                        
                        /*
                        if ($order->canInvoice() && !$order->hasInvoices()) {
                            $invoiceId = Mage::getModel('sales/order_invoice_api')->create($order->getIncrementId(), array());
                            $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);

                            // envia email de confirmacao de fatura
                            $invoice->sendEmail(true);
                            $invoice->setEmailSent(true);
                            $invoice->save();
                        }*/
                        $html = "<b>Pedido capturado com sucesso!</b><br><button type=\"button\" title=\"Atualizar Informações\" onclick=\"document.location.reload(true)\"><span>Recarregar Página</span></button><br /><br />";
                    }else if ($capture->ManageOrderResult->OrderStatusEnum == 'PartialCapture') {
                        //GERA FATURA
                        //$Api->generateInvoice($payment);
                        $html = "<b>Pedido capturado com sucesso!</b> <br><button type=\"button\" title=\"Atualizar Informações\" onclick=\"document.location.reload(true)\"><span>Recarregar Página</span></button><br /><br />";
                    }else {
                        $html = "<b>Pedido capturado com erro!</b> <br> " . $Api->MessageGateway($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->AcquirerReturnCode) . "<br /><br />";
                    }
                } else {
                    //MAIS DE UM RESULTADO
                    foreach ($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult as $key => $trans) {
                        //ULTIMO REGISTRO
                        if ($totalKey == $key) {
                            $Api->_addTransaction($payment, $trans->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $trans);
                            if ($capture->ManageOrderResult->OrderStatusEnum == 'Paid') {
                                //GERA FATURA
                                if ($order->canInvoice() && !$order->hasInvoices()) {
                                    $this->_debug('generateInvoice():createOrderRequest=$order->canInvoice()');
                                    $invoice = Mage::getModel('sales/service_order', $payment->getOrder())->prepareInvoice(array());
                                    $invoice->register();

                                    // Set capture case to online and register the invoice.
                                    $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
                                    $invoice->setTransactionId($trans->TransactionKey);
                                    $invoice->setCanVoidFlag(true);
                                    $invoice->getOrder()->setIsInProcess(true);
                                    //$invoice->capture();
                                    $invoice->save();
                                    $payment->setTransactionId($trans->TransactionKey);
                                }
                                $html = "<b>Pedido capturado com sucesso!</b> <br><button type=\"button\" title=\"Atualizar Informações\" onclick=\"document.location.reload(true)\"><span>Recarregar Página</span></button><br /><br />";
                            } else {
                                $html = "<b>Pedido capturado com erro!</b> <br> " . $Api->MessageGateway($trans->AcquirerReturnCode) . "<br /><br />";
                            }
                        }
                    }
                }
            } else {
                $html = "<b>Pedido com erro!</b> <br> " . $Api->MessageGateway($capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->AcquirerReturnCode) . "<br /><br />";
            }
        } else {
                $html = Mage::helper('mundipagg')->__('No OrderKey found.');
            //Mage::throwException(Mage::helper('mundipagg')->__('No OrderKey found.'));
        }
        echo $html;
        //$xml = new SimpleXMLElement($capture->ManageOrderResult);
        //$this->getResponse()->setBody($html . Mage::helper('mundipagg')->xmlToHtml($xml));
    }
    /**
     * 
     * Funcao responsavel por enviar o pedido de cancelamento para o WebService da Cielo
     * 
     */
    public function cancelAction() {
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
        $MerchantKey = Mage::getStoreConfig('payment/mundipagg_cc/merchant_id');
        $environment = Mage::getStoreConfig('payment/mundipagg_cc/environment');

        $model = Mage::getModel('mundipagg/webServiceOrder', array('environment' => $environment));

        $model->OrderKey = $this->getRequest()->getParam('OrderKey');
        $model->MerchantKey = $MerchantKey;

        // requisita cancelamento
        $model->requestCancellation();
        $xml = $model->getXmlResponse();
        $status = (string) $xml->status;

        // tudo ok, transacao cancelada
        if ($status == 9) {
            $html = "<b>Pedido cancelado com sucesso!</b> &nbsp; &nbsp; 
					<button type=\"button\" title=\"Atualizar Informações\" onclick=\"document.location.reload(true)\">
						<span>Recarregar Página</span>
					</button><br /><br />";

            // atualiza os dados da compra
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('AcquirerReturnCode', $status);
            $payment->save();
        } else {
            $html = "";
        }

        $this->getResponse()->setBody($html . Mage::helper('mundipagg')->xmlToHtml($xml));
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

