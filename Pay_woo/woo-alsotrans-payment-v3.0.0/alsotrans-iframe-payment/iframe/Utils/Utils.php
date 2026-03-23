<?php

class Utils
{
    /**
     * @return mixed
     */
    public static function getDomain()
    {
        return $_SERVER['HTTP_HOST'];
    }

    /**
     * 检查数组是否有空值
     * @param $data
     * @return void
     * @throws \Exception
     */
    public static function checkArrayValueEmpty($data)
    {
        foreach($data as $k=>$v){
            if(empty($v)){
                throw new \Exception($k.' can not be empty!');
            }
        }
    }

    /**
     * 根据币种对金额进行格式化判断
     * @param $amount
     * @param $currency
     * @return mixed|string
     */
    public static function confirmAmountByCurrency($amount,$currency,$currencies)
    {
        if(strpos((string)$amount,'.') !== false){
            if(is_array($currencies)){
                foreach($currencies as $v){
                    if($v['currency'] == $currency){
                        $amount_float = floatval($amount);
                        // 币种在需要处理的列表中，将金额转为整数
                        $amount = number_format($amount_float,$v['exponent'],'.','');
                    }
                }
            }
        }
        return $amount;
    }

    /**
     * post请求的请求头构建
     * @param $header
     * @param $data
     * @return array
     * @throws \Exception
     */
    public static function buildPostHeader($header,$data)
    {
        $buildHeader = self::buildHeader($header);
        $dataStr = json_encode(self::arraySortAndRemoveEmptyParam($data),JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $sign = self::rasSign($dataStr,$header['X-PRIVATE-KEY']);
        $buildHeader['X-SIGNATURE'] = $sign;
        return $buildHeader;
    }

    /**
     * get请求的请求头构建
     * @param $data
     * @return array
     * @throws \Exception
     */
    public static function buildGetHeader($header)
    {
        $buildHeader = self::buildHeader($header);
        $signStr = 'merchant_id='.$buildHeader['X-MERCHANT-ID'].'&site_domain='.$buildHeader['X-SITE-DOMAIN'].'&timestamp='.$buildHeader['X-TIMESTAMP'];
        $sign = self::rasSign($signStr,$header['X-PRIVATE-KEY']);
        $buildHeader['X-SIGNATURE'] = $sign;
        return $buildHeader;
    }

    /**
     * 请求头公共部分构建
     * @param $header
     * @return array
     * @throws \Exception
     */
    private static function buildHeader($header){
        $header = self::checkHeaderConfig($header);
        // 获取当前时间戳（秒级 + 微秒部分）
        $microTime = microtime(true);
        $timestamp = intval($microTime * 1000);
        return array(
            'X-TIMESTAMP'=>$timestamp,
            'X-MERCHANT-ID'=>$header['X-MERCHANT-ID'],
            'X-SITE-DOMAIN'=>$header['X-SITE-DOMAIN'],
            'X-ADDON-PLATFORM'=>$header['X-ADDON-PLATFORM'],
            'X-ADDON-VERSION'=>$header['X-ADDON-VERSION'],
            'X-ADDON-TYPE'=>'iframe',
        );
    }

    /**
     * @param $header
     * @return mixed
     * @throws \Exception
     */
    private static function checkHeaderConfig($header)
    {
        $merchantId = empty($header['X-MERCHANT-ID'])?'':$header['X-MERCHANT-ID'];
        $platform = empty($header['X-ADDON-PLATFORM'])?'':$header['X-ADDON-PLATFORM'];
        $addonVersion = empty($header['X-ADDON-VERSION'])?'':$header['X-ADDON-VERSION'];
        $privateKey = empty($header['X-PRIVATE-KEY'])?'':$header['X-PRIVATE-KEY'];
        $header['X-SITE-DOMAIN'] = empty($header['X-SITE-DOMAIN'])?Utils::getDomain():$header['X-SITE-DOMAIN'];
        if(empty($privateKey)){
            throw new \Exception('X-PRIVATE-KEY empty!');
        }
        if(empty($merchantId)){
            throw new \Exception('X-MERCHANT-ID empty!');
        }
        if(empty($addonVersion)){
            throw new \Exception('X-ADDON-VERSION empty!');
        }
        if(empty($platform)){
            throw new \Exception('X-ADDON-PLATFORM empty!');
        }
        return $header;
    }

    /**
     * 加密方法
     * @param $data
     * @param $privateKey
     * @return string
     * @throws \Exception
     */
    private static function rasSign($data, $privateKey)
    {
        // 加载私钥资源
        $privateKeyResource = openssl_pkey_get_private($privateKey);
        if ($privateKeyResource === false) {
            throw new \Exception('Invalid private key：' . openssl_error_string());
        }
        // 使用私钥生成签名
        $signature = '';
        $result = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        if (!$result) {
            throw new \Exception('Generate sign error by using private key：' . openssl_error_string());
        }
        // 返回 Base64 编码的签名
        return base64_encode($signature);
    }

    /**
     * 对数组进行排序并移除空元素
     * @param $data
     * @return array
     * @throws \Exception
     */
    private static function arraySortAndRemoveEmptyParam($data)
    {
        $formattedArr = array();
        if(!is_array($data)){
            $data = json_decode($data,true);
            if(!is_array($data)){
                throw new \Exception('Data must be array!');
            }
        }
        foreach ($data as $k=>$v){
            if(is_array($v)){
                $result = self::arraySortAndRemoveEmptyParam($v);
                ksort($result);
                $formattedArr[$k] = $result;
            }else{
                if(!empty($v)){
                    $formattedArr[$k] = $v;
                }
            }
        }
        ksort($formattedArr);
        return $formattedArr;
    }

    public static function getIp()
    {
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $online_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        elseif(isset($_SERVER['HTTP_CLIENT_IP'])){
            $online_ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        elseif(isset($_SERVER['HTTP_X_REAL_IP'])){
            $online_ip = $_SERVER['HTTP_X_REAL_IP'];
        }else{
            $online_ip = $_SERVER['REMOTE_ADDR'];
        }
        $ips = explode(",",$online_ip);
        $ip = $ips[0];
        if (substr($ip,0, 7) == "::ffff:") {
            $ip = substr($ip,7);
        }
        return $ip;
    }

    public static function getServerInfo()
    {
        $phpVersion = PHP_VERSION;
        $serverType = PHP_OS;
        $serverVersion = $_SERVER['SERVER_SOFTWARE'];
        $serverIp = $_SERVER['SERVER_ADDR'];
        return array(
            'php_version'=>$phpVersion,
            'server_type'=>$serverType,
            'server_version'=>$serverVersion,
            'server_ip'=>$serverIp,
        );
    }

    /**
     * HASH encrypt
     * @param $str
     */
    public static function sha256Encrypt($str)
    {
        return hash('sha256',$str);
    }
}