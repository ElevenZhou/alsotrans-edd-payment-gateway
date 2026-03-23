<?php

class Utils {

    public static function getDomain() {
        error_log('=== getDomain DEBUG ===');
        error_log('HTTP_HOST: ' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'NOT SET'));
        error_log('SERVER_NAME: ' . (isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'NOT SET'));

        if (!empty($_SERVER['HTTP_HOST'])) {
            $domain = $_SERVER['HTTP_HOST'];
            error_log('Using HTTP_HOST: ' . $domain);
            return $domain;
        }
        if (!empty($_SERVER['SERVER_NAME'])) {
            $domain = $_SERVER['SERVER_NAME'];
            error_log('Using SERVER_NAME: ' . $domain);
            return $domain;
        }
        if (function_exists('get_site_url')) {
            $site_url = get_site_url();
            error_log('get_site_url: ' . $site_url);
            $parsed = parse_url($site_url);
            if (isset($parsed['host'])) {
                error_log('Using get_site_url host: ' . $parsed['host']);
                return $parsed['host'];
            }
        }
        if (function_exists('get_option')) {
            $home_url = get_option('home');
            error_log('get_option home: ' . ($home_url ?: 'EMPTY'));
            if ($home_url) {
                $parsed = parse_url($home_url);
                if (isset($parsed['host'])) {
                    error_log('Using get_option host: ' . $parsed['host']);
                    return $parsed['host'];
                }
            }
        }
        if (function_exists('home_url')) {
            $home_url = home_url();
            error_log('home_url: ' . $home_url);
            $parsed = parse_url($home_url);
            if (isset($parsed['host'])) {
                error_log('Using home_url host: ' . $parsed['host']);
                return $parsed['host'];
            }
        }
        error_log('getDomain returning EMPTY');
        return '';
    }

    public static function checkArrayValueEmpty($data) {
        foreach ($data as $k => $v) {
            if (empty($v)) {
                throw new Exception($k . ' can not be empty!');
            }
        }
    }

    public static function confirmAmountByCurrency($amount, $currency, $currencies) {
        if (strpos((string)$amount, '.') !== false) {
            if (is_array($currencies)) {
                foreach ($currencies as $v) {
                    if ($v['currency'] == $currency) {
                        $amount_float = floatval($amount);
                        $amount = number_format($amount_float, $v['exponent'], '.', '');
                    }
                }
            }
        }
        return $amount;
    }

    public static function buildPostHeader($header, $data) {
        $buildHeader = self::buildHeader($header);
        error_log('=== buildPostHeader DEBUG ===');
        error_log('buildHeader: ' . json_encode($buildHeader));
        $dataStr = json_encode(self::arraySortAndRemoveEmptyParam($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        error_log('dataStr for sign: ' . $dataStr);
        $sign = self::rasSign($dataStr, $header['X-PRIVATE-KEY']);
        $buildHeader['X-SIGNATURE'] = $sign;
        return $buildHeader;
    }

    public static function buildGetHeader($header) {
        $buildHeader = self::buildHeader($header);
        error_log('=== buildGetHeader DEBUG ===');
        error_log('buildHeader: ' . json_encode($buildHeader));
        $signStr = 'merchant_id=' . $buildHeader['X-MERCHANT-ID'] . '&site_domain=' . $buildHeader['X-SITE-DOMAIN'] . '&timestamp=' . $buildHeader['X-TIMESTAMP'];
        error_log('signStr: ' . $signStr);
        $sign = self::rasSign($signStr, $header['X-PRIVATE-KEY']);
        $buildHeader['X-SIGNATURE'] = $sign;
        return $buildHeader;
    }

    private static function buildHeader($header) {
        $header = self::checkHeaderConfig($header);
        $microTime = microtime(true);
        $timestamp = intval($microTime * 1000);
        return array(
            'X-TIMESTAMP' => $timestamp,
            'X-MERCHANT-ID' => $header['X-MERCHANT-ID'],
            'X-SITE-DOMAIN' => $header['X-SITE-DOMAIN'],
            'X-ADDON-PLATFORM' => $header['X-ADDON-PLATFORM'],
            'X-ADDON-VERSION' => $header['X-ADDON-VERSION'],
            'X-ADDON-TYPE' => 'iframe',
        );
    }

    private static function checkHeaderConfig($header) {
        $merchantId = empty($header['X-MERCHANT-ID']) ? '' : $header['X-MERCHANT-ID'];
        $platform = empty($header['X-ADDON-PLATFORM']) ? '' : $header['X-ADDON-PLATFORM'];
        $addonVersion = empty($header['X-ADDON-VERSION']) ? '' : $header['X-ADDON-VERSION'];
        $privateKey = empty($header['X-PRIVATE-KEY']) ? '' : $header['X-PRIVATE-KEY'];

        error_log('=== checkHeaderConfig DEBUG ===');
        error_log('X-SITE-DOMAIN before: ' . ($header['X-SITE-DOMAIN'] ?? 'NOT SET'));
        error_log('X-MERCHANT-ID: ' . $merchantId);

        if (empty($header['X-SITE-DOMAIN'])) {
            $domain = self::getDomain();
            error_log('getDomain returned: ' . $domain);
            $header['X-SITE-DOMAIN'] = $domain;
        }

        if (empty($privateKey)) {
            throw new Exception('X-PRIVATE-KEY empty!');
        }
        if (empty($merchantId)) {
            throw new Exception('X-MERCHANT-ID empty!');
        }
        if (empty($addonVersion)) {
            throw new Exception('X-ADDON-VERSION empty!');
        }
        if (empty($platform)) {
            throw new Exception('X-ADDON-PLATFORM empty!');
        }
        if (empty($header['X-SITE-DOMAIN'])) {
            throw new Exception('X-SITE-DOMAIN is empty or incorrect');
        }
        return $header;
    }

    private static function rasSign($data, $privateKey) {
        $privateKeyResource = self::loadPrivateKey($privateKey);
        if ($privateKeyResource === false) {
            throw new Exception('Invalid private key: Unable to parse key');
        }
        $signature = '';
        $result = openssl_sign($data, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
        if (!$result) {
            throw new Exception('Generate sign error by using private key: ' . openssl_error_string());
        }
        return base64_encode($signature);
    }

    private static function loadPrivateKey($privateKey) {
        $privateKey = trim($privateKey);

        if (empty($privateKey)) {
            return false;
        }

        if (strpos($privateKey, '-----BEGIN ') !== false) {
            $keyResource = @openssl_pkey_get_private($privateKey);
            if ($keyResource !== false) {
                return $keyResource;
            }

            if (strpos($privateKey, 'ENCRYPTED') !== false) {
                throw new Exception('Encrypted private key requires passphrase, but passphrase is not supported.');
            }

            $keyData = $privateKey;
            $keyData = preg_replace('/-----BEGIN [A-Za-z0-9 ]+ PRIVATE KEY-----/', '', $keyData);
            $keyData = preg_replace('/-----END [A-Za-z0-9 ]+ PRIVATE KEY-----/', '', $keyData);
            $keyData = str_replace(array("\r", "\n", " ", "\t"), '', $keyData);
            $keyData = base64_decode($keyData);

            if ($keyData !== false) {
                $keyResource = @openssl_pkey_get_private($keyData);
                if ($keyResource !== false) {
                    return $keyResource;
                }

                $pemContent = "-----BEGIN RSA PRIVATE KEY-----\n";
                $pemContent .= chunk_split(base64_encode($keyData), 64, "\n");
                $pemContent .= "-----END RSA PRIVATE KEY-----";
                $keyResource = @openssl_pkey_get_private($pemContent);
                if ($keyResource !== false) {
                    return $keyResource;
                }

                $pemContent2 = "-----BEGIN PRIVATE KEY-----\n";
                $pemContent2 .= chunk_split(base64_encode($keyData), 64, "\n");
                $pemContent2 .= "-----END PRIVATE KEY-----";
                $keyResource = @openssl_pkey_get_private($pemContent2);
                if ($keyResource !== false) {
                    return $keyResource;
                }
            }
        }

        $keyResource = @openssl_pkey_get_private($privateKey);
        if ($keyResource !== false) {
            return $keyResource;
        }

        $decoded = base64_decode($privateKey, true);
        if ($decoded !== false && strlen($decoded) > 32) {
            $keyResource = @openssl_pkey_get_private($decoded);
            if ($keyResource !== false) {
                return $keyResource;
            }

            $pemContent = "-----BEGIN RSA PRIVATE KEY-----\n";
            $pemContent .= chunk_split($privateKey, 64, "\n");
            $pemContent .= "-----END RSA PRIVATE KEY-----";
            $keyResource = @openssl_pkey_get_private($pemContent);
            if ($keyResource !== false) {
                return $keyResource;
            }
        }

        return false;
    }

    private static function arraySortAndRemoveEmptyParam($data) {
        $formattedArr = array();
        if (!is_array($data)) {
            $data = json_decode($data, true);
            if (!is_array($data)) {
                throw new Exception('Data must be array!');
            }
        }
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $result = self::arraySortAndRemoveEmptyParam($v);
                ksort($result);
                $formattedArr[$k] = $result;
            } else {
                if (!empty($v)) {
                    $formattedArr[$k] = $v;
                }
            }
        }
        ksort($formattedArr);
        return $formattedArr;
    }

    public static function getIp() {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $online_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $online_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $online_ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $online_ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        }
        $ips = explode(',', $online_ip);
        $ip = trim($ips[0]);
        if (substr($ip, 0, 7) == '::ffff:') {
            $ip = substr($ip, 7);
        }
        return $ip;
    }

    public static function getServerInfo() {
        $phpVersion = PHP_VERSION;
        $serverType = PHP_OS;
        $serverVersion = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '';
        $serverIp = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        return array(
            'php_version' => $phpVersion,
            'server_type' => $serverType,
            'server_version' => $serverVersion,
            'server_ip' => $serverIp,
        );
    }

    public static function sha256Encrypt($str) {
        return hash('sha256', $str);
    }
}
