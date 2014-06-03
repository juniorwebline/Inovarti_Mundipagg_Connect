<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Api extends Mage_Payment_Model_Method_Abstract {

    protected $_service = NULL;
    protected $merchantKey = null;
    protected $homologacao = null;
    protected $clearsale = null;
    protected $retries = null;
    protected $valorTotal = null;
    protected $maxParcelas = null;

    public function getPayment() {
        return $this->getQuote()->getPayment();
    }

    public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function canAuthorize() {
        return $this->_canAuthorize;
    }

    public function canCapture() {
        return $this->_canCapture;
    }

    public function void(Varien_Object $payment) {
        if (!$this->canVoid($payment)) {
            Mage::throwException(Mage::helper('payment')->__('Void action is not available.'));
        }

        //Prepare data in order to void
        if ($payment->getOrderKey()) {

            $parametros = array(
                'manageOrderRequest' => array(
                    'MerchantKey' => $this->getmerchantKey(),
                    'OrderKey' => $payment->getOrderKey(),
                    'ManageOrderOperationEnum' => 'Void',
                    'CreditCardTransactionCollection' => array(
                        'ManageCreditCardTransactionRequest' => array(
                            'TransactionKey' => $payment->getTxnId(),
                        )
                    )
                )
            );
            $this->_debug('void():$manageOrderRequest=' . print_r($parametros, 1));
            $void = $this->getService()->ManageOrder($parametros);
            $this->_debug('void():$resultado=' . print_r($void, 1));

            $resultado = (isset($void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult)) ?
                    $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult :
                    0;

            //if ($payment->getOrderReference() == '18471') {
            //    $this->_addTransaction($payment, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
            //   return $this;
            //}
			
            if ($void->ManageOrderResult->OrderStatusEnum == 'Canceled') {
                $this->_addTransaction($payment, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                return $this;
            }
            if ($void->ManageOrderResult->OrderStatusEnum == 'Void') {
                $this->_addTransaction($payment, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                return $this;
            }
            if ($void->ManageOrderResult->OrderStatusEnum == 'Void') {
                $this->_addTransaction($payment, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                return $this;
            }
            if (isset($resultado->Success) && $resultado->Success == true) {
                $this->_addTransaction($payment, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_VOID, $void->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                return $this;
            } else {
                $error = Mage::helper('mundipagg')->__('Order status is: ' . $void->ManageOrderResult->OrderStatusEnum);
                Mage::throwException($error);
            }
        } else {
            Mage::throwException(Mage::helper('mundipagg')->__('No OrderKey found.'));
        }
    }

    public function refund(Varien_Object $payment, $amount) {
        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }

        //Prepare data in order to refund
        if ($payment->getOrderKey()) {

            $parametros = array(
                'manageOrderRequest' => array(
                    'MerchantKey' => $this->getmerchantKey(),
                    'OrderKey' => $payment->getOrderKey(),
                    'ManageOrderOperationEnum' => 'Void'
                )
            );
            $refund = $this->getService()->ManageOrder($parametros);
            $this->_debug('refund():$resultado=' . print_r($refund, 1));

            $resultado = (isset($refund->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult)) ?
                    $refund->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult :
                    0;

            if (isset($resultado->Success) && $resultado->Success == true) {
                $this->_addTransaction($payment, $refund->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult->TransactionKey, Mage_Sales_Model_Order_Payment_Transaction::TYPE_REFUND, $refund->ManageOrderResult->CreditCardTransactionResultCollection->CreditCardTransactionResult);
                return $this;
            } else {
                $error = Mage::helper('mundipagg')->__('Order status is: ' . $refund->ManageOrderResult->OrderStatusEnum);

                Mage::throwException($error);
            }
        } else {
            Mage::throwException(Mage::helper('mundipagg')->__('No OrderKey found.'));
        }
    }

    public function canVoid(Varien_Object $payment) {
        if ($payment instanceof Mage_Sales_Model_Order_Creditmemo) {
            return false;
        }

        return $this->_canVoid;
    }

    public function cancel(Varien_Object $payment) {
        return $this->void($payment);
    }

    public function canRefund() {
        return $this->_canRefund;
    }

    public function processBeforeRefund($invoice, $payment) {
        $payment->setRefundTransactionId($invoice->getTransactionId());

        return $this;
    }

    public function getOrderPlaceRedirectUrl() {
        $payment = $this->getInfoInstance();
    }

    public function getClearsale() {
        if (!is_object($this->clearsale)) {
            $this->clearsale = Mage::getStoreConfig('payment/mundipaggsettings/clearsale');
        }
        return $this->clearsale;
    }

    public function getRetries() {
        if (!is_object($this->retries)) {
            $this->retries = Mage::getStoreConfig('payment/mundipaggsettings/retries');
        }
        return $this->retries;
    }

    public function getHomologacao() {
        if (!is_object($this->homologacao)) {
            $this->homologacao = Mage::getStoreConfig('payment/mundipaggsettings/enable_test_mode');
        }
        return $this->homologacao;
    }

    public function getmerchantKey() {
        if (!is_object($this->merchantKey)) {
            $this->merchantKey = Mage::getStoreConfig('payment/mundipaggsettings/merchant_id');
        }
        return $this->merchantKey;
    }

    public function getParcelamento() {
        $val = intval(Mage::getStoreConfig('payment/mundipaggsettings/parcelamento'));
        return $val ? $val : 6;
    }

    public function getParcelamentoPriceMin() {
        $val = floatval(Mage::getStoreConfig('payment/mundipaggsettings/price_min'));
        return $val ? $val : 15;
    }

    public function getMaxParcela() {
        if (!is_object($this->maxParcelas)) {
            $numero_de_parcelas = $this->getParcelamento();
            $valor_parcelas = $this->getParcelamentoPriceMin();

            $totalsR = Mage::getModel('checkout/cart')->getQuote()->getTotals();
            $subtotal = Mage::getModel('checkout/cart')->getQuote()->getSubtotal();

            $shippingtotal = 0;
            $discountotal = 0;
            if (isset($totalsR['shipping']))
                $shippingtotal = $totalsR['shipping']->getValue();
            if (isset($totalsR['discount']))
                $discountotal = $totalsR['discount']->getValue();

            $valor = $subtotal + $shippingtotal + $discountotal;
            //Mage::log(print_r(($numero_de_parcelas),1), null, 'mundipagg_tem_setDiscount.log', true);

            if ($valor > $valor_parcelas) {
                for ($counter = $numero_de_parcelas; $counter >= 1; $counter--) {
                    if ($valor / $counter >= $valor_parcelas) {
                        $numero_de_parcelas = $counter;
                        break;
                    }
                }
            } else {
                $numero_de_parcelas = 1;
            }
            $this->maxParcelas = $numero_de_parcelas;
        }
        return $this->maxParcelas;
    }

    public function getValorTotal() {
        if (!is_object($this->valorTotal)) {
            $totalsR = Mage::getModel('checkout/cart')->getQuote()->getTotals();
            $subtotal = Mage::getModel('checkout/cart')->getQuote()->getSubtotal();

            $shippingtotal = 0;
            $discountotal = 0;
            if (isset($totalsR['shipping']))
                $shippingtotal = $totalsR['shipping']->getValue();
            if (isset($totalsR['discount']))
                $discountotal = $totalsR['discount']->getValue();

            $valor = $subtotal + $shippingtotal + $discountotal;


            $this->valorTotal = $valor;
        }
        return $this->valorTotal;
    }

    public function getDescricaoCartaoBoleto() {
        return Mage::getStoreConfig('payment/mundipagg_boleto/descricao');
    }

    public function getMethodConfig($cc_type) {
        $config = Mage::getSingleton('payment/config')->getCcTypes();
        return array_key_exists($cc_type, $config) ? $config[$cc_type] : 0;
    }

    public function getService() {
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

    public function getItensProductOrder() {
        $items = Mage::getSingleton("checkout/session")->getQuote()->getAllItems();
        foreach ($items as $item) {
            $produtos[] = array(
                'ItemReference' => $item->getSku(),
                'Name' => $item->getName(),
                'Quantity' => $item->getQty(),
                'UnitCostInCents' => $this->formataValor($item->getPrice()),
                'TotalCostInCents' => $this->formataValor($item->getQty() * $item->getPrice()),
            );
        };
        return $produtos;
    }

    public function getBuyer($payment) {
        $billing = $payment->getOrder()->getBillingAddress();
        $Buyer = array(
            'PersonTypeEnum' => 'Person',
            'GenderEnum' => $this->Gender($payment->getOrder()->getCustomerGender()),
            'Email' => $payment->getOrder()->getCustomerEmail(),
            'IpAddress' => ($payment->getOrder()->getRemoteIp() != '::1') ? $payment->getOrder()->getRemoteIp() : '127.0.0.1',
            'Name' => $payment->getOrder()->getCustomerFirstname() . ' ' . $payment->getOrder()->getCustomerLastname(),
            'TaxDocumentNumber' => ($payment->getCcCpf() == '') ? $payment->getCcCpf() : $this->limpaCNPJ($payment->getOrder()->getCustomerTaxvat()),
            'TaxDocumentTypeEnum' => 'CPF',
            'HomePhone' => $this->tratatel($billing->getTelephone()),
            'WorkPhone' => $this->tratatel($billing->getCelular()),
            'BuyerAddressCollection' => array(
                'BuyerAddress' => array(
                    'City' => $billing->getCity(),
                    'Complement' => $billing->getStreet4(),
                    'CountryEnum' => 'Brazil',
                    'District' => $billing->getStreet3(),
                    'Number' => $billing->getStreet2(),
                    'State' => ( strlen($billing->getRegion()) > 2 ) ? $this->converteuf($billing->getRegion()) : $billing->getRegion(),
                    'Street' => $billing->getStreet1(),
                    'ZipCode' => $billing->getPostcode(),
                    'AddressTypeEnum' => 'Billing',
                )
            )
        );
        return $Buyer;
    }

    public function _addTransaction(Mage_Sales_Model_Order_Payment $payment, $transactionId, $transactionType, $transactionAdditionalInfo) {
        $transaction = Mage::getModel('sales/order_payment_transaction');
        $transaction->setOrderPaymentObject($payment);

        $transaction = $transaction->loadByTxnId($transactionId);

        $transaction->setOrderPaymentObject($payment);
        $transaction->setTxnType($transactionType);
        $transaction->setTxnId($transactionId);

        if ($transactionAdditionalInfo->Success == true && $transactionType == 'authorization') {
            $transaction->setIsClosed(0);
        } else {
            $transaction->setIsClosed(1);
        }
        //$this->_debug('_addTransaction=' . print_r($payment->getIsTransactionPending(), 1));
        foreach ($transactionAdditionalInfo as $transKey => $value) {
            $transaction->setAdditionalInformation($transKey, $value);
        }
        return $transaction->save();
    }
    public function formataValor($amount) {
        $amountInt = (int) $amount;
        if ($amountInt == $amount) {
            $amount = (int) $amount;
            $amountStr = $amount;
            $amountStr = str_ireplace(",", "", $amountStr);
            $amountStr = str_ireplace(".", "", $amountStr);
            $amountStr = $amountStr . '00';
        } else {
            $amount = round($amount, 2);
            $amountStr = $amount;

            $pos = strpos($amountStr, '.');
            $decimais = substr($amountStr, $pos + 1);
            $tamDecimais = strlen($decimais);

            while ($tamDecimais < 2) {
                $amountStr = $amountStr . '0';

                $pos = strpos($amountStr, '.');
                $decimais = substr($amountStr, $pos + 1);
                $tamDecimais = strlen($decimais);
            }

            $amountStr = str_ireplace(",", "", $amountStr);
            $amountStr = str_ireplace(".", "", $amountStr);
        }

        return $amountStr;
    }
/*
    public function getInstallments() {
        // pega dados de parcelamento
        $maxInstallments = $this->getParcelamento();
        $minInstallmentValue = $this->getParcelamentoPriceMin();

        $totalsR = Mage::getModel('checkout/cart')->getQuote()->getTotals();
        $subtotal = Mage::getModel('checkout/cart')->getQuote()->getSubtotal();
        $discountotal = 0;
        $discountAmount = 0;
        $totalD = 0;

        //print_r($totals);
        //$subtotal = $totals['subtotal']->getValue();
        $shippingtotal = 0;
        if (isset($totalsR['shipping']))
            $shippingtotal = $totalsR['shipping']->getValue();
        if (isset($totalsR['discount']))
            $discountotal = $totalsR['discount']->getValue();
        if (isset($totalsR['grand_total']))
            $totalD = $totalsR['grand_total']->getValue();

        $total = $subtotal + $shippingtotal + $discountotal;
        $this->valorTotal = $total;


        $installments = array();
        // caso o valor da parcela minima seja maior do que o valor da compra,
        // deixa somente opcao a vista
        if ($minInstallmentValue > $total) {
                    $label = "à vista (" . Mage::helper('core')->currency(($minInstallmentValue), true, false) . " )";
            $installments[] = array("num" => 1, "label" => $label);
        }else{
            for ($i = 1; $i <= $maxInstallments; $i++) {
                $installmentValue = round($subtotal / $i, 2);
                // confere se a parcela nao esta abaixo do minimo
                // monta o texto da parcela
                if ($i == 1) {
                    $ordertotal = $total - $discountAmount;
                    $label = "a vista (" . Mage::helper('core')->currency(($total), true, false) . " )";
                } else {
                    $label = $i . "x sem juros (" . Mage::helper('core')->currency(($installmentValue), true, false) . " cada)";
                }

                // adiciona no vetor de parcelas
                $installments[] = array("num" => $i, "label" => $label);
                if ($installmentValue < $minInstallmentValue) {
                    break;
                }
            }    
        }
        $this->maxParcelas = $installments[0]['num'];
        return $installments;
    }
*/
    public function getInstallments() {
        // pega dados de parcelamento
        $maxInstallments = $this->getParcelamento();
        $minInstallmentValue = $this->getParcelamentoPriceMin();

        $totalsR = Mage::getModel('checkout/cart')->getQuote()->getTotals();
        $subtotal = Mage::getModel('checkout/cart')->getQuote()->getSubtotal();
        $discountotal = 0;

        $shippingtotal = 0;
        if (isset($totalsR['shipping']))
            $shippingtotal = $totalsR['shipping']->getValue();
        if (isset($totalsR['discount']))
            $discountotal = $totalsR['discount']->getValue();

        $total = $subtotal + $shippingtotal + $discountotal;

        $installments = array();
        for ($i = 1; $i <= $maxInstallments; $i++) {
            $installmentValue = round($total / $i, 2);
            // confere se a parcela nao estah abaixo do minimo
            // monta o texto da parcela
            if ($i == 1) {
                $ordertotal = $total;
                $label = "&#192; vista (" . Mage::helper('core')->currency(($ordertotal), true, false) . ")";
            } else {
                $label = $i . "x sem juros (" . Mage::helper('core')->currency(($installmentValue), true, false) . " cada)";
            }
            // adiciona no vetor de parcelas
            $installments[] = array("num" => $i, "label" => $label);
            if ($installmentValue < $minInstallmentValue) {
                break;
            }
        }
        // caso o valor da parcela minima seja maior do que o valor da compra,
        // deixa somente opcao a vista
        if ($minInstallmentValue > $total) {
            $label = "&#192; vista (" . Mage::helper('core')->currency(($total), true, false) . ")";
            $installments[] = array("num" => 1, "label" => $label);
        }

        return $installments;
    }

    public function tratatel($tel) {
        /*
          De: (11)2222?-3333
          Para: +x55(x21)9999999999
         */

        // Se existir $tel retorna o valor, senão, retorna default
        $tel = (!empty($tel) ) ? $tel : '(21)9003-9003';

        $remover = array(" ", "-");
        $semtraco = str_replace($remover, '', $tel);
        //$formatado = str_replace('(', '+x55(x', $semtraco);
        $formatado = str_replace('(', '+55(', $semtraco);
        return $formatado;
    }

    public function retiraacentos($palavra) {
        $array1 = array("á", "à", "â", "ã", "ä", "é", "è", "ê", "ë", "í", "ì", "î", "ï", "ó", "ò", "ô", "õ", "ö", "ú", "ù", "û", "ü", "ç"
            , "Á", "À", "Â", "Ã", "Ä", "É", "È", "Ê", "Ë", "Í", "Ì", "Î", "Ï", "Ó", "Ò", "Ô", "Õ", "Ö", "Ú", "Ù", "Û", "Ü", "Ç");
        $array2 = array("a", "a", "a", "a", "a", "e", "e", "e", "e", "i", "i", "i", "i", "o", "o", "o", "o", "o", "u", "u", "u", "u", "c"
            , "A", "A", "A", "A", "A", "E", "E", "E", "E", "I", "I", "I", "I", "O", "O", "O", "O", "O", "U", "U", "U", "U", "C");
        return str_replace($array1, $array2, $palavra);
    }

    public function converteuf($estado) {

        // Se existir $estado retorna o valor, senão, retorna default
        $estado = (!empty($estado) ) ? $estado : 'São Paulo';

        $estado = $this->retiraacentos($estado);

        //remover espaços duplos
        $estado = preg_replace('/\s(?=\s)/', '', $estado);
        $estado = preg_replace('/[\n\r\t]/', ' ', $estado);

        $ufs = array(
            'AC' => 'acre',
            'AL' => 'alagoas',
            'AM' => 'amazonas',
            'AP' => 'amapa',
            'BA' => 'bahia',
            'CE' => 'ceara',
            'DF' => 'distrito federal',
            'ES' => 'espirito santo',
            'GO' => 'goiais',
            'MA' => 'maranhao',
            'MG' => 'minas gerais',
            'MS' => 'mato grosso do sul',
            'MT' => 'mato grosso',
            'PA' => 'para',
            'PB' => 'paraiba',
            'PE' => 'pernambuco',
            'PI' => 'piaui',
            'PR' => 'parana',
            'RJ' => 'rio de janeiro',
            'RN' => 'rio grande do norte',
            'RO' => 'rondonia',
            'RR' => 'roraima',
            'RS' => 'rio grande do sul',
            'SC' => 'santa catarina',
            'SE' => 'sergipe',
            'SP' => 'sao paulo',
            'TO' => 'tocantins'
        );
        return array_search(strtolower($estado), $ufs);
        ;
    }

    /**
     * Limpa CPF/CNPJ
     */
    public function limpaCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        return $cnpj;
    }
    public function getVerificationRegEx() {
        $verificationExpList = array(
            'VI' => '/^[0-9]{3}$/', // Visa
            'MC' => '/^[0-9]{3}$/', // Master Card
            'AE' => '/^[0-9]{4}$/', // American Express
            'ELO' => '/^[0-9]{3,4}$/',
            'SM' => '/^[0-9]{3,4}$/', // Switch or Maestro
        );
        return $verificationExpList;
    }

    public function OtherCcType($type) {
        return $type == 'ELO';
    }

    public function Gender($gender) {
        switch ($gender) {
            case "1":
                return "M";
                break;
            default:
                return "F";
                break;
        }
    }

    public function MessageGateway($cod) {
        /*
          LR    Mensagem Retornada

          00    Transação autorizada.
          01    Transação negada. Aguardar contato do emissor.
          02    Transação negada. Contatar emissor
          03    Transação negada. Estabelecimento inválido.
          04    Transação negada - Contatar emissor (Problemas com cartão)
          05    Não Autorizada pelo emissor
          06    Problemas ocorridos na transação eletrônica.
          07    Transação negada - Contatar emissor (Problemas com cartão)
          08    Cód de Seg Invalido
          11    Transação autorizada.
          12    Transação inválida.
          13    Valor inválido / Parcelado Loja não atingiu valor minimo por parcela - 5,00R$
          14    Cartão inválido
          15    Emissor sem comunicação.
          19    Refaça a transação
          21    Transação não localizada.
          22    Parcelamento inválido
          25    Número do cartão não foi enviado.
          28    Arquivo indisponível.
          41    Transação negada - Contatar emissor (Problemas com cartão)
          43    Transação negada - Contatar emissor (Problemas com cartão)
          51    Não Autorizada pelo Emissor
          52    Cartão com dígito de controle inválido.
          53    Cartão inválido para essa operação.
          54    Cartão Vencido
          55    Senha Inválida
          57    Transação não permitida para o cartão.
          61    Transação negada - Possivel problema com sistema do banco.
          62    Transação negada - Cartão não permitido para transação online.
          63    Transação negada - Possivel erro de segurança ao tentar processar.
          65    Transação negada.
          75    Senha Bloqueada
          76    Problemas com número de referência da transação.
          77    Dados não conferem com mensagem original.
          78    Cartão Bloqueado 1º USO
          80    Data inválida.
          81    Erro de criptografia.
          82    Código de Segurança Incorreto ou Inválido
          83    Erro no sistema de senhas.
          85    Erro métodos de criptografia.
          86    Refaça a transação.
          91    Emissor sem comunicação..
          93    Transação negada - Violação de regra bancária
          94    Transação negada - Violação de regra bancária
          96    Venda abaixo de  R$ 1,00
          98    Emissor sem comunicação.
          99    Possivel erro de sistema - Contatar suporte.
          400   Código de meio de pagamento inválido: '2'
         */
        /*
          erro operadora
          400 - Dado inválido informado para o contrato do serviço. ou O campo do documento do cliente deve ter 11 dígitos para CPF ou 14 dígitos para CNPJ
          500 - Erro inesperado.
          504 - Ocorreu um erro na comunicação com o adquirente para a transação:
         */
        /*
          capture
          3597 = PartialCapture
         */
        switch ($cod) {
            case "51"://ESTABELECIMENTO INVÁLIDO. POR FAVOR, ENTRE EM CONTATO COM O ESTABELECIMENTO QUE ESTÁ EFETUANDO A VENDA.
                //return "Estabelecimento inválido. por favor, entre em contato com o estabelecimento que está efetuando a venda.";
                return "Problemas com o cartão. por favor, verifique os dados de seu cartão. caso o erro persista, entre em contato com a central de atendimento de seu cartão.";
                break;
            case "74"://REFAÇA A TRANSAÇÃO. SUA TRANSAÇÃO NÃO PODE SER CONCLUIDA. POR FAVOR, TENTE NOVAMENTE
                return "Refaça a transação. sua transação não pode ser concluida. por favor, tente novamente";
                break;
            case "58":////58 sem saldo  PROBLEMAS COM O CARTÃO. POR FAVOR, VERIFIQUE OS DADOS DE SEU CARTÃO. CASO O ERRO PERSISTA, ENTRE EM CONTATO COM A CENTRAL DE ATENDIMENTO DE SEU CARTÃO.
                return "Problemas com o cartão. por favor, verifique os dados de seu cartão. caso o erro persista, entre em contato com a central de atendimento de seu cartão.";
                break;
            case "62"://PROBLEMAS COM O CARTÃO RESTRIÇÃO
                return "Problemas restriçào com o cartão. por favor, verifique os dados de seu cartão. caso o erro persista, entre em contato com a central de atendimento de seu cartão e informe Cod:(" . $cod . ")";
                break;
            case "57"://CARTÃO DE DEMO
                return "Problemas com o cartão. por favor, verifique os dados de seu cartão.";
                break;
            case "05"://DADOS INVALIDOS
                return "Problemas com o cartão. por favor, verifique os dados de seu cartão. Exe: veja se o codigo de segurança e outros dados estam corretos.";
                break;
            case "400"://DADOS INVALIDOS
                return "Problemas com a compra cod:(" . $cod . ") Caso o erro persista, entre em contato com a central de atendimento.";
                break;
            case "500"://DADOS INVALIDOS
                return "Problemas com a compra cod:(" . $cod . ") Caso o erro persista, entre em contato com a central de atendimento.";
                break;
            case "504"://DADOS INVALIDOS
                return "Problemas com a compra cod:(" . $cod . ") Caso o erro persista, entre em contato com a central de atendimento.";
                break;
            case "3597"://DADOS INVALIDOS
                return "Status parcilamente captura, tente novamente mais tarde!.";
                break;
            case ($cod == "")://DADOS INVALIDOS
                return "Problemas com a compra! Caso o erro persista, entre em contato com a central de atendimento.";
                break;
            default:
                return "Problemas com a compra cod:(" . $cod . ") Caso o erro persista, entre em contato com a central de atendimento.";
                break;
        }
    }

    public function generateInvoice(Varien_Object $payment, $amount) {
        $order = $payment->getOrder();

        if (!$order->canInvoice()) {
            $order->addStatusHistoryComment('Cannot create an invoice.', false);
            $order->save();
            Mage::logException(Mage::helper('core')->__('Cannot create an invoice.'));
            Mage::throwException(Mage::helper('core')->__('Cannot create an invoice.'));
        }
        if ($order->canInvoice()) {
            $invoice = Mage::getModel('sales/service_order', $payment->getOrder())->prepareInvoice(array());
            $invoice->register();

            // Set capture case to online and register the invoice.
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
            $invoice->setTransactionId($this->transaction_id);
            $invoice->setCanVoidFlag(true);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->capture();
            $invoice->save();
            $order->addStatusHistoryComment('Captured online amount of R$' . $amount * 100, false);

            $payment->setTransactionId($this->transaction_id);
        }
        return $this;
    }

    public function processInvoice($invoice, $payment) {
        if ($payment->getLastTransId()) {
            $invoice->setTransactionId($payment->getLastTransId());
            $invoice->setCanVoidFlag(true);

            if (Mage::helper('sales')->canSendNewInvoiceEmail($payment->getOrder()->getStoreId())) {
                $invoice->setEmailSent(false);
                $invoice->sendEmail(false);
            }

            return $this;
        }

        return false;
    }

    public function validateCcNum($ccNumber) {
        $cardNumber = strrev($ccNumber);
        $numSum = 0;

        for ($i = 0; $i < strlen($cardNumber); $i++) {
            $currentNum = substr($cardNumber, $i, 1);

            /**
             * Double every second digit
             */
            if ($i % 2 == 1) {
                $currentNum *= 2;
            }

            /**
             * Add digits of 2-digit numbers together
             */
            if ($currentNum > 9) {
                $firstNum = $currentNum % 10;
                $secondNum = ($currentNum - $firstNum) / 10;
                $currentNum = $firstNum + $secondNum;
            }

            $numSum += $currentNum;
        }

        /**
         * If the total has no remainder it's OK
         */
        return ($numSum % 10 == 0);
    }

    public function _validateExpDate($expYear, $expMonth) {
        $date = Mage::app()->getLocale()->date();
        if (!$expYear || !$expMonth || ($date->compareYear($expYear) == 1) || ($date->compareYear($expYear) == 0 && ($date->compareMonth($expMonth) == 1))
        ) {
            return false;
        }
        return true;
    }

    public function _debug($debugData) {
        if (Mage::getStoreConfig('payment/mundipaggsettings/debug')) {
            Mage::log($debugData, null, 'mundipagg_' . $this->getCode() . '.log', true);
        }
    }

    public function setDiscount($info) {
        $quote = $info->getQuote();
        $quoteid = $quote->getId();
        if ($quoteid) {
            $discountAmount = $this->getDescontoParcelamento();
            $minInstallmentValue = $this->getParcelamentoPriceMin();
            $valorTotal = $this->getValorTotal();
            $discountAmountDescription = "Desconto " . $discountAmount . "%";
            $Amount = 0;

            if ($discountAmount > 0 
                    && ($quote->getPayment()->getCcParcelamento() == 1 && $quote->getPayment()->getMethod() == 'mundipagg') 
                    && ($valorTotal > $minInstallmentValue)
                ) {
                
                $total = $quote->getBaseSubtotal();
                $Amount = $total * $discountAmount / 100;

                $canAddItems = $quote->isVirtual() ? ('billing') : ('shipping');
                foreach ($quote->getAllAddresses() as $address) {

                    $address->setSubtotalWithDiscoun(0);
                    $address->setBaseSubtotalWithDiscount(0);
                    $address->setGrandTotal(0);
                    $address->setBaseGrandTotal(0);

                    $address->collectTotals();


                    if ($address->getAddressType() == $canAddItems) {
                        $address->setSubtotalWithDiscount((float) $address->getSubtotalWithDiscount() + $Amount);
                        $address->setBaseSubtotalWithDiscount((float) $address->getBaseSubtotalWithDiscount() + $Amount);
                        $address->setGrandTotal((float) $address->getGrandTotal() - $Amount);
                        $address->setBaseGrandTotal((float) $address->getBaseGrandTotal() - $Amount);
                        if ($address->getDiscountDescription()) {
                            $address->setDiscountAmount($address->getDiscountAmount() - $Amount);
                            $address->setBaseDiscountAmount($address->getBaseDiscountAmount() - $Amount);
                            $address->setDiscountDescription($address->getDiscountDescription() . ',' . $discountAmountDescription);
                        } else {
                            $address->setDiscountAmount($address->getDiscountAmount() - $Amount);
                            $address->setDiscountDescription($discountAmountDescription);
                            $address->setBaseDiscountAmount($address->getBaseDiscountAmount() - $Amount);
                        }
                        $address->save();
                    }
                }
            } else {
                $quote->setSubtotalWithDiscount($quote->getSubtotalWithDiscount() + $Amount)
                        ->setBaseSubtotalWithDiscount($quote->getBaseSubtotalWithDiscount() + $Amount)
                        ->save();
                $canAddItems = $quote->isVirtual() ? ('billing') : ('shipping');
                foreach ($quote->getAllAddresses() as $address) {

                    if ($address->getAddressType() == $canAddItems) {
                        $address->setSubtotalWithDiscount((float) $address->getSubtotalWithDiscount() + $Amount);
                        $address->setBaseSubtotalWithDiscount((float) $address->getBaseSubtotalWithDiscount() + $Amount);
                        $address->setGrandTotal((float) $address->getGrandTotal() + $Amount);
                        $address->setBaseGrandTotal((float) $address->getBaseGrandTotal() + $Amount);

                        $address->setDiscountAmount($address->getDiscountAmount() + $Amount)
                                ->setBaseDiscountAmount($address->getBaseDiscountAmount() + $Amount)
                                ->save();
                    }
                }
            }
        }
    }

}
