<?php

require __DIR__.'/../Utils/Utils.php';
require  __DIR__.'/../Utils/HttpHelper.php';
require  __DIR__.'/../Model/IframeResponse.php';
require  __DIR__.'/../Model/GatewayRequest.php';
require  __DIR__.'/../Model/GatewayResponse.php';

class Iframe
{
    protected $config;
    protected $dataObj;

    public function __construct(ATPConfig $config)
    {
        $this->config = $config;
    }

    /**
     * 创建支付请求
     * @param $data
     * @return GatewayResponse
     * @throws Exception
     */
    public function create($data)
    {
        $this->dataObj = new GatewayRequest($data);
        //支付钱对参数做必要的校验
        $this->validateBeforeSubmit();
        //构建请求头
        $header = Utils::buildPostHeader($this->config->getConfig(),$this->dataObj->toArray());
        //发起支付
        $result = HttpHelper::request('POST',$this->config->getGateway(),$this->dataObj->toArray(),$header);
        $result = json_decode($result,true);
        if($result['code'] == '0000'){
            return new GatewayResponse($result['data']);
        }
        throw new Exception('Create order error : code:'.$result['code'].', message:'.$result['describe']);
    }

    /**
     * 获取iframe token用于展示卡信息输入框
     * @return mixed
     * @throws Exception
     */
    public function getToken()
    {
        $header = Utils::buildGetHeader($this->config->getConfig());
        $result = HttpHelper::request('GET',$this->config->getIframeUrl(),"",$header);
        $result = json_decode($result,true);
        if($result['code'] == '0000'){
            return $result['data']['token'];
        }
        throw new Exception('Get token error : code:'.$result['code'].', message:'.$result['describe']);
    }

    /**
     * 处理订单金额
     * @return void
     */
    private function confirmAmount()
    {
        //订单金额
        $this->dataObj->setAmount(Utils::confirmAmountByCurrency($this->dataObj->getAmount(),$this->dataObj->getCurrency(),$this->config->getCurrencies()));
        $confirmedProducts = array();
        $products = $this->dataObj->getProducts();
        if(is_array($products)){
            foreach ($products as $v){
                if(empty($v['amount'] || $v['currency'])){
                    throw new Exception('Product error! amount or currency error!');
                }
                $v['amount'] = Utils::confirmAmountByCurrency($v['amount'],$v['currency'],$this->config->getCurrencies());
                $confirmedProducts[] = $v;
            }
        }else{
            $confirmedProducts = $products;
        }
        $this->dataObj->setProducts($confirmedProducts);
    }

    /**
     * 检查并完善shipping和billing信息
     * @return void
     * @throws \Exception
     */
    private function confirmCustomerInfo()
    {
        $billingInfo = $this->dataObj->getBillingDetails();
        $shippingInfo = $this->dataObj->getShippingDetails();

        if(!empty($billingInfo) && is_array($billingInfo)){
            $this->dataObj->setBillingDetails($this->handleEmptyData($billingInfo));
        }else{
            throw new \Exception('Billing information empty or format error!');
        }

        if(!empty($shippingInfo) && is_array($shippingInfo)){
            $this->dataObj->setShippingDetails($this->handleEmptyData($shippingInfo));
        }else{
            throw new \Exception('Shipping information empty or format error!');
        }

    }

    /**
     * 处理state,zipcode,city为空的情况
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    private function handleEmptyData($data)
    {
        $countryNoZipCode = $this->config->getCountryNoZipcode();
        $countryNoCity = $this->config->getCountryNoCity();
        $countryNoState = $this->config->getCountryNoState();
        if(empty($data['country'])) {
            throw new \Exception('Country can not be empty!');
        }
        if(empty($data['postal_code'])){
            if(in_array($data['country'],$countryNoZipCode)){
                $data['postal_code'] = '000000';
            }
        }
        if(empty($billingInfo['state'])){
            if(in_array($data['country'],$countryNoState)){
                $data['state'] = empty($data['city'])?'default':$data['city'];
            }
        }
        if(empty($data['city'])){
            if(in_array($data['country'],$countryNoCity)){
                $data['city'] = empty($data['state'])?'default':$data['state'];
            }
        }
        Utils::checkArrayValueEmpty($data);
        return $data;
    }

    /**
     * @return void
     * @throws \Exception
     */
    private function validateBeforeSubmit()
    {
        $this->confirmCustomerInfo();
        $this->confirmAmount();
    }
}