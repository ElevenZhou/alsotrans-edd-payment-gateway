<?php

class ATPConfig
{
    private $mode;
    protected $config;
    const BASE_URL_LIVE = 'https://api.alsotransglobal.com';
    const BASE_URL_SANDBOX = 'https://stage-api.alsotransglobal.com';
    const BASE_URL_JS_LIVE = 'https://icashier.alsotransglobal.com';
    const BASE_URL_JS_SANDBOX = 'https://stage-icashier.alsotransglobal.com';
    const DEFAULT_CONFIG  = [
        //支付网关
        'gateway' => '/v3/merchants/payments',
        //支持的支付方式接口
        'paymentMethodUrl' => '/v3/payment_method',
        //支持的币种接口
        'paymentCurrencyUrl' => '/v3/support_currencies',
        //iframe获取token的url
        'iframeUrl' => '/v3/merchants/token',
        //前端引入的js
        'jsUrl' => '/index.js',
	    'shieldUrl' => '/static/js/envProfiler.js',
        //没有州的国家
        'countryNoState' => array('AF', 'AX', 'AT', 'BH', 'BB', 'BE', 'BZ', 'BA', 'BG', 'BI', 'HR', 'CW', 'CZ', 'DK', 'EE', 'ET', 'FI', 'FR', 'GF', 'DE', 'GR', 'GP', 'GG', 'HU', 'IS', 'IR', 'IM', 'IL', 'KW', 'LV', 'LB', 'LI', 'LU', 'MT', 'MQ', 'YT', 'NL', 'NZ', 'NO', 'PL', 'PT', 'PR', 'RE', 'RW', 'MF', 'RS', 'SG', 'SK', 'SI', 'KR', 'LK', 'SE', 'CH', 'AE', 'GB', 'VN',),
        //没有邮编的国家
        'countryNoZipcode' => array('BH', 'BD', 'CL', 'CO', 'GH', 'HK', 'IE', 'JM', 'NP', 'UG', 'VN', 'ZW', 'BO', 'MZ', 'NL', 'NG', 'NO', 'WS', 'ST', 'AO', 'BS', 'BH', 'BD', 'BZ', 'BO', 'CL', 'CO', 'CW', 'GH', 'GT', 'HK', 'IE', 'JM', 'MZ', 'NP', 'NG', 'KN', 'WS', 'UG', 'AE', 'VN', 'ZW',),
        //城市可为空的国家
        'countryNoCity' => array('SG'),
        //币种
        'currencies'=>'[{"currency":"USD","exponent":2},{"currency":"EUR","exponent":2},{"currency":"CNY","exponent":2},{"currency":"GBP","exponent":2},{"currency":"HKD","exponent":2},{"currency":"JPY","exponent":0},{"currency":"AUD","exponent":2},{"currency":"NOK","exponent":2},{"currency":"SGD","exponent":2},{"currency":"CAD","exponent":2},{"currency":"SEK","exponent":2},{"currency":"DKK","exponent":2},{"currency":"NZD","exponent":2},{"currency":"TWD","exponent":2},{"currency":"CHF","exponent":2},{"currency":"RUB","exponent":2},{"currency":"TRY","exponent":2},{"currency":"MYR","exponent":2},{"currency":"PLN","exponent":2},{"currency":"BRL","exponent":2},{"currency":"HUF","exponent":0},{"currency":"CZK","exponent":2},{"currency":"KRW","exponent":0},{"currency":"VND","exponent":0},{"currency":"IDR","exponent":0},{"currency":"THB","exponent":2},{"currency":"PHP","exponent":2},{"currency":"RON","exponent":2},{"currency":"BHD","exponent":3},{"currency":"AED","exponent":2},{"currency":"BGN","exponent":2},{"currency":"ILS","exponent":2},{"currency":"COP","exponent":2},{"currency":"CLP","exponent":2},{"currency":"ARS","exponent":2},{"currency":"MXN","exponent":2},{"currency":"SAR","exponent":2},{"currency":"BND","exponent":2},{"currency":"INR","exponent":2},{"currency":"GEL","exponent":2},{"currency":"AZN","exponent":2},{"currency":"AMD","exponent":2}]',
    ];

    public function __construct($config = [],$mode = 'live')
    {
        if(!in_array($mode,['live','sandbox'])){
            throw new \Exception('mode must be live or sandbox! '. $mode.' given.');
        }
        $this->mode = $mode;

        if(!is_array($config)){
            throw new \Exception('config must be array!');
        }
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getCountryNoState()
    {
        return self::DEFAULT_CONFIG['countryNoState'];
    }

    public function getCountryNoZipcode()
    {
        return self::DEFAULT_CONFIG['countryNoZipcode'];
    }

    public function getCountryNoCity()
    {
        return self::DEFAULT_CONFIG['countryNoCity'];
    }

    public function getCurrencies()
    {
        return json_decode(self::DEFAULT_CONFIG['currencies'],true);
    }

    public function getGateway()
    {
        return self::get('gateway');
    }

    public function getPaymentMethodUrl()
    {
        return self::get('paymentMethodUrl');
    }

    public function getPaymentCurrencyUrl()
    {
        return self::get('paymentCurrencyUrl');
    }

	public function getShieldUrl(  ) {
		return self::get('shieldUrl');
	}

    public function getJsUrl()
    {
        return $this->mode == 'live' ? self::BASE_URL_JS_LIVE.self::DEFAULT_CONFIG['jsUrl'] : self::BASE_URL_JS_SANDBOX.self::DEFAULT_CONFIG['jsUrl'];
    }

    public function getIframeUrl()
    {
        return self::get('iframeUrl');
    }

    private function get($key)
    {
        return $this->mode == 'live' ? self::BASE_URL_LIVE.self::DEFAULT_CONFIG[$key] : self::BASE_URL_SANDBOX.self::DEFAULT_CONFIG[$key];
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    public function getCustomerIp()
    {
        return Utils::getIp();
    }
}