<?php

class GatewayResponse
{
    public $id;
    public $status;
    public $payment_method;
    public $payment_result;
    public $error;
    public $currency;
    public $amount_value;
    public $merchant_reference;
    public $created;
    public $live_mode;
    public $request_id;
    public $redirect_url;
    public $version;
    private $formattedErrorCode;
    private $formattedErrorMessage;
    public $responseData;

    public function __construct($data)
    {
        if(!is_array($data)){
            $data = json_decode($data,true);
            if(!is_array($data)){
                throw new Exception('data must be array!');
            }
        }

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

    }

    public function toArray()
    {
        return get_object_vars($this);
    }

    public function formatErrorInfo()
    {
        $paymentResult = $this->payment_result;
        $paymentError = $this->error;
        if(is_array($paymentResult) && !empty($paymentResult['fail_message'])){
            $this->formattedErrorCode = $paymentResult['fail_code'];
            $this->formattedErrorMessage = $paymentResult['fail_message'];
        }
        if(is_array($paymentError) && !empty($paymentError['error'])){
            $this->formattedErrorCode = $paymentError['code'];
            $this->formattedErrorMessage = $paymentError['message'];
        }
    }

    public function getFormattedErrorCode()
    {
        if(empty($this->formattedErrorCode)){
            $this->formatErrorInfo();
        }
        return $this->formattedErrorCode;
    }

    public function getFormattedErrorMessage()
    {
        if(empty($this->formattedErrorMessage)){
            $this->formatErrorInfo();
        }
        return $this->formattedErrorMessage;
    }

}