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
        // Write detailed request log to uploads/alsotrans-edd-logs for debugging (cross-platform)
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/alsotrans-edd-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            $log_file = $log_dir . '/' . date('Y-m') . '.log';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " " . $debug_log, FILE_APPEND);
        } else {
            // Fallback for non-WP contexts
            @file_put_contents(sys_get_temp_dir() . '/alsotrans_debug.log', date('Y-m-d H:i:s') . " " . $debug_log, FILE_APPEND);
        }

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
        // Log response to the same upload log for correlation
        $response_log = "=== HTTP POST RESPONSE ===\nHTTP Code: $http_code\nResponse: $result\n";
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/alsotrans-edd-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            $log_file = $log_dir . '/' . date('Y-m') . '.log';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " " . $response_log, FILE_APPEND);
        } else {
            @file_put_contents(sys_get_temp_dir() . '/alsotrans_debug.log', date('Y-m-d H:i:s') . " " . $response_log, FILE_APPEND);
        }
        
        curl_close($curl);
        return $result;
    }

    public static function curlGet($url, array $header = array()) {
        $header = self::headerFormat($header);

        error_log('=== HTTP GET REQUEST ===');
        error_log('URL: ' . $url);
        error_log('HEADERS: ' . json_encode($header));
        // Also write GET request to uploads log
        $debug_log_get = "=== HTTP GET REQUEST ===\nURL: $url\nHEADERS: " . json_encode($header) . "\n";
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/alsotrans-edd-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            $log_file = $log_dir . '/' . date('Y-m') . '.log';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " " . $debug_log_get, FILE_APPEND);
        } else {
            @file_put_contents(sys_get_temp_dir() . '/alsotrans_debug.log', date('Y-m-d H:i:s') . " " . $debug_log_get, FILE_APPEND);
        }

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
        $response_log_get = "=== HTTP GET RESPONSE ===\nHTTP Code: $http_code\nResponse: $result\n";
        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/alsotrans-edd-logs';
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            $log_file = $log_dir . '/' . date('Y-m') . '.log';
            file_put_contents($log_file, date('Y-m-d H:i:s') . " " . $response_log_get, FILE_APPEND);
        } else {
            @file_put_contents(sys_get_temp_dir() . '/alsotrans_debug.log', date('Y-m-d H:i:s') . " " . $response_log_get, FILE_APPEND);
        }
        
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
