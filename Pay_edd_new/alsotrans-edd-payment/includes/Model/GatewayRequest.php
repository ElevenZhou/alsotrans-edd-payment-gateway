<?php

class GatewayRequest {
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

    public function __construct($data = array()) {
        if (!is_array($data)) {
            throw new Exception('data must be array!');
        }
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray() {
        return get_object_vars($this);
    }

    public function getModel() {
        return $this->model;
    }

    public function setModel($model) {
        $this->model = $model;
    }

    public function getIp() {
        return $this->ip;
    }

    public function setIp($ip) {
        $this->ip = $ip;
    }

    public function getPaymentMethod() {
        return $this->payment_method;
    }

    public function setPaymentMethod($payment_method) {
        $this->payment_method = $payment_method;
    }

    public function getMerchantReference() {
        return $this->merchant_reference;
    }

    public function setMerchantReference($merchant_reference) {
        $this->merchant_reference = $merchant_reference;
    }

    public function getCurrency() {
        return $this->currency;
    }

    public function setCurrency($currency) {
        $this->currency = $currency;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function setAmount($amount) {
        $this->amount = $amount;
    }

    public function getCustomerEmail() {
        return $this->customer_email;
    }

    public function setCustomerEmail($customer_email) {
        $this->customer_email = $customer_email;
    }

    public function getPaymentInformation() {
        return $this->payment_information;
    }

    public function setPaymentInformation($payment_information) {
        $this->payment_information = $payment_information;
    }

    public function getReturnUrl() {
        return $this->return_url;
    }

    public function setReturnUrl($return_url) {
        $this->return_url = $return_url;
    }

    public function getSid() {
        return $this->sid;
    }

    public function setSid($sid) {
        $this->sid = $sid;
    }

    public function getUserAgent() {
        return $this->user_agent;
    }

    public function setUserAgent($user_agent) {
        $this->user_agent = $user_agent;
    }

    public function getProducts() {
        return $this->products;
    }

    public function setProducts($products) {
        $this->products = $products;
    }

    public function getBillingDetails() {
        return $this->billing_details;
    }

    public function setBillingDetails($billing_details) {
        $this->billing_details = $billing_details;
    }

    public function getShippingDetails() {
        return $this->shipping_details;
    }

    public function setShippingDetails($shipping_details) {
        $this->shipping_details = $shipping_details;
    }

    public function getMetadata() {
        return $this->metadata;
    }

    public function setMetadata($metadata) {
        $this->metadata = $metadata;
    }

    public function getSourceType() {
        return $this->sourceType;
    }

    public function setSourceType($sourceType) {
        $this->sourceType = $sourceType;
    }

    public function getCardBrand() {
        return $this->cardBrand;
    }

    public function setCardBrand($cardBrand) {
        $this->cardBrand = $cardBrand;
    }
}
