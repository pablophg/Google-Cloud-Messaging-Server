<?php
/**
 * Google Cloud Messaging
 *
 * @author Pablo Herreros García <pablophg33@gmail.com>
 * @copyright Copyright (c) 2015, Pablo Herreros García
 */
 
 /*
	TODO:
		- Handle all errors from https://developer.android.com/google/gcm/server-ref.html#error-codes
		
		- Add an option to set a TTL to a message
 */

	ini_set('display_errors',1);
	ini_set('display_startup_errors',1);
	error_reporting(-1);

	class HttpStatusCodes {
		public static $codes = array(
			100 => 'Continue',
			101 => 'Switching Protocols',
			102 => 'Processing',
			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',
			207 => 'Multi-Status',
			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			306 => 'Switch Proxy',
			307 => 'Temporary Redirect',
			400 => 'Bad Request',
			401 => 'Unauthorized',
			402 => 'Payment Required',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',
			418 => 'I\'m a teapot',
			422 => 'Unprocessable Entity',
			423 => 'Locked',
			424 => 'Failed Dependency',
			425 => 'Unordered Collection',
			426 => 'Upgrade Required',
			449 => 'Retry With',
			450 => 'Blocked by Windows Parental Controls',
			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported',
			506 => 'Variant Also Negotiates',
			507 => 'Insufficient Storage',
			509 => 'Bandwidth Limit Exceeded',
			510 => 'Not Extended'
		);
	}

	class GCMRequest {
		var $api_url = 'https://android.googleapis.com/gcm/send';

		var $api_key;
		var $devices;
		var $data;
		
		public function __construct($api_key) {
			if (empty($api_key)) {
				throw new Exception("API Key not set");
			}
			else {
				$this->api_key = $api_key;
			}
		}
		
		public function sendMessage() {
			return $this->sendPostRequest();
		}
		
		public function setTargetDevices($devices) {
			if (empty($devices)) {
				throw new Exception("setTargetDevices requires at least one device registration ID");
			}
			else {
				if (is_array($devices)) {
					if (count($devices) <= 0) {
						throw new Exception("Devices list is empty");
					}
					else{
						$this->devices = $devices;
					}
				}
				else {
					$this->devices = array($devices);
				}
			}
		}
		
		public function setData($data){
			$test_json = json_encode($data);
			if (strlen($test_json) > 4096){
				throw new Exception("Payload exceeds 4096 byte limit");
			}
			else{
				$this->data = $data;
			}
		}

		private function sendPostRequest() {
			$request_headers = array(
				'Authorization: key='.$this->api_key,
				'Content-Type: application/json'
			);
			
			$postdata = array();
			
			if (count($this->devices) <= 0) {
				throw new Exception("Devices list is empty");
			}
			else {
				$postdata['registration_ids'] = $this->devices;
			}
			
			if (isset($this->data)){
				$postdata['data'] = $this->data;
			}
			
			$ch = curl_init();
			
			curl_setopt($ch, CURLOPT_URL, $this->api_url);
			curl_setopt($ch, CURLOPT_HEADER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			
			$result_raw = curl_exec($ch);
			
			$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$header = substr($result_raw, 0, $header_size);
			$result = substr($result_raw, $header_size);
			
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			
			curl_close($ch);
			
			switch ($status_code) {
				case 200:
					$result = preg_replace( '/multicast_id":(\d+)/', 'multicast_id":"\1"', $result); // See https://bugs.php.net/bug.php?id=50224
					$res = json_decode($result, true);
					$response = new GCMResponse($res['multicast_id'], $res['success'], $res['failure'], $res['canonical_ids']);
					if (isset($res['results'])){
						foreach ($res['results'] as $key => $result) {
							$device_response = new GCMDevice();
							$device_response->setOriginalRegistrationId($this->devices[$key]);
							if (isset($result['message_id'])) {
								$device_response->setMessageId($result['message_id']);
							}
							if (isset($result['registration_id'])) {
								$device_response->setRegistrationId($result['registration_id']);
							}
							if (isset($result['error'])) {
								$device_response->setError($result['error']);
							}
							$response->addResult($device_response);
						}
					}
					return $response;
					break;
				case 401:
					throw new Exception("HTTP 401: Unauthorized (API Key is not valid)");
					break;
				default:
					if (array_key_exists((int) $status_code, HttpStatusCodes::$codes)) {
						throw new Exception("HTTP $status_code: ".HttpStatusCodes::$codes[$status_code]);
					}
					else {
						throw new Exception("HTTP UNKNOWN: Unknown HTTP response");
					}
			}
			
			return false;
		}
		
		
	}
	
	class GCMDevice {
		var $original_registration_id;
		
		var $message_id;
		var $registration_id;
		var $error;
		
		public function __construct() {
			// Empty constructor
		}
		
		public function setOriginalRegistrationId($original_registration_id) {
			$this->original_registration_id = $original_registration_id;
		}
		
		public function setMessageId($message_id) {
			$this->message_id = $message_id;
		}
		
		public function setRegistrationId($registration_id) {
			$this->registration_id = $registration_id;
		}
		
		public function setError($error) {
			$this->error = $error;
		}
		
		public function getOriginalRegistrationId() {
			return $this->original_registration_id;
		}
		
		public function getMessageId() {
			return $this->message_id;
		}
		
		public function getRegistrationId() {
			return $this->registration_id;
		}
		
		public function getError() {
			return $this->error;
		}
	}
	
	class GCMResponse {
		var $multicast_id;
		var $success;
		var $failure;
		var $canonical_ids;
		var $results = array();
		
		public function __construct($multicast_id, $success, $failure, $canonical_ids) {
			$this->multicast_id = $multicast_id;
			$this->success = $success;
			$this->failure = $failure;
			$this->canonical_ids = $canonical_ids;
		}
		
		public function addResult($GCMDevice_obj) {
			array_push($this->results, $GCMDevice_obj);
		}
	}
	
	// Usage:
	try{
		$gcm = new GCMRequest("YOUR_API_KEY");
		$gcm->setTargetDevices(array("thiswillbeanerror", "registration_id_1", "registration_id_2"));
		$gcm->setData(array("title" => "Example title", "description" => "Example description"));
		$result = $gcm->sendMessage();
		
		/*
			- You should replace original registration ID (getOriginalRegistrationId()) with getRegistrationId()
			on your database if getRegistrationId() is set and returns a value.
			
			- You should delete the registration from your database if getError() returns "NotRegistered"
			
			- You should retry sending to those devices where getError() returns "Unavailable"
			
			- You should retry sending to those devices where getError() returns "Unavailable"
		*/
		echo '<pre>';
		print_r($result);
		echo '</pre>';
	}
	catch (Exception $e){
		echo $e->getMessage();
	}
?>
