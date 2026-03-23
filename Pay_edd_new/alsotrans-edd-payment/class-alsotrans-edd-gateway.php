<?php

class Alsotrans_EDD_Gateway {
    private $gateway_id = 'alsotrans_iframe';
    private $config;

    public function __construct() {
        $this->init_config();
    }

    private function init_config() {
        $config = array(
            'X-MERCHANT-ID' => $this->get_merchant_id(),
            'X-PRIVATE-KEY' => $this->get_private_key(),
            'X-MD5-KEY' => $this->get_md5_key(),
            'X-ADDON-PLATFORM' => 'easy-digital-downloads',
            'X-ADDON-VERSION' => ALSOTRANS_EDD_VERSION,
            'X-SITE-DOMAIN' => $this->get_site_domain(),
        );
        $mode = $this->get_environment();
        $this->config = new ATPConfig($config, $mode);
    }

    public function get_site_domain() {
        error_log('=== get_site_domain DEBUG ===');
        $domain = trim(edd_get_option('alsotrans_site_domain', ''));
        error_log('Setting alsotrans_site_domain: ' . ($domain ?: 'EMPTY'));

        if (!empty($domain)) {
            error_log('Using setting domain: ' . $domain);
            return sanitize_text_field($domain);
        }

        error_log('Setting empty, calling Utils::getDomain()');
        $domain = Utils::getDomain();
        error_log('Utils::getDomain() returned: ' . ($domain ?: 'EMPTY'));

        if (!empty($domain)) {
            return sanitize_text_field($domain);
        }

        error_log('get_site_domain returning EMPTY');
        return '';
    }

    public function is_available() {
        return (bool) edd_get_option('alsotrans_enabled', false);
    }

    public function get_environment() {
        return edd_get_option('alsotrans_environment', 'live');
    }

    public function get_merchant_id() {
        return edd_get_option('alsotrans_merchant_id', '');
    }

    public function get_private_key() {
        return edd_get_option('alsotrans_private_key', '');
    }

    public function get_md5_key() {
        return edd_get_option('alsotrans_md5_key', '');
    }

    public function get_card_types() {
        // Alsotrans only supports Visa for this merchant
        return array('visa');
    }

    public function should_show_card_icons() {
        return edd_get_option('alsotrans_show_card_icons', 0);
    }

    public function get_title() {
        return edd_get_option('alsotrans_title', __('Credit Card', 'alsotrans-edd'));
    }

    public function get_description() {
        return edd_get_option('alsotrans_description', __('Pay securely with your credit card via Alsotrans.', 'alsotrans-edd'));
    }

    public function get_iframe_token() {
        $header = Utils::buildGetHeader($this->config->getConfig());
        $result = HttpHelper::request('GET', $this->config->getIframeUrl(), $header);
        $result = json_decode($result, true);
        if ($result['code'] == '0000') {
            return $result['data']['token'];
        }
        throw new Exception('Get token error: ' . ($result['describe'] ?? 'Unknown error'));
    }

    public function create_payment($purchase_data) {
        $cart_details = $purchase_data['cart_details'];
        $payment_data = $this->prepare_payment_data($purchase_data);

        $payment_id = edd_insert_payment($payment_data);
        if (!$payment_id) {
            throw new Exception(__('Failed to create payment record', 'alsotrans-edd'));
        }

        $request_data = $this->build_gateway_request($payment_id, $purchase_data);

        try {
            $result = $this->send_payment_request($request_data);
            $this->update_payment_with_result($payment_id, $result);
            return array(
                'payment_id' => $payment_id,
                'result' => $result
            );
        } catch (Exception $e) {
            edd_update_payment_status($payment_id, 'failed');
            edd_insert_payment_note($payment_id, __('Payment failed: ', 'alsotrans-edd') . $e->getMessage());
            throw $e;
        }
    }

    private function prepare_payment_data($purchase_data) {
        $user_info = $purchase_data['user_info'];
        return array(
            'price' => $purchase_data['price'],
            'date' => $purchase_data['date'],
            'user_email' => $purchase_data['user_email'],
            'purchase_key' => $purchase_data['purchase_key'],
            'currency' => edd_get_currency(),
            'downloads' => $purchase_data['downloads'],
            'cart_details' => $purchase_data['cart_details'],
            'user_info' => $user_info,
            'status' => 'pending'
        );
    }

