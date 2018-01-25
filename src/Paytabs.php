<?php
namespace Lutdev;

class Paytabs
{
	const TESTING = 'https://localhost:8888/paytabs/apiv2/index';
	const AUTHENTICATION = 'https://www.paytabs.com/apiv2/validate_secret_key';
	const PAYPAGE_URL = 'https://www.paytabs.com/apiv2/create_pay_page';
	const VERIFY_URL = 'https://www.paytabs.com/apiv2/verify_payment';

	public $paymentURL;
	public $payPageID;

	protected $merchantEmail;
	protected $apiKey;

	protected $responseResult;
	protected $responseCode;
	protected $responseMessage;

	protected $errors = [];
	protected $success = false;

	private $merchantSecretKey;

	public function __construct($merchantEmail, $secretKey)
	{
		$this->merchantEmail = $merchantEmail;
		$this->merchantSecretKey = $secretKey;
		$this->apiKey = null;
	}

	#region Setters && getters
	public function getResponseMessage()
	{
		return $this->responseMessage;
	}

	public function getResponseCode()
	{
		return $this->responseCode;
	}

	public function getResponseResult()
	{
		return $this->responseResult;
	}

	public function getErrors()
	{
		return $this->errors;
	}

	public function isSuccess()
	{
		return $this->success;
	}

	public function isFail()
	{
		return !$this->success;
	}
	#endregion

	public function authentication()
	{
		$obj = $this->runPost(self::AUTHENTICATION, [
			'merchant_email' => $this->merchantEmail,
			'secret_key' =>  $this->merchantSecretKey
		])->getResponseResult();

		$this->apiKey = $obj->access == 'granted' ? $obj->api_key : null;

		return $this->apiKey;
	}

	public function createPayPage($values)
	{
		$values['merchant_email'] = $this->merchantEmail;
		$values['secret_key'] = $this->merchantSecretKey;
		$values['ip_customer'] = $_SERVER['REMOTE_ADDR'];
		$values['ip_merchant'] = $_SERVER['SERVER_ADDR'];

		$this->runPost(self::PAYPAGE_URL, $values);

		if($this->isSuccess()){
			$this->paymentURL = $this->responseResult->payment_url;
			$this->payPageID = $this->responseResult->p_id;
		}

		return $this;
	}

	public function sendRequest()
	{
		$values['ip_customer'] = $_SERVER['REMOTE_ADDR'];
		$values['ip_merchant'] = $_SERVER['SERVER_ADDR'];

		return $this->runPost(self::TESTING, $values);
	}


	public function verifyPayment($payment_reference)
	{
		$values['merchant_email'] = $this->merchantEmail;
		$values['secret_key'] = $this->merchantSecretKey;
		$values['payment_reference'] = $payment_reference;

		return $this->runPost(self::VERIFY_URL, $values);
	}

	public function runPost($url, $fields)
	{
		$fieldsString = '';

		foreach ($fields as $key => $value) {
			$fieldsString .= $key . '=' . $value . '&';
		}

		rtrim($fieldsString, '&');
		$ch = curl_init();

		$ip = $_SERVER['REMOTE_ADDR'];

		$IPAddress = [
			'REMOTE_ADDR' => $ip,
			'HTTP_X_FORWARDED_FOR' => $ip
		];

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $IPAddress);
		curl_setopt($ch, CURLOPT_POST, count($fields));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_REFERER, 1);

		$result = curl_exec($ch);
		curl_close($ch);

		$result = json_decode($result);

		$this->responseResult = $result;
		$this->responseCode = (int)$result->response_code;
		$this->responseMessage = $result->result;

		$this->handleErrors();

		return $this;
	}

	protected function handleErrors()
	{
		switch($this->responseCode){
			case 100:
			case 4000:
			case 4012:
				$this->success = true;
				$this->errors = [];
				break;
			default:
				$this->success = false;
				$this->errors[] = [
					'code' => $this->responseCode,
					'message' => $this->responseMessage
				];
		}

		return $this;
	}
}