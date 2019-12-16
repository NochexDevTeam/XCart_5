<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\Nochex\Nochex\Model\Payment\Processor;

use XLite\Model\Payment\BackendTransaction;
use XLite\Model\Payment\Transaction;
use XLite\Core\Session;

class Nochex extends \XLite\Model\Payment\Base\WebBased
{
    protected function getFormURL()
    {
        return 'https://secure.nochex.com/default.aspx';
    }

    protected function getFormFields()
    {
		$merchantID = \XLite\Core\Config::getInstance()->Nochex->Nochex->merchantID;
		$testMode = \XLite\Core\Config::getInstance()->Nochex->Nochex->loginMode;
		$hideMode = \XLite\Core\Config::getInstance()->Nochex->Nochex->hide;
		$prodCollMode = \XLite\Core\Config::getInstance()->Nochex->Nochex->prodColl;
		
		if ($testMode == "test") {
			$testTrans = "100";
		}else {
			$testTrans = "";
		}
		
		if ($hideMode == "yes") {
			$testTrans = "100";
		}else {
			$testTrans = "";
		}
		
		$description = "";
		$xmlCollection = "<items>";
		foreach ($this->getOrder()->getItems() as $item) {
           
            $product = $item->getProduct();
			
			$description = $product->getName() . " (" . $item->getPrice(). " x " . $item->getAmount(). ")";
			
		$xmlCollection .= "<item><id></id><name>". $product->getName() ."</name><description>".$product->getName()."</description><quantity>" . $item->getAmount(). "</quantity><price>".$item->getPrice()."</price></item>";
		
        }
		
		$xmlCollection .= "<items>";
		
		if ($prodCollMode=="yes"){
			$description = "Order - ".$this->getTransactionId();
		}else{
			$xmlCollection = "";			
		}
		
        $fields = [
			'merchant_id'   => $merchantID,
			'amount'        => round($this->transaction->getValue(), 2),
			'billing_fullname' => $this->getProfile()->getBillingAddress()->getFirstname() . ", " . $this->getProfile()->getBillingAddress()->getLastname(),
            'customer_phone_number'  => $this->getProfile()->getBillingAddress()->getPhone(),
            'email_address'      => $this->getProfile()->getLogin(),
            'billing_address'    => $this->getProfile()->getBillingAddress()->getStreet(),
            'billing_city'       => $this->getProfile()->getBillingAddress()->getCity(),
            'billing_postcode'   => $this->getProfile()->getBillingAddress()->getZipcode(),
            'billing_country'    => $this->getProfile()->getBillingAddress()->getCountry()->getCountry(),
            'order_id' =>  $this->getTransactionId(),
            'cancel_url' => $this->getReturnURL(null),
            'success_url' => $this->getReturnURL(null, true),
            'callback_url' => $this->getCallbackURL(null, true),
            'optional_1' => "Callback",
			'test_transaction'  => $testTrans,
            'test_success_url' => $this->getReturnURL(null, true),
			'description'=>$description,/*
            'submit' => false,*/
            ];
			
			 $shippingAddress = $this->getProfile()->getShippingAddress();
        if ($shippingAddress) {

            $fields += [
                'delivery_fullname' => $shippingAddress->getFirstname() . ", " . $shippingAddress->getLastname(),
                'delivery_address'    => $shippingAddress->getStreet(),
                'delivery_city'       => $shippingAddress->getCity(),               
                'delivery_postcode'   => $shippingAddress->getZipcode(),
                'delivery_country'    => $shippingAddress->getCountry()->getCountry(),
            ];
        }
		
		return $fields;

    }

    public function processCallback(Transaction $transaction)
    {
        parent::processCallback($transaction);
		
		if ($_POST) {
		
		ini_set("SMTP","mail.nochex.com"); 
		$header = "From: apc@nochex.com";
		// Get the POST information from Nochex server
		$postvars = http_build_query($_POST);
		// Set parameters for the email
		$to = "jamesrlugton@hotmail.co.uk";
		
		if ($_POST["optional_1"] == "Callback") {
		
		$url = "https://secure.nochex.com/callback/callback.aspx";
		$ch = curl_init ();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postvars);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
		$response = curl_exec ($ch);
		curl_close ($ch);
		
		if($_POST["transaction_status"] == "100"){
		$testStatus = "Test"; 
		}else{
		$testStatus = "Live";
		}
		
		//If statement
		if ($response=="AUTHORISED") {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
			$msg = "Callback was AUTHORISED."; 
		} else { 
			$msg = "Callback was not AUTHORISED.";  // displays debug message
			
		}
		//Email the response
		//mail($to, 'APC', $msg, $header);
       } else {
		
		$url = "https://www.nochex.com/apcnet/apc.aspx";
		// Curl code to post variables back
		$ch = curl_init(); // Initialise the curl tranfer
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_VERBOSE, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postvars); // Set POST fields
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 60); // set connection time out variable - 60 seconds	
		$response = curl_exec($ch); // Post back
		curl_close($ch);
		
		if ($response=="AUTHORISED") {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
			$msg = "APC was AUTHORISED.";
		} else { 
			$msg = "APC was not AUTHORISED.";
		}
		
		}
				
										
        $status = \XLite\Model\Order\Status\Payment::STATUS_PAID;   
		
        $transaction->setStatus($status);
        $transaction->setNote($msg);
		
		}	
		
	}

    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
	
	    parent::processReturn($transaction);

        $request = \XLite\Core\Request::getInstance();

        $status = $transaction::STATUS_SUCCESS;           
        $notes = 'Payment Complete';

        $this->transaction->setStatus($status);
        $this->transaction->setNote($notes);
		
    }
}