<?php
/**
 * BoxBilling
 *
 * LICENSE
 *
 * This source file is subject to the license that is bundled
 * with this package in the file LICENSE.txt
 * It is also available through the world-wide-web at this URL:
 * http://www.boxbilling.com/LICENSE.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@boxbilling.com so we can send you a copy immediately.
 *
 * @copyright Copyright (c) 2013 PersianBoxBilling (http://www.Persianboxbilling.ir)
 * @license   http://www.boxbilling.com/LICENSE.txt
 * @Programed by : Mohammad Javad Ahmady (PersianBoxbilling Team)
 * @version   $Id$
 */
class Payment_Adapter_Payline extends Payment_AdapterAbstract
{
    public function _send($url,$api,$amount,$redirect){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&amount=$amount&redirect=$redirect");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    public function _get($url,$api,$trans_id,$id_get){
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_POSTFIELDS,"api=$api&id_get=$id_get&trans_id=$trans_id");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    public $go_url;
    public function init()
    {

        if (!$this->getParam('securityCode')) {
        	throw new Payment_Exception('Payment gateway "Payline" is not configured properly. Please update configuration parameter "API Key Code" at "Configuration -> Payments".');
        }
    }

    public static function getConfig()
    {
        return array(
            'supports_one_time_payments'   =>  true,
            'supports_subscriptions'     =>  false,
            'description'     =>  'Clients will be redirected to Payline.ir to make payment.<br />' ,
            'form'  => array(
                 'securityCode' => array('text', array(
                 			'label' => 'API Key Code',
                 			'description' => 'To setup your "API Key Code" login to Payline account. Go to LINK "http://payline.ir/user/gateway-list". Copy "API Key Code" and paste it to this field.',
                 			'validators' => array('notempty'),
                 	),
                 ),
            ),
        );
    }

    /**
     * Return payment gateway type
     * @return string
     */
    public function getType()
    {
        return Payment_AdapterAbstract::TYPE_FORM;
    }

    /**
     * Return payment gateway type
     * @return string
     */
    public function getServiceUrl()
    {
        if($this->testMode) {
            return 'http://payline.ir/';
        }
		return $this->go_url;
    }

    /**
     * Init call to webservice or return form params
     * @param Payment_Invoice $invoice
     */
	public function singlePayment(Payment_Invoice $invoice)
	{
        $url = 'http://payline.ir/payment/gateway-send';
        $api = $this->getParam('securityCode');
        $amount = (int)$invoice->getTotalWithTax();
        $redirect = urlencode($this->getParam('redirect_url'));

        $result = $this->_send($url,$api,$amount,$redirect);
        if($result > 0 && is_numeric($result)){
            $this->go_url = "http://payline.ir/payment/gateway-$result";
        }else{
            throw new Exception('Payline error : '.$result);
        }

        return $data;
	}

    /**
     * Perform recurent payment
     */
	public function recurrentPayment(Payment_Invoice $invoice)
	{
		throw new Payment_Exception('Not implemented yet');
	}

    /**
     * Handle IPN and return response object
     * @return Payment_Transaction
     */
	public function getTransaction($data, Payment_Invoice $invoice)
	{
        $ipn = $data['post'];

        $Pay_Status             = 'FAIL';

        $api = $this->getParam('securityCode');
        $trans_id = $ipn['trans_id'];
        $id_get = $ipn['id_get'];
        $url = 'http://payline.ir/payment/gateway-result-second';


		$result = $this->_get($url,$api,$trans_id,$id_get);
        $VerifyAnswer = $result->return;
        error_log('Verify answer:' . $VerifyAnswer);

        if($result == 1){
            $Pay_Status = 'OK';
        }

        if ($Pay_Status != 'OK' ){
            throw new Payment_Exception('Sale verification failed: '.$VerifyAnswer);
        }

        $response = new Payment_Transaction();
        $response->setType(Payment_Transaction::TXTYPE_PAYMENT);
        $response->setId($trans_id);
        $response->setAmount($invoice->getTotalWithTax());
        $response->setCurrency($invoice->getCurrency());
        $response->setStatus(Payment_Transaction::STATUS_COMPLETE);
        return $response;
	}
    /**
     * Check if Ipn is valid
     */
    public function isIpnValid($data, Payment_Invoice $invoice)
    {
        $ipn = $data['post'];
        return true;
    }
}