<?php

require __DIR__.'/../Model/CallbackData.php';

class CallbackService
{
    protected $config;
    public $callbackData;

    public function __construct(ATPConfig $config)
    {
        $this->config = $config->getConfig();
    }

    /**
     * @param $data
     * @return CallbackData
     * @throws Exception
     */
    public function create($data): CallbackData
    {
        $this->callbackData = new CallbackData($data);
        $this->verifySign();
        return $this->callbackData;
    }

    public function getInfo($sign)
    {
        $merchantId = empty($this->config['X-MERCHANT-ID'])?'':$this->config['X-MERCHANT-ID'];
        $md5key = empty($this->config['X-MD5-KEY'])?'':$this->config['X-MD5-KEY'];
        $domain = Utils::getDomain();
        if($md5key && $merchantId){
            $str = $merchantId.$md5key.$domain;
            $encrypt = Utils::sha256Encrypt($str);
            if($encrypt == $sign){
                return Utils::getServerInfo();
            }
            throw new Exception('Get info verify error,encrypt not equals to sign!');
        }
        throw new Exception('Get info verify error,merchant id or md5key empty!');
    }

    /**
     * @return bool
     * @throws Exception
     */
    private function verifySign(): bool
    {
        $merchantId = empty($this->config['X-MERCHANT-ID'])?'':$this->config['X-MERCHANT-ID'];
        $md5key = empty($this->config['X-MD5-KEY'])?'':$this->config['X-MD5-KEY'];
        if($md5key && $merchantId){
            $str = $this->callbackData->id.$this->callbackData->status.$this->callbackData->amount_value.$md5key.$merchantId.$this->callbackData->request_id;
            $encrypt = Utils::sha256Encrypt($str);
            if($encrypt == $this->callbackData->sign_verify){
                return true;
            }
            throw new Exception('Callback verify error,encrypt not equals to sign!');
        }
        throw new Exception('Callback verify error,merchant id or md5key empty!');
    }

}