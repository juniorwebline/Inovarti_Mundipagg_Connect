<?php
/**
 *
 * @category   Inovarti
 * @package    Inovarti_Mundipagg
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Mundipagg_Block_Adminhtml_Sales_Transactions_Detail_Grid extends Mage_Adminhtml_Block_Sales_Transactions_Detail_Grid
{
	/**
     * Retrieve Transaction addtitional info
     *
     * FIX: Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS inside getAdditionalInformation()
     * was causing no Transaction Details display
     *
     * @return array
     */
    public function getTransactionAdditionalInfo()
    {
    	$info = Mage::registry('current_transaction')->getAdditionalInformation();
        return (is_array($info)) ? $info : array();
    }
}