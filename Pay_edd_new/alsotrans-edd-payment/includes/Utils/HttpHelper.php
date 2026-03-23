<?php

class HttpHelper {
    const TIMEOUT = 20;

    public static function request($method, $url, $header = array(), $data = null) {
        if ($method == 'POST') {
            return self::curlPost($url, $data, $header);
        } elseif ($method == 'GET') {
            return self::curlGet($url, $header);
        } else {
            throw new Exception('Only support POST or GET!');
        }
    }

    public static function curlPost($url, $data, array $header = array()) {
        $header['Content-Type'] = 'application/json';
        $header = self::headerFormat($header);
        if (is_array($data)) {
            $data = json_encode($data);
        }

        $debug_log = "=== HTTP POST REQUEST ===\nURL: $url\nHEADERS: " . json_encode($header) . "\nBODY: $data\n";
        $upload_dir = function_exists('wp_upload_dir') ? wp_upload_dir() : false;
        $log_file = $upload_dir ? rtrim($upload_dir['basedir'], '\\/') . '/alsotrans_debug.log' : sys_get_temp_dir() . '/alsotrans_debug.log';
        file_put_contents($log_file, date('Y-m-d H:i:s') . " $debug_log", FILE_APPEND);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_USERAGENT, isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($curl);
        if (curl_error($curl)) {
            throw new Exception('CURL Error: ' . curl_error($curl));
        }
        
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        error_log('=== HTTP POST RESPONSE ===');
        error_log('HTTP Code: ' . $http_code);
        error_log('Response: ' . $result);
        
        curl_close($curl);
        return $result;
    }

    public static function curlGet($url, array $header = array()) {
        $header = self::headerFormat($header);

        error_log('=== HTTP GET REQUEST ===');
        error_log('URL: ' . $url);
        error_log('HEADERS: ' . json_encode($header));

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        $result = curl_exec($curl);
        if (curl_error($curl)) {
            throw new Exception('CURL Error: url:' . $url . ',' . curl_error($curl));
        }
        
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        error_log('=== HTTP GET RESPONSE ===');
        error_log('HTTP Code: ' . $http_code);
        error_log('Response: ' . $result);
        
        curl_close($curl);
        return $result;
    }

    private static function headerFormat(array $data) {
        $header = array();
        foreach ($data as $k => $v) {
            $header[] = $k . ':' . $v;
        }
        return $header;
    }
}
