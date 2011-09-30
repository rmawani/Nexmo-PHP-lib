<?php

/**
 * Class NexmoMessage handles the methods and properties of sending an SMS message.
 * 
 * Usage: $var = new NexoMessage ( $account_key, $account_password );
 * Methods:
 *     sendText ( $to, $from, $message )
 *     sendBinary ( $to, $from, $body, $udh )
 *     pushWap ( $to, $from, $title, $url, $validity = 172800000 )
 *     displayOverview( $nexmo_response=null )
 *     
 *
 */

class NexmoMessage {

	// Nexmo account credentials
	private $nx_key = '';
	private $nx_password = '';

	/**
	 * Nexmo server URI
	 *
	 * We're sticking with the JSON interface here since json
	 * parsing is built into PHP and requires no extensions.
	 * This will also keep any debugging to a minimum due to
	 * not worrying about which parser is being used.
	 */
	var $nx_uri = 'https://rest.nexmo.com/sms/json';


	/**
	 * @var string The most recent unparsed Nexmo response.
	 */
	var $unparsed_response = '';
	
	
	/**
	 * @var array The most recent parsed Nexmo response.
	 */
	var $nexmo_response = '';
	

	/**
	 * @var array If one exists, any inbound message
	 */
	var $inbound_text = array();

	
	function NexmoMessage ($nx_key, $nx_password) {
		$this->nx_key = $nx_key;
		$this->nx_password = $nx_password;

		// Get any inbound text if they exist
		$this->inbound_text = $this->inboundText();
	}



	/**
	 * Prepare new text message.
	 */
	function sendText ( $to, $from, $message ) {
	
		if ( !is_numeric($from) ) {
			//Must be UTF-8 Encoded if not a continuous number
			$from = utf8_encode( $from );
		}
		
		//Must be UTF-8 Encoded
		$message = utf8_encode( $message );
		
		// URL Encode
		$from = urlencode( $from );
		$message = urlencode( $message );
		
		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'text' => $message
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare new WAP message.
	 */
	function sendBinary ( $to, $from, $body, $udh ) {
	
		//Binary messages must be hex encoded
		$body = bin2hex ( $body );
		$udh = bin2hex ( $udh );

		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'type' => 'binary',
			'body' => $body,
			'udh' => $udh
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare new binary message.
	 */
	function pushWap ( $to, $from, $title, $url, $validity = 172800000 ) {
		
		//WAP Push title and URL must be UTF-8 Encoded
		$title = utf8_encode ( $body );
		$url = utf8_encode ( $udh );

		// Send away!
		$post = array(
			'from' => $from,
			'to' => $to,
			'type' => 'wappush',
			'url' => $url,
			'title' => $title,
			'validity' => $validity
		);
		return $this->sendRequest ( $post );
		
	}
	
	
	/**
	 * Prepare and send new text message.
	 */
	private function sendRequest ( $data ) {
	 	// Build the post data
	 	$data = array_merge($data, array('username' => $this->nx_key, 'password' => $this->nx_password));
	 	$post = '';
	 	foreach($data as $k => $v){
	 		$post .= "&$k=$v";
	 	}

		$to_nexmo = curl_init( $this->nx_uri );
		curl_setopt( $to_nexmo, CURLOPT_POST, true );
		curl_setopt( $to_nexmo, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $to_nexmo, CURLOPT_POSTFIELDS, $post );
		$from_nexmo = curl_exec( $to_nexmo );
		curl_close ( $to_nexmo );
		
		$from_nexmo = str_replace('-', '', $from_nexmo);
		$this->unparsed_response = $from_nexmo;
		
		return $this->nexmoParse( $from_nexmo );
	 
	 }
	
	
	/**
	 * Parse server response.
	 */
	private function nexmoParse ( $from_nexmo ) {
		
		$response_obj = json_decode( $from_nexmo );
		
		if ($response_obj) {
			$this->nexmo_response = $response_obj;
			return $response_obj;

		} else {
			// A malformed response
			$this->nexmo_response = array();
			return false;
		}
		
	}




	public function displayOverview( $nexmo_response=null ){
		$info = (!$nexmo_response) ? $this->nexmo_response : $nexmo_response;

		//How many messages were sent?
		if ( $info->messagecount > 1 ) {
		
			$start = '<p>Your message was sent in ' . $info->messagecount . ' parts ';
		
		} else {
		
			$start = '<p>Your message was sent ';
		
		}
		
		//Check each message for errors
		$error = '';
		if (!is_array($info->messages)) $info->messages = array();

		foreach ( $info->messages as $message ) {
			if ( $message->status != 0) {
				$error .= $message->errortext . ' ';
			}
		}
		
		
		//Complete parsed response
		if ( $error == '' ) {
			$complete = 'and delivered successfully.</p>';
		} else {
			$complete = 'but there was an error: ' . $error . '</p>';
		}

		return $start . $complete;
	}







	/**
	 * Inbound text methods
	 */
	public function inboundText( $data=null ){
		if(!$data) $data = $_GET;

		if(!isset($data['text'], $data['msisdn'], $data['to']) return array();

		$ret = array(
			'to' => $data['to'],
			'from' => $data['msisdn'],
			'text' => $data['text']
		);

		return $ret;
	}


	public function reply ($message) {
		// Make sure we actually have a text to reply to
		if (empty($this->inbound_text)) {
			return false;
		}

		$msg = $this->inbound_text;

		return $this->sendText($msg['from'], $msg['to'], $message);
	}

}