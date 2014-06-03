<?php

/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Model_Payment_PaymentAction
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => Inovarti_Mundipagg_Model_Payment::ACTION_AUTHORIZE,
                'label' => Mage::helper('mundipagg')->__('Authorize Only')
            ),
            array(
                'value' => Inovarti_Mundipagg_Model_Payment::ACTION_AUTHORIZE_CAPTURE,
                'label' => Mage::helper('mundipagg')->__('Authorize and Capture')
            ),
        );
    }
}
