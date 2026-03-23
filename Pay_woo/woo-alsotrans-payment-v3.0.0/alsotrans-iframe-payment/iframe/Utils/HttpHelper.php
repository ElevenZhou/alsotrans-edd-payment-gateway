<?php

class HttpHelper
{
    const TIMEOUT = 20;

    public static function request($method,$url,$params,$header)
    {
        if($method == 'POST'){
            return self::curlPost($url,$params,$header);
        }elseif($method == 'GET'){
            return self::curlGet($url,$header);
        }else{
            throw new \Exception('Only support POST or GET!');
        }
    }


    /**
     * @param $url
     * @param $data
     * @param array $header
     * @param string $userAgent
     * @return bool|string
     * @throws \Exception
     */
    public static function curlPost($url, $data, array $header = array())
    {
        $header['Content-Type'] = 'application/json';
        $header = self::headerFormat($header);
        if(is_array($data)){
            $data = json_encode($data);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE );
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_USERAGENT,$_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $data = curl_exec($curl);
        if (curl_error($curl)) {
            throw new \Exception("Error: " . curl_error($curl));
        }
        curl_close($curl);
        return $data;
    }

    /**
     * @param $url
     * @param $header
     * @return bool|string
     * @throws \Exception
     */
    public static function curlGet($url, array $header = array())
    {
        $header = self::headerFormat($header);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($curl);
        if (curl_error($curl)) {
            throw new \Exception("CURL Error: url:".$url.',' . curl_error($curl));
        }
        curl_close($curl);
        return $data;
    }

    /**
     * @param array $data
     * @return array
     */
    private static function headerFormat(array $data)
    {
        $header = array();
        foreach ($data as $k=>$v){
            $header[] = $k.':'.$v;
        }
        return $header;
    }
}