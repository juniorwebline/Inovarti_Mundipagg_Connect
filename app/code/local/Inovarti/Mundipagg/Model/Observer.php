<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Observer {

    public function PedidoErrorOrderMundipagg() {
        $orderCollection = Mage::getResourceModel('sales/order_collection');
        $orderCollection->join(array('p' => 'sales/order_payment'), 'main_table.entity_id=p.parent_id')
                ->addFieldToFilter('p.method', 'mundipagg');
        $orderCollection
                ->addFieldToFilter('status', 'pending_payment')
                ->addFieldToFilter('created_at', array(
                    'lt' => new Zend_Db_Expr("DATE_ADD('" . now() . "', INTERVAL -'90:00' HOUR_MINUTE)")))
                ->getSelect()
                ->order('entity_id')
                ->limit(10)
        ;
        //$this->_debug('PedidoErrorOrderMundipagg():sql=' . print_r($orderCollection->getSelect()->__toString(), 1));
        /*
          foreach ($orderCollection->getItems() as $order) {
          $orderPaymentModel = Mage::getModel('sales/order_payment');
          $orderPaymentModel->load($order['entity_id']);
          if (!$orderPaymentModel->canCancel())continue;
          $orderPaymentModel->cancel();
          $orderPaymentModel->setStatus('canceled_pendings');
          $orderPaymentModel->save();
          }
         */
        /*
          foreach ($orderCollection->getItems() as $order) {
          $orderModel = Mage::getModel('sales/order');
          $orderModel->load($order['entity_id']);
          if (!$orderModel->canCancel())continue;
          $orderModel->cancel();
          $orderModel->setStatus('canceled');
          $orderModel->save();
          } */
    }

    public function addOrderButton(Varien_Event_Observer $observer) {
        $block = $observer->getEvent()->getBlock();
        if (!$block) {
            return $this;
        }

        if ($block->getType() == 'adminhtml/sales_order_view') {

            $payment = $block->getOrder()->getPayment();
            $method = $payment->getMethodInstance()->getCode();
            $OrderKey = $payment->getOrderKey();

            if (!empty($OrderKey) && in_array($method, array('mundipagg',  'mundipagg_boleto'))) {
                $block->addButton('inovarti_mundipagg_consult', array(
                    'label' => Mage::helper('mundipagg')->__('Consult WebService'),
                    'onclick' => "loadMundipaggWebServiceData('" . $OrderKey . "', " . $block->getOrder()->getId() . ");",
                    'class' => 'go'
                ));
            }
        }
    }

    public function _debug($debugData) {
        if (Mage::getStoreConfig('payment/mundipaggsettings/debug')) {
            Mage::log($debugData, null, 'mundipagg_cron.log', true);
        }
    }
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

}
