<?php
/*
 * Plugin Name: Alsotrans Payment Gateway
 * Description: Alsotrans Payment Gateway for woocommerce.
 * Author: Alsotrans
 * Author URI: https://www.alsotransglobal.com/
 * Version: 3.0.0
 */

/**
 * 定义常量，
 * WC_ALSOTRANS_PLUGIN_FILE_PATH 插件根路径，
 * WC_ALSOTRANS_ASSETS 公共资源根路径，
 * WC_ALSOTRANS_PLUGIN_NAME 插件名：woo-cartadicreditopay-payment/cartadicreditopay-payments.php
 */
define( 'WC_ALSOTRANS_PLUGIN_FILE_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_ALSOTRANS_ASSETS', plugin_dir_url( __FILE__ ) . 'assets/' );
define( 'WC_ALSOTRANS_PLUGIN_NAME', plugin_basename( __FILE__ ) );

//const ATPENV = 'sandbox';
//const ATPENV = 'live';

if(!defined('ATPENV')){
	define('ATPENV','live');
}

require __DIR__.'/iframe/IframeClient.php';

//注册支付网关
add_filter( 'woocommerce_payment_gateways', 'alsotrans_iframe_gateway' );
function alsotrans_iframe_gateway( $gateways ) {
    $gateways[] = 'AlsotransIframeGateway'; // your class name is here
    return $gateways;
}

