<?php

require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Utils/Utils.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Utils/HttpHelper.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/GatewayRequest.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/GatewayResponse.php';

class Iframe {
    protected $config;
    protected $dataObj;

    public function __construct(ATPConfig $config) {
        $this->config = $config;
    }

    public function create($data) {
        $this->dataObj = new GatewayRequest($data);
        $this->validateBeforeSubmit();
        $header = Utils::buildPostHeader($this->config->getConfig(), $this->dataObj->toArray());
        $result = HttpHelper::request('POST', $this->config->getGateway(), $header, $this->dataObj->toArray());
        $result = json_decode($result, true);
        if ($result['code'] == '0000') {
            return new GatewayResponse($result['data']);
        }
        throw new Exception('Create order error: code:' . $result['code'] . ', message:' . ($result['describe'] ?? 'Unknown error'));
    }

    public function getToken() {
        $header = Utils::buildGetHeader($this->config->getConfig());
        $result = HttpHelper::request('GET', $this->config->getIframeUrl(), $header);
        $result = json_decode($result, true);
        if ($result['code'] == '0000') {
            return $result['data']['token'];
        }
        throw new Exception('Get token error: code:' . $result['code'] . ', message:' . ($result['describe'] ?? 'Unknown error'));
    }

    private function confirmAmount() {
        $this->dataObj->setAmount(Utils::confirmAmountByCurrency(
            $this->dataObj->getAmount(),
            $this->dataObj->getCurrency(),
            $this->config->getCurrencies()
        ));

        $confirmedProducts = array();
        $products = $this->dataObj->getProducts();
        if (is_array($products)) {
            foreach ($products as $v) {
                if (empty($v['amount'] || $v['currency'])) {
                    throw new Exception('Product error! amount or currency error!');
                }
                $v['amount'] = Utils::confirmAmountByCurrency($v['amount'], $v['currency'], $this->config->getCurrencies());
                $confirmedProducts[] = $v;
            }
        } else {
            $confirmedProducts = $products;
        }
        $this->dataObj->setProducts($confirmedProducts);
    }

    private function confirmCustomerInfo() {
        $billingInfo = $this->dataObj->getBillingDetails();
        $shippingInfo = $this->dataObj->getShippingDetails();

        if (!empty($billingInfo) && is_array($billingInfo)) {
            $this->dataObj->setBillingDetails($this->handleEmptyData($billingInfo));
        } else {
            throw new Exception('Billing information empty or format error!');
        }

        if (!empty($shippingInfo) && is_array($shippingInfo)) {
            $this->dataObj->setShippingDetails($this->handleEmptyData($shippingInfo));
        } else {
            throw new Exception('Shipping information empty or format error!');
        }
    }

    private function handleEmptyData($data) {
        $countryNoZipCode = $this->config->getCountryNoZipcode();
        $countryNoCity = $this->config->getCountryNoCity();
        $countryNoState = $this->config->getCountryNoState();

        if (empty($data['country'])) {
            throw new Exception('Country can not be empty!');
        }

        if (empty($data['postal_code'])) {
            if (in_array($data['country'], $countryNoZipCode)) {
                $data['postal_code'] = '000000';
            }
        }

        if (empty($data['state'])) {
            if (in_array($data['country'], $countryNoState)) {
                $data['state'] = empty($data['city']) ? 'default' : $data['city'];
            }
        }

        if (empty($data['city'])) {
            if (in_array($data['country'], $countryNoCity)) {
                $data['city'] = empty($data['state']) ? 'default' : $data['state'];
            }
        }

        return $data;
    }

    private function validateBeforeSubmit() {
        $this->confirmCustomerInfo();
        $this->confirmAmount();
    }
}
