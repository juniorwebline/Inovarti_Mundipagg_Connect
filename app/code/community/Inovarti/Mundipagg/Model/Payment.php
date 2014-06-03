<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Payment extends Inovarti_Mundipagg_Model_Api {

    protected $_isGateway = true;
    protected $_isInitializeNeeded = true;
    protected $_canAuthorize = true;
    protected $_canCapture = true;
    protected $_canCapturePartial = true;
    protected $_canVoid = true;
    protected $_canRefund = true;
    protected $_canUseInternal = true;
    protected $_canUseCheckout = true;
    protected $_code = 'mundipagg';
    protected $_formBlockType = 'mundipagg/payment_form';
    protected $_infoBlockType = 'mundipagg/info_payment';
    protected $transaction_id = null;

    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        parent::assignData($data);

        // salva a bandeira e o numero de parcelas
        $info = $this->getInfoInstance();

        $info->setCcType($data->getCcType())
                ->setCcOwner($data->getCcOwner())
                ->setCcLast4(substr($data->getCcNumber(), -4))
                ->setCcNumber($data->getCcNumber())
                ->setCcCid($data->getCcCid())
                ->setCcExpMonth($data->getCcExpMonth())
                ->setCcCpf($data->getCcCpf())
                ->setCcParcelamento($data->getCcParcelamento())
                ->setCcExpYear($data->getCcExpYear());
    }

    public function initialize($paymentAction, $stateObject) {
        $payment = $this->getInfoInstance();
        $order = $payment->getOrder();
        switch ($paymentAction) {
            case Inovarti_Mundipagg_Model_Payment::ACTION_AUTHORIZE:
                $payment->authorize(true, $order->getBaseTotalDue());
                break;
            case Inovarti_Mundipagg_Model_Payment::ACTION_AUTHORIZE_CAPTURE:
                $payment->authorize(true, $order->getBaseTotalDue());
                $this->generateInvoice($payment, $order->getBaseTotalDue());
                break;
            default:
                break;
        }
    }

    public function authorize(Varien_Object $payment, $amount) {
        if (!$this->canAuthorize()) {
            Mage::throwException(Mage::helper('payment')->__('Authorize action is not available.'));
        }

        ini_set('soap.wsdl_cache_enabled', '0');

        //$info = $this->getInfoInstance();

        $order = $payment->getOrder();
        $order_id = $order->getIncrementId();
        $totals = $this->formataValor($order->getGrandTotal());
        $CreditCardOperationEnum = "AuthOnly";

        //CASO TIPO FOR TEF MUDAR OPERACAO PARA autorize e capture
        if ($this->getHomologacao() == "0") {
            $CreditCardOperationEnum = "AuthAndCapture";
        }

        $parametros = array(
            'createOrderRequest' => array(
                'MerchantKey' => $this->getmerchantKey(),
                'OrderReference' => $order_id,
                'AmountInCents' => $totals,
                'AmountInCentsToConsiderPaid' => $totals,
                'EmailUpdateToBuyerEnum' => 'No',
                'CurrencyIsoEnum' => 'BRL',
                'Retries' => $this->getRetries(),
                'ShoppingCartCollection' => array(
                    'ShoppingCart' => array(
                        'FreightCostInCents' => $this->formataValor($order->getShippingAmount()),
                        'ShoppingCartItemCollection' => $this->getItensProductOrder()
                    )
                ),
                'Buyer' => $this->getBuyer($payment),
                'CreditCardTransactionCollection' => array(
                    'CreditCardTransaction' => array(
                        'AmountInCents' => $totals,
                        'CreditCardBrandEnum' => $this->getMethodConfig($payment->getCcType()),
                        'ExpMonth' => $payment->getCcExpMonth(),
                        'ExpYear' => $payment->getCcExpYear(),
                        'HolderName' => $payment->getCcOwner(),
                        'InstallmentCount' => $payment->getCcParcelamento(),
                        'CreditCardNumber' => $payment->getCcNumber(),
                        'PaymentMethodCode' => $this->getHomologacao(),
                        'SecurityCode' => $payment->getCcCid(),
                        'CreditCardOperationEnum' => $CreditCardOperationEnum,
                    )
                )
            )
        );
        $this->_debug('authorize():createOrderRequest=' . print_r($parametros, 1));
        $authorize = $this->getService()->CreateOrder($parametros);
        $this->_debug('authorize():$resultado=' . print_r($authorize, 1));
        $resultUltimo = $authorize->CreateOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult;
        if (is_array($resultUltimo)) {
            $resultUltimo = end($resultUltimo);
        }

        //SUCESSO
        if (isset($authorize->CreateOrderResult->Success) && $authorize->CreateOrderResult->Success == true) {
            //ULTIMO REGISTRO
            if ($resultUltimo->CreditCardTransactionStatusEnum == 'AuthorizedPendingCapture') {
                $info = $this->getInfoInstance();
                $info->setLastTransId($resultUltimo->TransactionKey);
                $info->setOrderKey($authorize->CreateOrderResult->OrderKey);
                $info->setOrderReference($authorize->CreateOrderResult->OrderReference);
                $info->setTransactionStatus($resultUltimo->CreditCardTransactionStatusEnum);

                $this->transaction_id = $resultUltimo->TransactionKey;
                $info->save();

                //GERAR TRANSACAO
                $this->_addTransaction($payment, $resultUltimo->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $resultUltimo);

                //APROVADO
                Mage::getSingleton('core/session')->setApprovalRequestSuccess(true);
                return $this;
            }
            //CASO JA FOI PAGO OU CAPTURADO
            if ($authorize->CreateOrderResult->OrderStatusEnum == 'Paid' || $resultUltimo->CreditCardTransactionStatusEnum == 'Captured') {

                $info = $this->getInfoInstance();
                $info->setLastTransId($resultUltimo->TransactionKey);
                $info->setOrderKey($authorize->CreateOrderResult->OrderKey);
                $info->setOrderReference($authorize->CreateOrderResult->OrderReference);
                $info->setTransactionStatus($resultUltimo->CreditCardTransactionStatusEnum);

                $this->transaction_id = $resultUltimo->TransactionKey;
                $info->save();

                //GERAR TRANSACAO
                $this->_addTransaction($payment, $resultUltimo->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $resultUltimo);

                //PAGAMENTO ID TRANSACAO
                $payment->setIsTransactionPending(false)
                        ->setLastTransId($this->transaction_id);

                //APROVADO
                Mage::getSingleton('core/session')->setApprovalRequestSuccess(true);

                //GERA FATURA
                $this->generateInvoice($payment);

                return $this;
            }
        } else {
            //CASO DEU ERRO DE ALGUMA FORMA
            //ERRO OPERADORA
            if (isset($authorize->CreateOrderResult->ErrorReport->ErrorItemCollection->ErrorItem)) {
                //SKIP ORDER
                $payment->setSkipOrderProcessing(true);
                //MENSAGEM NA TELA
                Mage::throwException($this->MessageGateway($authorize->CreateOrderResult->ErrorReport->ErrorItemCollection->ErrorItem->ErrorCode));
                return $this;
            } else {
                //ERRO CARTAO
                //GRAVA MESMO COM ERRO -EXETO NAO AUTORIZADO
                if ($resultUltimo->CreditCardTransactionStatusEnum == 'NotAuthorized') {
                    $payment->setSkipOrderProcessing(true);
                    Mage::throwException($this->MessageGateway($resultUltimo->AcquirerReturnCode) . '(Cartão ' . $resultUltimo->CreditCardNumber . ')');
                    return $this;
                } else {
                    $info = $this->getInfoInstance();
                    $info->setLastTransId($resultUltimo->TransactionKey);
                    $info->setOrderKey($authorize->CreateOrderResult->OrderKey);
                    $info->setOrderReference($authorize->CreateOrderResult->OrderReference);
                    $info->setTransactionStatus($resultUltimo->CreditCardTransactionStatusEnum);
                    $this->transaction_id = $resultUltimo->TransactionKey;
                    $info->save();

                    //GERAR TRANSACAO
                    $this->_addTransaction($payment, $resultUltimo->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH, $resultUltimo);

                    //NAO APROVADO
                    Mage::getSingleton('core/session')->setApprovalRequestSuccess(false);
                    return $this;
                }
            }
        }
    }

    public function capture(Varien_Object $payment, $amount) {
        if (!$this->canCapture()) {
            Mage::throwException(Mage::helper('payment')->__('Capture action is not available.'));
        }

        /* if ($this->getClearsale() == 1) {
          Mage::throwException(Mage::helper('payment')->__('You cannot capture having ClearSale activated.'));
          } */

        // Already captured
        if ($payment->getTransactionStatus() == 'Captured') {
            return $this;
        }

        //Prepare data in order to capture
        if ($payment->getOrderKey()) {

            ini_set('soap.wsdl_cache_enabled', '0');

            $parametros = array(
                'manageOrderRequest' => array(
                    'MerchantKey' => $this->getmerchantKey(),
                    'OrderKey' => $payment->getOrderKey(),
                    'OrderReference' => $payment->getOrderReference(),
                    'ManageOrderOperationEnum' => 'Capture'
                )
            );

            $this->_debug('capture():manageOrderRequest=' . print_r($parametros, 1));
            $capture = $this->getService()->ManageOrder($parametros);
            $this->_debug('capture():$resultado=' . print_r($capture, 1));
            $resultUltimo = $capture->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult;

            if (isset($capture->ManageOrderResult->Success) && $capture->ManageOrderResult->Success == true) {
                $this->_addTransaction($payment, $resultUltimo->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE, $resultUltimo);
                if ($capture->ManageOrderResult->OrderStatusEnum == 'Paid') {
                    $payment->setIsTransactionPending(false)
                            ->setLastTransId($resultUltimo->TransactionKey);
                } else {
                    //PAGAMENTO ID TRANSACAO
                    $payment->setIsTransactionPending(true)
                            ->setLastTransId($resultUltimo->TransactionKey);
                }
            } else {
                //ERRO NA CAPTURA FECHA O PEDIDO
                //PAGAMENTO ID TRANSACAO
                $payment->setIsTransactionPending(true)
                        ->setLastTransId($resultUltimo->transaction_id);

                $error = Mage::helper('mundipagg')->__('Order status is: ' . $this->MessageGateway($resultUltimo->AcquirerReturnCode));
                Mage::throwException($error);
            }
        } else {
            Mage::throwException(Mage::helper('mundipagg')->__('No OrderKey found.'));
        }
        return parent::capture($payment, $amount);
    }

    public function validate() {

        $info = $this->getInfoInstance();
        
        $errorMsg = false;
        $availableTypes = explode(',', $this->getConfigData('cctypes'));

        $ccNumber = $info->getCcNumber();
        $ccNumber = preg_replace('/[\-\s]+/', '', $ccNumber);
        $info->setCcNumber($ccNumber);

        $ccType = '';

        if (in_array($info->getCcType(), $availableTypes)) {
            if ($this->validateCcNum($ccNumber)
            ) {
                $ccType = 'ELO';
                $ccTypeRegExpList = array(
                    'SO' => '/(^(6334)[5-9](\d{11}$|\d{13,14}$))|(^(6767)(\d{12}$|\d{14,15}$))/',
                    'SM' => '/(^(5[0678])\d{11,18}$)|(^(6[^05])\d{11,18}$)|(^(601)[^1]\d{9,16}$)|(^(6011)\d{9,11}$)'
                    . '|(^(6011)\d{13,16}$)|(^(65)\d{11,13}$)|(^(65)\d{15,18}$)'
                    . '|(^(49030)[2-9](\d{10}$|\d{12,13}$))|(^(49033)[5-9](\d{10}$|\d{12,13}$))'
                    . '|(^(49110)[1-2](\d{10}$|\d{12,13}$))|(^(49117)[4-9](\d{10}$|\d{12,13}$))'
                    . '|(^(49118)[0-2](\d{10}$|\d{12,13}$))|(^(4936)(\d{12}$|\d{14,15}$))/',
                    'VI' => '/^4[0-9]{12}([0-9]{3})?$/',
                    'MC' => '/^5[1-5][0-9]{14}$/',
                    'AE' => '/^3[47][0-9]{13}$/',
                    'DI' => '/^6011[0-9]{12}$/',
                    'HI' => '/^[0-9]{16}$/',
                    'DN' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
                    'JCB' => '/^(3[0-9]{15}|(2131|1800)[0-9]{11})$/'
                );

                foreach ($ccTypeRegExpList as $ccTypeMatch => $ccTypeRegExp) {
                    if (preg_match($ccTypeRegExp, $ccNumber)) {
                        $ccType = $ccTypeMatch;
                        break;
                    }
                }

                if (!$this->OtherCcType($info->getCcType()) && $ccType != $info->getCcType()) {
                    $errorMsg = Mage::helper('mundipagg')->__('Credit card number mismatch with credit card type.');
                }
            } else {
                $errorMsg = Mage::helper('mundipagg')->__('Invalid Credit Card Number');
            }
        } else {
            $errorMsg = Mage::helper('mundipagg')->__('Credit card type is not allowed for this payment method.');
        }

        //validate credit card verification number
        if ($errorMsg === false && $this->hasVerification()) {
            $verifcationRegEx = $this->getVerificationRegEx();
            $regExp = isset($verifcationRegEx[$info->getCcType()]) ? $verifcationRegEx[$info->getCcType()] : '';
            if (!$info->getCcCid() || !$regExp || !preg_match($regExp, $info->getCcCid())) {
                $errorMsg = Mage::helper('mundipagg')->__('Please enter a valid credit card verification number.');
            }
        }

        if ($ccType != 'SS' && !$this->_validateExpDate($info->getCcExpYear(), $info->getCcExpMonth())) {
            $errorMsg = Mage::helper('mundipagg')->__('Incorrect credit card expiration date.');
        }

        if ($errorMsg) {
            Mage::throwException($errorMsg);
        }

        //This must be after all validation conditions
        if ($this->getIsCentinelValidationEnabled()) {
            $this->getCentinelValidator()->validate($this->getCentinelValidationData());
        }

        return $this;
    }

}
