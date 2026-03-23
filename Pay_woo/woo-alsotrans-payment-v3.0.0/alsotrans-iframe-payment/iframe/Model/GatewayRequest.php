<?php

class GatewayRequest
{
    public $model;
    public $ip;
    public $payment_method;
    public $merchant_reference;
    public $currency;
    public $amount;
    public $customer_email;
    public $payment_information;
    public $return_url;
    public $sid;
    public $user_agent;
    public $products;
    public $billing_details;
    public $shipping_details;
    public $redirect_url;
    public $metadata;
    public $sourceType;
    public $cardBrand;

    public function __construct($data)
    {
        if(!is_array($data)){
            throw new Exception('data must be array!');
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

    /**
     * @return mixed
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @param mixed $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * @return mixed
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param mixed $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return mixed
     */
    public function getPaymentMethod()
    {
        return $this->payment_method;
    }

    /**
     * @param mixed $payment_method
     */
    public function setPaymentMethod($payment_method)
    {
        $this->payment_method = $payment_method;
    }

    /**
     * @return mixed
     */
    public function getMerchantReference()
    {
        return $this->merchant_reference;
    }

    /**
     * @param mixed $merchant_reference
     */
    public function setMerchantReference($merchant_reference)
    {
        $this->merchant_reference = $merchant_reference;
    }

    /**
     * @return mixed
     */
    public function getCurrency()
    {
        return $this->currency;
    }

    /**
     * @param mixed $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param mixed $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return mixed
     */
    public function getCustomerEmail()
    {
        return $this->customer_email;
    }

    /**
     * @param mixed $customer_email
     */
    public function setCustomerEmail($customer_email)
    {
        $this->customer_email = $customer_email;
    }

    /**
     * @return mixed
     */
    public function getPaymentInformation()
    {
        return $this->payment_information;
    }

    /**
     * @param mixed $payment_information
     */
    public function setPaymentInformation($payment_information)
    {
        $this->payment_information = $payment_information;
    }

    /**
     * @return mixed
     */
    public function getReturnUrl()
    {
        return $this->return_url;
    }

    /**
     * @param mixed $return_url
     */
    public function setReturnUrl($return_url)
    {
        $this->return_url = $return_url;
    }

    /**
     * @return mixed
     */
    public function getSid()
    {
        return $this->sid;
    }

    /**
     * @param mixed $sid
     */
    public function setSid($sid)
    {
        $this->sid = $sid;
    }

    /**
     * @return mixed
     */
    public function getUserAgent()
    {
        return $this->user_agent;
    }

    /**
     * @param mixed $user_agent
     */
    public function setUserAgent($user_agent)
    {
        $this->user_agent = $user_agent;
    }

    /**
     * @return mixed
     */
    public function getProducts()
    {
        return $this->products;
    }

    /**
     * @param mixed $products
     */
    public function setProducts($products)
    {
        $this->products = $products;
    }

    /**
     * @return mixed
     */
    public function getBillingDetails()
    {
        return $this->billing_details;
    }

    /**
     * @param mixed $billing_details
     */
    public function setBillingDetails($billing_details)
    {
        $this->billing_details = $billing_details;
    }

    /**
     * @return mixed
     */
    public function getShippingDetails()
    {
        return $this->shipping_details;
    }

    /**
     * @param mixed $shipping_details
     */
    public function setShippingDetails($shipping_details)
    {
        $this->shipping_details = $shipping_details;
    }

    /**
     * @return mixed
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param mixed $metadata
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @return mixed
     */
    public function getSourceType()
    {
        return $this->sourceType;
    }

    /**
     * @param mixed $sourceType
     */
    public function setSourceType($sourceType)
    {
        $this->sourceType = $sourceType;
    }

    /**
     * @return mixed
     */
    public function getCardBrand()
    {
        return $this->cardBrand;
    }

    /**
     * @param mixed $cardBrand
     */
    public function setCardBrand($cardBrand)
    {
        $this->cardBrand = $cardBrand;
    }


}