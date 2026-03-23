<?php
/**
 * Plugin Name: Alsotrans Payment Gateway for Easy Digital Downloads
 * Plugin URI: https://www.alsotransglobal.com/
 * Description: Alsotrans Payment Gateway for Easy Digital Downloads - Accept credit card payments via iframe integration.
 * Version: 1.1.5
 * Author: Alsotrans
 * Author URI: https://www.alsotransglobal.com/
 * Text Domain: alsotrans-edd
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ALSOTRANS_EDD_VERSION', '1.1.7');
define('ALSOTRANS_EDD_PLUGIN_FILE', __FILE__);
define('ALSOTRANS_EDD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ALSOTRANS_EDD_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!defined('ATPENV')) {
    define('ATPENV', 'live');
}

require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Config/ATPConfig.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Utils/Utils.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Utils/HttpHelper.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/GatewayRequest.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/GatewayResponse.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Model/CallbackData.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Payment/Iframe.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'includes/Callback/CallbackService.php';
require_once ALSOTRANS_EDD_PLUGIN_DIR . 'class-alsotrans-edd-gateway.php';

add_action('plugins_loaded', 'alsotrans_edd_load_plugin', 0);

function alsotrans_edd_load_plugin() {
    if (!class_exists('Easy_Digital_Downloads')) {
        add_action('admin_notices', 'alsotrans_edd_missing_edd_notice');
        return;
    }

    add_filter('edd_payment_gateways', 'alsotrans_edd_register_gateway');
    add_action('edd_gateway_alsotrans_iframe', 'alsotrans_edd_process_payment');
    add_action('edd_gateway_alsotrans_credit_card', 'alsotrans_edd_process_payment');

    add_filter('edd_settings_gateways', 'alsotrans_edd_add_settings');
    add_filter('edd_settings_sections_gateways', 'alsotrans_edd_add_settings_section');

    add_action('init', 'alsotrans_edd_load_textdomain');

    add_action('wp_enqueue_scripts', 'alsotrans_edd_enqueue_scripts');

    add_action('edd_alsotrans_ipn', 'alsotrans_edd_ipn_handler');

    add_action('edd_insert_payment', 'alsotrans_edd_record_logs', 10, 1);
}

function alsotrans_edd_missing_edd_notice() {
    echo '<div class="error"><p>';
    echo sprintf(
        __('Alsotrans Payment Gateway requires Easy Digital Downloads to be installed and active. <a href="%s">Install EDD Now</a>', 'alsotrans-edd'),
        admin_url('plugin-install.php?tab=search&type=term&s=easy+digital+downloads')
    );
    echo '</p></div>';
}

function alsotrans_edd_register_gateway($gateways) {
    $gateways['alsotrans_iframe'] = array(
        'admin_label' => __('Alsotrans Payment Gateway', 'alsotrans-edd'),
        'checkout_label' => __('Credit Card (Alsotrans)', 'alsotrans-edd'),
        'supports' => array(
            'purchase_details'
        )
    );
    return $gateways;
}

function alsotrans_edd_add_settings_section($sections) {
    $sections['alsotrans'] = __('Alsotrans', 'alsotrans-edd');
    return $sections;
}

function alsotrans_edd_add_settings($settings) {
    $alsotrans_settings = array(
        'alsotrans' => array(
            array(
                'id' => 'alsotrans_settings_header',
                'name' => '<strong>' . __('Alsotrans Payment Gateway Settings', 'alsotrans-edd') . '</strong>',
                'desc' => __('Configure your Alsotrans payment gateway credentials and options.', 'alsotrans-edd'),
                'type' => 'header'
            ),
            array(
                'id' => 'alsotrans_enabled',
                'name' => __('Enable/Disable', 'alsotrans-edd'),
                'desc' => __('Enable Alsotrans payment gateway', 'alsotrans-edd'),
                'type' => 'checkbox',
                'std' => 0
            ),
            array(
                'id' => 'alsotrans_title',
                'name' => __('Title', 'alsotrans-edd'),
                'desc' => __('The payment method title users see during checkout.', 'alsotrans-edd'),
                'type' => 'text',
                'std' => __('Credit Card', 'alsotrans-edd')
            ),
            array(
                'id' => 'alsotrans_description',
                'name' => __('Description', 'alsotrans-edd'),
                'desc' => __('The payment method description users see during checkout.', 'alsotrans-edd'),
                'type' => 'textarea',
                'std' => __('Pay securely with your credit card via Alsotrans.', 'alsotrans-edd')
            ),
            array(
                'id' => 'alsotrans_merchant_id',
                'name' => __('Merchant ID', 'alsotrans-edd'),
                'desc' => __('Your Alsotrans Merchant ID.', 'alsotrans-edd'),
                'type' => 'text'
            ),
            array(
                'id' => 'alsotrans_private_key',
                'name' => __('Private Key', 'alsotrans-edd'),
                'desc' => __('Your Alsotrans Private Key for API authentication.', 'alsotrans-edd'),
                'type' => 'password'
            ),
            array(
                'id' => 'alsotrans_md5_key',
                'name' => __('MD5 Key', 'alsotrans-edd'),
                'desc' => __('Your Alsotrans MD5 Key for callback verification.', 'alsotrans-edd'),
                'type' => 'password'
            ),
            array(
                'id' => 'alsotrans_environment',
                'name' => __('Environment', 'alsotrans-edd'),
                'desc' => __('Select sandbox for testing, live for production.', 'alsotrans-edd'),
                'type' => 'select',
                'options' => array(
                    'live' => __('Live', 'alsotrans-edd'),
                    'sandbox' => __('Sandbox', 'alsotrans-edd')
                ),
                'std' => 'live'
            ),
            array(
                'id' => 'alsotrans_site_domain',
                'name' => __('Site Domain', 'alsotrans-edd'),
                'desc' => __('Your website domain for API verification (e.g., example.com).', 'alsotrans-edd'),
                'type' => 'text'
            ),
            array(
                'id' => 'alsotrans_show_card_icons',
                'name' => __('Show Card Icons', 'alsotrans-edd'),
                'desc' => __('Display accepted card type icons on the checkout form.', 'alsotrans-edd'),
                'type' => 'checkbox',
                'std' => 0
            )
        )
    );

    return array_merge($settings, $alsotrans_settings);
}

function alsotrans_edd_load_textdomain() {
    load_plugin_textdomain(
        'alsotrans-edd',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
}

function alsotrans_edd_enqueue_scripts() {
    if (!edd_is_checkout()) {
        return;
    }

    $alsotrans_gateway = new Alsotrans_EDD_Gateway();
    if (!$alsotrans_gateway->is_available()) {
        return;
    }

    $env = $alsotrans_gateway->get_environment();
    $base_url = $env === 'sandbox' ? 'https://stage-api.alsotransglobal.com' : 'https://api.alsotransglobal.com';
    $js_base_url = $env === 'sandbox' ? 'https://stage-icashier.alsotransglobal.com' : 'https://icashier.alsotransglobal.com';

    wp_enqueue_script(
        'alsotrans_edd_js',
        $js_base_url . '/index.js',
        array('jquery'),
        ALSOTRANS_EDD_VERSION,
        true
    );

    wp_enqueue_script(
        'alsotrans_edd_checkout_js',
        ALSOTRANS_EDD_PLUGIN_URL . 'assets/js/alsotrans_edd.js',
        array('jquery', 'alsotrans_edd_js'),
        ALSOTRANS_EDD_VERSION,
        true
    );

    try {
        $iframe_token = $alsotrans_gateway->get_iframe_token();
    } catch (Exception $e) {
        $iframe_token = '';
    }

    wp_localize_script('alsotrans_edd_checkout_js', 'alsotransEddParams', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'iframeToken' => $iframe_token,
        'jsBaseUrl' => $js_base_url,
        'currency' => edd_get_currency(),
        'gatewayId' => 'alsotrans_iframe'
    ));
}

function alsotrans_edd_ipn_handler() {
    $alsotrans_gateway = new Alsotrans_EDD_Gateway();
    $alsotrans_gateway->process_ipn();
}

add_action('edd_alsotrans_iframe_cc_form', 'alsotrans_edd_payment_fields');

function alsotrans_edd_payment_fields() {
    $alsotrans_gateway = new Alsotrans_EDD_Gateway();
    if (!$alsotrans_gateway->is_available()) {
        return;
    }

    try {
        $token = $alsotrans_gateway->get_iframe_token();
    } catch (Exception $e) {
        echo '<p class="edd-error">' . __('Error loading payment form: ', 'alsotrans-edd') . esc_html($e->getMessage()) . '</p>';
        return;
    }
    $acceptedCards = $alsotrans_gateway->get_card_types();
    // Alsotrans only supports Visa for this merchant
    if (empty($acceptedCards) || !is_array($acceptedCards)) {
        $acceptedCards = array('visa');
    }

    ?>
    <input type="hidden" name="alsotrans_pay" value="card">
    <div id="llpay-card-element"></div>
    <input id="alsotrans_token" name="alsotrans_token" type="hidden" value="">
    <script>
    (function() {
        function initATPCard() {
            if (typeof ATP === 'undefined') {
                console.warn('ATP library not loaded yet, retrying...');
                setTimeout(initATPCard, 100);
                return;
            }

            try {
                var elements = ATP.elements();
                var card = elements.create("card", {
                    token: "<?php echo esc_js($token); ?>",
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
                    language: "en",
                    acceptedCards: <?php echo wp_json_encode($acceptedCards); ?>,
                });
                card.mount("#llpay-card-element").catch((err) => {
                    console.error('Card mount error:', err);
                });
            } catch (error) {
                console.error('ATP card initialization error:', error);
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initATPCard);
        } else {
            initATPCard();
        }
    })();
    </script>
    <input id="alsotrans_sid" name="alsotrans_sid" type="hidden" value="">
    <script>
    if (typeof getBrowserInfoSid === 'function') {
        getBrowserInfoSid().then((res) => {
            console.log(res);
            if (res && res.sid) {
                document.getElementById("alsotrans_sid").value = res.sid;
            }
        }).catch((err) => {
            console.log(err);
        });
    }
    </script>
    <?php
}

function alsotrans_edd_record_logs($payment_id) {
    $payment = edd_get_payment($payment_id);
    if ($payment && $payment->gateway === 'alsotrans_iframe') {
        $alsotrans_gateway = new Alsotrans_EDD_Gateway();
        $alsotrans_gateway->record_log($payment_id, 'Payment created with status: ' . $payment->status);
    }
}