    private function build_gateway_request($payment_id, $purchase_data) {
        $cart_details = $purchase_data['cart_details'];
        $user_info = $purchase_data['user_info'];
        $currency = edd_get_currency();

        $products = $this->format_products($cart_details, $currency);

        $billing_country = $user_info['country'] ?? '';
        if (empty($billing_country)) {
            $billing_country = sanitize_text_field($_POST['edd_country'] ?? $_POST['country'] ?? $_POST['billing_country'] ?? '');
        }
        $billing_country = $this->normalizeCountry($billing_country);

        $billing_details = array(
            'email' => $purchase_data['user_email'],
            'first_name' => $user_info['first_name'] ?? sanitize_text_field($_POST['edd_first'] ?? ''),
            'last_name' => $user_info['last_name'] ?? sanitize_text_field($_POST['edd_last'] ?? ''),
            'address' => $this->formatAddress($user_info),
            'city' => $user_info['city'] ?? sanitize_text_field($_POST['card_city'] ?? ''),
            'state' => $user_info['state'] ?? sanitize_text_field($_POST['card_state'] ?? ''),
            'country' => $billing_country,
            'postal_code' => $user_info['zip'] ?? sanitize_text_field($_POST['card_zip'] ?? ''),
            'phone' => $user_info['phone'] ?? sanitize_text_field($_POST['card_phone'] ?? ''),
        );

        // Debug logging for billing data extraction
        error_log('=== BILLING DATA DEBUG ===');
        error_log('user_info: ' . print_r($user_info, true));
        error_log('billing_country from user_info: ' . ($user_info['country'] ?? 'NOT_SET'));
        error_log('billing_country from POST: ' . ($_POST['billing_country'] ?? 'NOT_SET'));
        error_log('final billing_country: ' . $billing_country);
        error_log('billing_details: ' . print_r($billing_details, true));

        $shipping_details = $billing_details;
        if (!empty($purchase_data['shipping'])) {
            $shipping_info = $purchase_data['shipping'];
            $shipping_country = $shipping_info['country'] ?? $billing_details['country'];
            $shipping_country = $this->normalizeCountry($shipping_country);
            $shipping_details = array(
                'email' => $purchase_data['user_email'],
                'first_name' => $shipping_info['first_name'] ?? $billing_details['first_name'],
                'last_name' => $shipping_info['last_name'] ?? $billing_details['last_name'],
                'address' => $this->formatAddress($shipping_info),
                'city' => $shipping_info['city'] ?? $billing_details['city'],
                'state' => $shipping_info['state'] ?? $billing_details['state'],
                'country' => $shipping_country,
                'postal_code' => $shipping_info['zip'] ?? $billing_details['postal_code'],
                'phone' => $billing_details['phone'],
            );
        }

        $card_token = isset($_POST['alsotrans_token']) ? sanitize_text_field($_POST['alsotrans_token']) : '';
        error_log('=== PAYMENT DEBUG ===');
        error_log('alsotrans_token from POST: ' . ($card_token ?: 'EMPTY'));
        error_log('All POST data: ' . print_r($_POST, true));
        error_log('Billing details for request: ' . print_r($billing_details, true));
        error_log('Shipping details for request: ' . print_r($shipping_details, true));
        $sid = isset($_POST['alsotrans_sid']) ? sanitize_text_field($_POST['alsotrans_sid']) : '';
        if (empty($card_token)) {
            error_log('=== PAYMENT DEBUG WARNING: card_token is empty, cannot proceed without token.');
        }

        $callback_url = home_url('/?edd-listener=alsotrans_ipn');

        return array(
            'model' => 'EMBED',
            'amount' => $purchase_data['price'],
            'currency' => $currency,
            'merchant_reference' => (string) $payment_id,
            'customer_email' => $purchase_data['user_email'],
            'payment_method' => 'card',
            'payment_information' => array(
                'card_token' => $card_token,
                'holder_name' => ($user_info['first_name'] ?? '') . ' ' . ($user_info['last_name'] ?? ''),
            ),
            'products' => $products,
            'billing_details' => $billing_details,
            'shipping_details' => $shipping_details,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'redirect_url' => $callback_url,
            'sid' => $sid,
            'ip' => $this->get_customer_ip(),
            'metadata' => '',
        );
    }