//插件初始配置方法
add_action( 'plugins_loaded', 'alsotrans_iframe_gateway_class' );
function alsotrans_iframe_gateway_class(){
    if(!class_exists('WC_Payment_Gateway')){
        echo '<div class="notice notice-error"><p>';
        echo 'Before using Alsotrans payment plugin , Please install WooCommerce plugin first!';
        echo '</p></div>';
        return;
    }

    class AlsotransIframeGateway extends WC_Payment_Gateway
    {
        public ?string $callbackUrl;
        public IframeClient $iframe;

        public function __construct()
        {
            $this->id = 'alsotrans_iframe'; // payment gateway plugin ID
            $this->has_fields = true;
            $this->method_title = 'Alsotrans Gateway';
            $this->method_description = 'Alsotrans Payment Gateway'; // will be displayed on the options page
            if(empty($this->get_option('callback_url'))){
                $this->callbackUrl = home_url('/?wc-api=alsotrans_callback');
            }else{
                $this->callbackUrl = $this->get_option('callback_url');
            }
            add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->init_form_fields();
            $this->init_settings();
            $config = [
                'X-MERCHANT-ID'=>$this->get_option( 'merchant_id' ),
                'X-PRIVATE-KEY'=>$this->get_option( 'private_key' ),
                'X-MD5-KEY'=>$this->get_option( 'md5key' ),
                'X-ADDON-PLATFORM'=>'woocommerce',
                'X-ADDON-VERSION'=>$this->getVersion(),
            ];

            try{
	            if(ATPENV == 'sandbox' && str_contains($_SERVER['REQUEST_URI'],'section=alsotrans')) {
		            echo '<div class="notice notice-error"><p>You are using sandbox mode.</p></div>';
	            }
	            $this->iframe = new IframeClient($config,ATPENV);
            }catch (Exception $e){
                echo '<div class="notice notice-error"><p>';
                echo $e->getMessage();
                echo '</p></div>';
            }

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            //我们需要自定义JavaScript以获得令牌
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        }

        public function init_form_fields(){
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'alsotrans_gateway'),
                    'type' => 'checkbox',
                    'label' => __('Enable Payment Gateway, it will be visible on checkout page.', 'alsotrans_gateway'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'alsotrans_gateway'),
                    'type' => 'text',
                    'description' => __('The title will be shown to the user during checkout page.', 'alsotrans_gateway'),
                    'default' => __('Credit Card', 'alsotrans_gateway')
                ),
                'merchant_id' => array(
                    'title' => __('Merchant ID', 'alsotrans_gateway'),
                    'type' => 'text',
                ),
                'md5key' => array(
                    'title' => __('Md5 Key', 'alsotrans_gateway'),
                    'type' => 'text',
                ),
                'apikey' => array(
                    'title' => __('Api Key', 'alsotrans_gateway'),
                    'type' => 'text',
                ),
                'private_key' => array(
                    'title' => __('Private Key', 'alsotrans_gateway'),
                    'type' => 'textarea',
                    'css' => 'width: 400px;',
                ),
                'wintopay_cardtypes' => array(
                    'title'    => __( 'Accepted Cards', 'woocommerce' ),
                    'type'     => 'multiselect',
                    'class'    => 'chosen_select',
                    'css'      => 'width: 350px;',
                    'desc_tip' => __( 'Select the card types to accept.', 'woocommerce' ),
                    'options'  => array(
                        'visa'             => 'Visa',
                        'mastercard'       => 'MasterCard',
                        'jcb'		       => 'JCB',
                        'amex' 		       => 'American Express',
                        'dn'		       => 'Diners club',
                        'dc'		       => 'Discover',
                    ),
                    'default' => array( 'visa','mastercard', 'jcb' ),
                ),
                'callback_url' => array(
                    'title' => __('Callback url', 'alsotrans_gateway'),
                    'type' => 'text',
                    'description' => __('Callback url', 'alsotrans_gateway'),
                    'default' => __(home_url('/?wc-api=alsotrans_callback') , 'alsotrans_gateway')
                ),
                'description' => array(
                    'title' => __('Description', 'alsotrans_gateway'),
                    'type' => 'textarea',
                    'css' => 'width: 400px;',
                    'description' => __('Payment message to customer', 'alsotrans_gateway'),
                ),
            );
        }

        /*
          * 订单信息验证函数
         */
        public function validate_fields(): bool
        {
            if(empty($_GET[ 'order-pay' ])){
                if( empty( $_POST[ 'billing_first_name' ]) ) {
                    wc_add_notice(  'First name is required!', 'error' );
                    return false;
                }elseif ( empty( $_POST[ 'billing_last_name' ]) ) {
                    wc_add_notice('Last name is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'billing_country' ]) ) {
                    wc_add_notice('Country is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'billing_address_1' ]) ) {
                    wc_add_notice('Address is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'billing_postcode' ]) ) {
                    wc_add_notice('Zipcode name is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'billing_phone' ]) ) {
                    wc_add_notice('Phone is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'billing_email' ]) ) {
                    wc_add_notice('Email is required!', 'error');
                    return false;
                }elseif ( empty( $_POST[ 'alsotrans_token' ]) ) {
                    wc_add_notice('Card token is required!', 'error');
                    return false;
                }
            }
            return true;
        }
        function record_logs($message,$data='')
        {
            $file = getcwd();
            $file_name = $file.'/'.date('Y-m',time()).'.log';

            if(is_array($data)){
                file_put_contents($file_name,date('Y-m-d H:i:s',time()).' - '.$message.' :: '.var_export($data,true).PHP_EOL,FILE_APPEND);
            }elseif($data){
                file_put_contents($file_name,date('Y-m-d H:i:s',time()).' - '.$message.' :: '.$data.PHP_EOL,FILE_APPEND);
            }else{
                file_put_contents($file_name,date('Y-m-d H:i:s',time()).' - '.$message.PHP_EOL,FILE_APPEND);
            }
        }
        public function process_payment( $order_id ): array
        {

            $order = wc_get_order($order_id);

            //支付失败，修改订单号已支持重复支付
            if($order->get_status() == 'failed'){
                $endline = date('is',time());
                $order_id = $order_id.'_'.$endline;
            }

            $currency = get_woocommerce_currency();
            $amount = $order->get_total();
            $billing_first_name = $order->get_billing_first_name();
            $billing_last_name = $order->get_billing_last_name();
            $billing_company = $order->get_billing_company();
            $billing_country = $order->get_billing_country();
            $billing_address_1 = $order->get_billing_address_1();
            $billing_address_2 = $order->get_billing_address_2();
            $billing_city = $order->get_billing_city();
            $billing_state = $order->get_billing_state() ? $order->get_billing_state() : $billing_city;
            $billing_postcode = $order->get_billing_postcode();
            $billing_phone = $order->get_billing_phone();
            $billing_email = $order->get_billing_email();
            $shipping_first_name = $order->get_shipping_first_name() ? $order->get_shipping_first_name() : $billing_first_name;
            $shipping_last_name = $order->get_shipping_last_name() ? $order->get_shipping_last_name() : $billing_last_name;
            $shipping_company = $order->get_shipping_company() ? $order->get_shipping_company() : $billing_company;
            $shipping_country = $order->get_shipping_country() ? $order->get_shipping_country() : $billing_country;
            $shipping_address_1 = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : $billing_address_1;
            $shipping_address_2 = $order->get_shipping_address_2() ? $order->get_shipping_address_2() : $billing_address_2;
            $shipping_city = $order->get_shipping_city() ? $order->get_shipping_city() : $billing_city;
            $shipping_state = $order->get_shipping_state() ? $order->get_shipping_state() : $billing_state;
            $shipping_postcode = $order->get_shipping_postcode() ? $order->get_shipping_postcode() : $billing_postcode;

            $cardToken = $_REQUEST['alsotrans_token'] ?? '';
			$sid = $_REQUEST['alsotrans_sid'] ?? '';

            $items = $order->get_items();
            $products = $this->formattedProducts($items,$currency);

            $billingDetails = [
                'email' => $billing_email,
                'first_name' => $billing_first_name,
                'last_name' => $billing_last_name,
                'address' => $billing_address_1.','.$billing_address_2,
                'city' => $billing_city,
                'state' => $billing_state,
                'country' => $billing_country,
                'postal_code' => $billing_postcode,
                'phone' => $billing_phone,
            ];

            $shippingDetails = [
                'email' => $billing_email,
                'first_name' => $shipping_first_name,
                'last_name' => $shipping_last_name,
                'address' => $shipping_address_1.','.$shipping_address_2,
                'city' => $shipping_city,
                'state' => $shipping_state,
                'country' => $shipping_country,
                'postal_code' => $shipping_postcode,
                'phone' => $billing_phone,
            ];

            //请求参数的处理
            $requestData = array(
                'model' => 'EMBED',//内嵌iframe模式
                'amount'=> $amount,
                'currency'=> $currency,
                'merchant_reference' => $order_id,
                'customer_email' => $billing_email,
                'payment_method' => 'card',
                'payment_information' => [
                    'card_token'=>$cardToken,
                    'holder_name'=>$billing_first_name.' '.$billing_last_name,
                ],
                'products' => $products,
                'billing_details' => $billingDetails,
                'shipping_details' => $shippingDetails,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'redirect_url' =>$this->callbackUrl,
				'sid'=> $sid,
                'ip'=>$this->iframe->config->getCustomerIp(),
                'metadata' => '',
            );

            try {
                $result = $this->iframe->iframe->create($requestData);
                $status = $result->status;
                $this->updateOrderStatus($result->status,'RESPONSE',$order);
                if($status == 'paid'){
                    $redirectUrl = $this->get_return_url( $order ).'&status=paid';
                }elseif($status == 'pending' || $status == 'unpaid'){
                    if(!empty($result->redirect_url)){
                        $redirectUrl = $result->redirect_url;
                    }else{
                        $redirectUrl = $this->get_return_url( $order ).'&status=pending';
                    }
                }elseif($status == 'failed' || $status == 'canceled'){
                    $redirectUrl = esc_url(wc_get_checkout_url());
                    wc_add_notice($result->getFormattedErrorMessage(), 'error');
                }
                return array(
                    'result' => 'success',
                    'redirect' => $redirectUrl,
                );
            }catch (Exception $e){
                $redirectUrl = esc_url(wc_get_checkout_url());
                wc_add_notice($e->getMessage(), 'error');
                return array(
                    'result' => 'success',
                    'redirect' => $redirectUrl,
                );
            }
        }

        /**
         * 更新订单状态
         * @param $status
         * @param $type
         * @param WC_Order $order
         * @return void
         */
        public function updateOrderStatus($status,$type,WC_Order $order): void
        {
            $company = 'ALSOTRANS';
            $orderStatus = $order->get_status();
            if($this->canUpdateOrderStatus($status,$orderStatus)){
                if($status == 'processing'){
                    $order->payment_complete($order->get_id());
                }else{
                    $order->update_status($this->transferAlsotransStatusToWoocommerceStatus($status),$company.','.$type.',UPDATE.');
                }
            }else{
                $order->add_order_note($company.','.$type.',RECORD : order status - '.$orderStatus.' ,new status - '.$this->transferAlsotransStatusToWoocommerceStatus($status).'.');
            }
        }

        /**
         * 检查订单状态是否可以更新
         * @param $status
         * @param $orderStatus
         * @return bool
         */
        private function canUpdateOrderStatus($status,$orderStatus): bool
        {
            $canUpdateStatus = ['pending','failed','cancelled'];
            if(in_array($orderStatus,$canUpdateStatus)){
                if($this->transferAlsotransStatusToWoocommerceStatus($status) == $orderStatus){
                    return false;
                }
                return true;
            }
            return false;
        }

        /**
         * 将alsotrans的订单状态匹配为woocommerce的订单状态
         * @param $status
         * @return string
         */
        private function transferAlsotransStatusToWoocommerceStatus($status): string
        {
            $statusArr = [
                'unpaid'=>'pending',
                'pending'=>'pending',
                'paid'=>'processing',
                'failed'=>'failed',
                'canceled'=>'cancelled',
            ];
            if(array_key_exists($status,$statusArr)){
                return $statusArr[$status];
            }else{
                return $statusArr['canceled'];
            }
        }

        /**
         * 组合产品信息为网关接受的格式
         * @param $items
         * @param $currency
         * @return array
         */
        public function formattedProducts($items,$currency): array
        {
            $products = array();
            foreach ($items as $item) {
                $name = $item->get_name();
                $quantity = $item->get_quantity();
                // 获取产品对象
                $product = $item->get_product();
                // 获取产品ID
                $product_id = $product->get_id();
                $product = wc_get_product($product_id);
                $product_sku = empty($product->get_sku())?'atp-default':$product->get_sku();
                $price = $product->get_price();
                $products[] = [
                    'sku' => $product_sku,
                    'name' => $name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'currency' => $currency
                ];
            }
            return $products;
        }

        /**
         * 获取本插件的版本号
         * @return string
         */
        public function getVersion(): string
        {
            $plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);
            return $plugin_data['Version'];
        }

        /**
         * 添加额外的js文件
         * @return void
         */
        public function payment_scripts(): void
        {
            if (!is_cart() && !is_checkout() && !isset($_GET['pay_for_order'])) {
                return;
            }
            if ('no' === $this->enabled) {
                return;
            }

            try {
                $token = $this->iframe->iframe->getToken();
	            $this->update_option('iframeToken', $token);
            }catch (Exception $e){
                wc_add_notice($e->getMessage(), 'error');
            }
			
            wp_enqueue_script( 'woocommerce_alsotrans_iframe_js', plugins_url( 'assets/js/alsotrans_iframe.js', __FILE__ ), array( 'jquery' ) );
            wp_enqueue_script( 'woocommerce_alsotrans_js', $this->iframe->config->getJsUrl(), array( 'jquery' )  );
            wp_enqueue_script( 'woocommerce_alsotrans_shield', $this->iframe->config->getShieldUrl(), array( 'jquery' )  );
        }

        /**
         * 前台支付页面
         * @return void
         */
        public function payment_fields(): void
        {
            $token = $this->get_option('iframeToken');
            $form = '<input type="hidden" name="alsotrans_pay" value="card">
                     <div id="alsotrans-card-element"></div>
                     <input id="alsotrans_token" name="alsotrans_token" type="hidden" value="">';
            $form .= '<script>
    var elements = ATP.elements();
    var card = elements.create("card", {
    token: "'.$token.'", //通过开放接口API获取
    style: {
        base: {
            backgroundColor: "#fff",
            color: "#000",
            fontSize: "14px",
            floatLabelSize: "12px",
            floatLabelColor: "#333333",
            floatLabelWeight: "400",
            },
        },
    language: "default",
    });
    card.mount("#alsotrans-card-element").catch((err) => {
    console.error(err);
});
</script>';

	        $form .= '<input id="alsotrans_sid" name="alsotrans_sid" type="hidden" value="">';
	        $form .= '<script>
getBrowserInfoSid().then((res) => {
            console.log(res);
            document.getElementById("alsotrans_sid").value = res.sid;
        }).catch((err) => {
            console.log(err);
        });
</script>';

            echo $form;
        }
    }
}


//多语言翻译
function alsotrans_gateway_load_textdomain() {
    load_plugin_textdomain('alsotrans_gateway', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'alsotrans_gateway_load_textdomain');

//添加后台插件setting按钮
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'alsotrans_iframe_addon_settings_link' );
function alsotrans_iframe_addon_settings_link( $links ) {
    $settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=alsotrans_iframe">' . __( 'Settings' ) . '</a>';
    $links[] = $settings_link;
    return $links;
}

//回调处理
add_action('woocommerce_api_alsotrans_callback', 'alsotrans_callback_api' );

function alsotrans_callback_api(): void
{
    $method = $_SERVER['REQUEST_METHOD'];
    if($method == 'POST'){
        //回调
        alsotransCallback();
    }else{
        //跳转返回
        alsotransRedirect();
    }
}

//处理回调
function alsotransCallback(): void
{
    $alsotransIframeGateway = new AlsotransIframeGateway();
    $requestData = file_get_contents('php://input','r');
    try {
        if(!is_object($alsotransIframeGateway)){throw new Exception('Create AlsotransIframeClient Error');}
        $callback = $alsotransIframeGateway->iframe->callback->create($requestData);
        $order_id = $callback->order_id;

        if(strpos($order_id,'_')){
            $order_arr = explode('_',$order_id);
            $order_id = $order_arr[0];
        }

        $order = wc_get_order($order_id);
        $alsotransIframeGateway->updateOrderStatus($callback->status,'CALLBACK',$order);
        exit('[success]');
    }catch (Exception $e){
        exit($e->getMessage());
    }
}

//处理跳转返回
function alsotransRedirect(): void
{
    $alsotransIframeGateway = new AlsotransIframeGateway();
    $requestData = $_GET;
    $order = '';
    try {
        if(!is_object($alsotransIframeGateway)){throw new Exception('Create AlsotransIframeClient Error');}
        $redirect = $alsotransIframeGateway->iframe->callback->create($requestData);
        $order_id = $redirect->order_id;

        if(strpos($order_id,'_')){
            $order_arr = explode('_',$order_id);
            $order_id = $order_arr[0];
        }

        $order = wc_get_order($order_id);
        $alsotransIframeGateway->updateOrderStatus($redirect->status,'REDIRECT',$order);
        $redirectUrl = $alsotransIframeGateway->get_return_url( $order ). '&status='.$redirect->status.'&result_msg='.$redirect->fail_message;
        header('Location: '.$redirectUrl);
        exit;
    }catch (Exception $e){
        if(is_object($order)){
            $order->add_order_note('Redirect error:'.$e->getMessage());
        }
        wp_redirect(home_url(),'302');
        exit;
    }
}

//回调处理
add_action('woocommerce_api_alsotrans_info', 'alsotrans_info_api' );
function alsotrans_info_api(){
    if($_SERVER['REQUEST_METHOD'] == 'POST'){
        $requestData = file_get_contents('php://input','r');
        $requestData = json_decode($requestData,true);
        $sign = empty($requestData['sign']) ? '' : $requestData['sign'];

        $alsotransIframe = new AlsotransIframeGateway();
        $baseData = array();
        $otherData = array();
        $message = 'success';
        try {
            $baseData = $alsotransIframe->iframe->callback->getInfo($sign);

            //wp版本
            global $wp_version;
            // 获取 WooCommerce 版本
            $woocommerce_version = defined('WC_VERSION') ? WC_VERSION : get_option('woocommerce_version');
            $plugins = alsotransGetPlugins();
            $themes = alsotransGetThemes();

            $otherData = [
                'plugin_version'=>$alsotransIframe->getVersion(),
                'plugin_platform'=>'woocommerce',
                'platform_version'=>[
                    'wordpress'=>$wp_version,
                    'woocommerce'=>$woocommerce_version,
                ],
                'plugins'=>$plugins,
                'themes'=>$themes,
            ];
        }catch (Exception $e){
            $message = $e->getMessage();
        }

        $data = [
            'base_data'=>$baseData,
            'other_data'=>$otherData
        ];

        $result = [
            'code'=>'200',
            'message'=>$message,
            'data'=>$data,
        ];
        exit(json_encode($result));
    }else{
        wp_redirect(home_url(),'302');
        exit;
    }
}

function alsotransGetPlugins(){
    $plugins = get_plugins();
    if(is_array($plugins)){
        foreach($plugins as $k=>$v){
            if(is_plugin_active($k)){
                $plugins[$k]['acitve'] = 'acitve';
            }else{
                $plugins[$k]['active'] = 'inacitve';
            }
        }
    }
    return $plugins;
}

function alsotransGetThemes(){
    $themes = wp_get_themes();
    $themesArr = array();
    if(is_array($themes)){
        foreach($themes as $k=>$v){
            $themesArr[$k]['version'] = $v->get('Version');
            if(get_template() == $k){
                $themesArr[$k]['acitve'] = 'active';
            }else{
                $themesArr[$k]['acitve'] = 'inactive';
            }
        }
    }
    return $themesArr;
}

/*
 * 支付结果页面弹出弹窗显示付款结果。
 */
add_action( 'woocommerce_thankyou', 'alsotrans_order_confirmation', 10, 2 );
function alsotrans_order_confirmation( $order_id) {

    $order = wc_get_order( $order_id );
    $paymentMethod = $order->get_payment_method();
    if($paymentMethod == 'alsotrans_iframe'){
        $status = empty($_REQUEST['status'])?'':$_REQUEST['status'];
        $resultMessage = empty($_REQUEST['result_msg'])?'':$_REQUEST['result_msg'];
        wp_enqueue_script('sweetalert2',plugin_dir_url(__FILE__).'assets/js/sweetalert2.js');
        switch ($status){
            case 'paid':
                $popMessage = 'Payment Success';
                $popStatus = 'success';
                break;
            case 'pending':
            case 'unpaid':
                $popMessage = 'Payment Pending';
                $popStatus = 'success';
                $resultMessage = 'The order has been successfully submitted. Waiting payment processing and the payment result will be sent your email.';
                echo "<section class='woocommerce-wtp-display-message' id='wtp_local_pending'><h2 class='woocommerce-column__title'>Payment Pending!</h2><div class='tybox'><p>The order has been successfully submitted. Waiting payment processing and the payment result will be sent your email.</p></div></section>";
                break;
            default:
                $popMessage = 'Payment Failed';
                $popStatus = 'error';
                echo "<section class='woocommerce-wtp-display-message' id='wtp_local_failed'><h2 class='woocommerce-column__title'>Payment Failed!</h2><div class='tybox'><p>Reason:$resultMessage</p></div></section>";
        }
        if(!isset($_COOKIE['alsotrans_alert'])){
            wp_add_inline_script('sweetalert2', "jQuery(document).ready(function($) {
            Swal.fire({
                title: '$popMessage',
                text:'$resultMessage',
                icon: '$popStatus',
            });
            document.cookie = \"alsotrans_alert=true\";
        });");
        }
    }
}