<?php

require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/CallbackData.php';

class CallbackService {
    protected $config;

    public function __construct($config) {
        $this->config = $config;
    }

    public function create($data) {
        $callbackData = new CallbackData($data);
        $this->verifySign($callbackData);
        return $callbackData;
    }

    private function verifySign($callbackData) {
        $merchantId = isset($this->config['X-MERCHANT-ID']) ? $this->config['X-MERCHANT-ID'] : '';
        $md5key = isset($this->config['X-MD5-KEY']) ? $this->config['X-MD5-KEY'] : '';

        if (empty($md5key) || empty($merchantId)) {
            throw new Exception('Callback verify error: merchant id or md5key empty!');
        }

        $str = $callbackData->id . $callbackData->status . $callbackData->amount_value . $md5key . $merchantId . $callbackData->request_id;
        $encrypt = Utils::sha256Encrypt($str);

        if ($encrypt !== $callbackData->sign_verify) {
            throw new Exception('Callback verify error: encrypt not equals to sign!');
        }

        return true;
    }
}