    private function formatAddress($user_info) {
        $address = $user_info['address'] ?? sanitize_text_field($_POST['card_address'] ?? '');
        $address2 = $user_info['address_2'] ?? sanitize_text_field($_POST['card_address_2'] ?? '');

        if (is_array($address)) {
            $address = implode(', ', array_filter($address));
        }

        $parts = array_filter([$address, $address2]);
        return implode(', ', $parts);
    }

    private function format_products($cart_details, $currency) {
        $products = array();
        if (is_array($cart_details)) {
            foreach ($cart_details as $item) {
                $price = isset($item['price']) ? $item['price'] : 0;
                if (isset($item['item_number']['options']['price'])) {
                    $price = $item['item_number']['options']['price'];
                }
                $products[] = array(
                    'sku' => isset($item['id']) ? (string) $item['id'] : 'atp-default',
                    'name' => isset($item['name']) ? $item['name'] : 'Product',
                    'price' => $price,
                    'quantity' => isset($item['quantity']) ? (int) $item['quantity'] : 1,
                    'currency' => $currency
                );
            }
        }
        return $products;
    }

    private function send_payment_request($request_data) {
        $header = Utils::buildPostHeader($this->config->getConfig(), $request_data);
        error_log('=== POST PAYMENT REQUEST ===');
        error_log('Header: ' . json_encode($header));
        error_log('Request Data: ' . json_encode($request_data));
        if (empty($request_data['payment_information']['card_token'])) {
            error_log('=== POST PAYMENT REQUEST WARNING: card_token is empty in request_data');
        }
        $result = HttpHelper::request('POST', $this->config->getGateway(), $header, $request_data);
        error_log('POST Response: ' . $result);
        $result = json_decode($result, true);
        if ($result['code'] == '0000') {
            return new GatewayResponse($result['data']);
        }
        throw new Exception('Create order error: code:' . $result['code'] . ', message:' . ($result['describe'] ?? 'Unknown error'));
    }

    private function update_payment_with_result($payment_id, $result) {
        edd_update_payment_meta($payment_id, '_alsotrans_transaction_id', $result->id);
        edd_update_payment_meta($payment_id, '_alsotrans_request_id', $result->request_id);

        $status = $this->map_gateway_status_to_edd($result->status);

        if ($result->status === 'paid') {
            edd_update_payment_status($payment_id, 'complete');
            edd_insert_payment_note($payment_id, sprintf(__('Alsotrans payment completed. Transaction ID: %s', 'alsotrans-edd'), $result->id));
        } elseif ($result->status === 'pending' || $result->status === 'unpaid') {
            edd_update_payment_status($payment_id, 'pending');
            edd_insert_payment_note($payment_id, sprintf(__('Alsotrans payment pending. Redirect URL: %s', 'alsotrans-edd'), $result->redirect_url ?? 'N/A'));
        } else {
            edd_update_payment_status($payment_id, 'failed');
            $error_msg = $result->getFormattedErrorMessage() ?: 'Payment failed';
            edd_insert_payment_note($payment_id, sprintf(__('Alsotrans payment failed: %s', 'alsotrans-edd'), $error_msg));
        }
    }

    private function map_gateway_status_to_edd($status) {
        $status_map = array(
            'paid' => 'complete',
            'pending' => 'pending',
            'unpaid' => 'pending',
            'failed' => 'failed',
            'canceled' => 'cancelled'
        );
        return $status_map[$status] ?? 'pending';
    }

    public function process_ipn() {
        $request_data = file_get_contents('php://input');
        $request_data = json_decode($request_data, true);

        if (!$request_data) {
            $request_data = $_POST;
        }

        try {
            $callback = new CallbackData($request_data);
            $this->verify_callback_sign($callback);

            $payment_id = $callback->order_id;
            if (strpos($payment_id, '_') !== false) {
                $order_arr = explode('_', $payment_id);
                $payment_id = $order_arr[0];
            }

            $payment = edd_get_payment($payment_id);
            if (!$payment) {
                throw new Exception('Payment not found: ' . $payment_id);
            }

            $status = $this->map_gateway_status_to_edd($callback->status);
            edd_update_payment_status($payment_id, $status);

            if (!empty($callback->id)) {
                edd_update_payment_meta($payment_id, '_alsotrans_ipn_transaction_id', $callback->id);
            }

            edd_insert_payment_note($payment_id, sprintf(__('Alsotrans IPN callback received. Status: %s', 'alsotrans-edd'), $callback->status));

            echo '[success]';
            exit;

        } catch (Exception $e) {
            $this->record_log('IPN Error', $e->getMessage());
            echo 'Error: ' . $e->getMessage();
            exit;
        }
    }

    private function verify_callback_sign($callback_data) {
        $merchant_id = $this->get_merchant_id();
        $md5key = $this->get_md5_key();

        if (empty($md5key) || empty($merchant_id)) {
            throw new Exception('Callback verify error: merchant id or md5key empty!');
        }

        $str = $callback_data->id . $callback_data->status . $callback_data->amount_value . $md5key . $merchant_id . $callback_data->request_id;
        $encrypt = Utils::sha256Encrypt($str);

        if ($encrypt !== $callback_data->sign_verify) {
            throw new Exception('Callback verify error: encrypt not equals to sign!');
        }

        return true;
    }

    public function record_log($message, $data = '') {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/alsotrans-edd-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/' . date('Y-m') . '.log';
        $timestamp = date('Y-m-d H:i:s');

        if (is_array($data)) {
            $log_entry = "$timestamp - $message :: " . var_export($data, true) . PHP_EOL;
        } elseif ($data) {
            $log_entry = "$timestamp - $message :: $data" . PHP_EOL;
        } else {
            $log_entry = "$timestamp - $message" . PHP_EOL;
        }

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    private function normalizeCountry($country) {
        $country = trim($country);
        if (empty($country)) {
            return '';
        }

        $country_upper = strtoupper($country);
        if (strlen($country_upper) === 2) {
            return $country_upper;
        }

        $country_map = array(
            'CHINA' => 'CN',
            'UNITED STATES' => 'US',
            'UNITED KINGDOM' => 'GB',
            'ENGLAND' => 'GB',
            'GERMANY' => 'DE',
            'FRANCE' => 'FR',
            'CANADA' => 'CA',
            'AUSTRALIA' => 'AU',
            'JAPAN' => 'JP',
            'SINGAPORE' => 'SG',
            'HONG KONG' => 'HK'
        );

        $country_upper = preg_replace('/\s+/', ' ', $country_upper);
        if (isset($country_map[$country_upper])) {
            return $country_map[$country_upper];
        }

        return $country;
    }

    private function get_customer_ip() {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $online_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $online_ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $online_ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $online_ip = $_SERVER['REMOTE_ADDR'];
        }
        $ips = explode(',', $online_ip);
        $ip = trim($ips[0]);
        if (substr($ip, 0, 7) == '::ffff:') {
            $ip = substr($ip, 7);
        }
        return $ip;
    }
}

function alsotrans_edd_process_payment($purchase_data) {
    try {
        if (!wp_verify_nonce($purchase_data['gateway_nonce'], 'edd-gateway')) {
            throw new Exception(__('Security verification failed', 'alsotrans-edd'));
        }

        $alsotrans_gateway = new Alsotrans_EDD_Gateway();

        $result = $alsotrans_gateway->create_payment($purchase_data);
        $payment_id = $result['payment_id'];
        $gateway_result = $result['result'];

        if ($gateway_result->status === 'paid') {
            edd_send_to_success_page(array('purchase_id' => $payment_id));
        } elseif ($gateway_result->status === 'pending' || $gateway_result->status === 'unpaid') {
            if (!empty($gateway_result->redirect_url)) {
                wp_redirect($gateway_result->redirect_url);
                exit;
            }
            edd_send_to_success_page(array('purchase_id' => $payment_id));
        } else {
            edd_set_error('alsotrans_payment_error', $gateway_result->getFormattedErrorMessage() ?: __('Payment failed', 'alsotrans-edd'));
            edd_send_back_to_checkout();
        }

    } catch (Exception $e) {
        edd_set_error('alsotrans_payment_error', $e->getMessage());
        edd_record_gateway_error(__('Alsotrans Payment Error', 'alsotrans-edd'), $e->getMessage());
        edd_send_back_to_checkout();
    }
}
