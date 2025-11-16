<?php
/**
 * Plugin Name: TrustFlowPay Payment Gateway
 * Plugin URI: https://trustflowpay.com
 * Description: WooCommerce payment gateway for TrustFlowPay using PGH Checkout API with redirect and iframe modes
 * Version: 1.1.0
 * Author: TrustFlowPay
 * Author URI: https://trustflowpay.com
 * Text Domain: trustflowpay-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WC_TRUSTFLOWPAY_VERSION', '1.1.0' );
define( 'WC_TRUSTFLOWPAY_PLUGIN_FILE', __FILE__ );
define( 'WC_TRUSTFLOWPAY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_TRUSTFLOWPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Declare HPOS (High-Performance Order Storage) compatibility
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

add_action( 'plugins_loaded', 'wc_trustflowpay_init', 11 );

function wc_trustflowpay_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_trustflowpay_missing_wc_notice' );
        return;
    }

    require_once WC_TRUSTFLOWPAY_PLUGIN_PATH . 'includes/class-wc-gateway-trustflowpay.php';

    add_filter( 'woocommerce_payment_gateways', 'wc_trustflowpay_add_gateway' );

    // Register API endpoints
    add_action( 'woocommerce_api_wc_gateway_trustflowpay_redirect', 'wc_trustflowpay_redirect_page' );
}

function wc_trustflowpay_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_TrustFlowPay';
    return $gateways;
}

function wc_trustflowpay_missing_wc_notice() {
    echo '<div class="error"><p><strong>TrustFlowPay Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
}

/**
 * Logging function
 */
function wc_trustflowpay_log( $message, $level = 'info' ) {
    $gateways = WC()->payment_gateways->payment_gateways();
    if ( isset( $gateways['trustflowpay'] ) && 'yes' === $gateways['trustflowpay']->get_option( 'debug' ) ) {
        $logger = wc_get_logger();
        $logger->log( $level, $message, array( 'source' => 'trustflowpay' ) );
    }
}

/**
 * Redirect/Iframe payment page handler
 */
function wc_trustflowpay_redirect_page() {
    $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

    if ( ! $order_id ) {
        wp_die( 'Invalid order ID', 'TrustFlowPay Payment', array( 'response' => 400 ) );
    }

    $order = wc_get_order( $order_id );

    if ( ! $order ) {
        wp_die( 'Order not found', 'TrustFlowPay Payment', array( 'response' => 404 ) );
    }

    // Get gateway instance
    $gateways = WC()->payment_gateways->payment_gateways();
    $gateway = isset( $gateways['trustflowpay'] ) ? $gateways['trustflowpay'] : null;

    if ( ! $gateway ) {
        wp_die( 'Payment gateway not found', 'TrustFlowPay Payment', array( 'response' => 500 ) );
    }

    // Get stored request params
    $request_params_json = $order->get_meta( '_trustflowpay_request_params', true );

    if ( ! $request_params_json ) {
        wp_die( 'Payment parameters not found', 'TrustFlowPay Payment', array( 'response' => 500 ) );
    }

    $request_params = json_decode( $request_params_json, true );
    $base_url = $gateway->get_base_url();
    $checkout_mode = $gateway->get_option( 'checkout_display_mode', 'redirect' );

    wc_trustflowpay_log( 'Rendering payment page for Order #' . $order_id . ' in ' . $checkout_mode . ' mode' );

    $payment_endpoint = rtrim( $base_url, '/' ) . '/pgui/jsp/paymentrequest';

    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php esc_html_e( 'Processing Payment', 'trustflowpay-gateway' ); ?> - <?php bloginfo( 'name' ); ?></title>
        <?php if ( $checkout_mode === 'iframe' ) : ?>
            <link rel="stylesheet" href="<?php echo esc_url( rtrim( $base_url, '/' ) . '/pgui/checkoutlibrary/checkout.min.css' ); ?>">
            <script src="<?php echo esc_url( rtrim( $base_url, '/' ) . '/pgui/checkoutlibrary/checkout.min.js' ); ?>"></script>
        <?php endif; ?>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f7f7f7;
                margin: 0;
                padding: 20px;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
            }
            .payment-container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                max-width: 800px;
                width: 100%;
                text-align: center;
            }
            .payment-container h2 {
                margin-top: 0;
                color: #333;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .redirect-message {
                color: #666;
                margin: 20px 0;
            }
        </style>
    </head>
    <body>
        <div class="payment-container">
            <?php if ( $checkout_mode === 'iframe' ) : ?>
                <h2><?php esc_html_e( 'Enter Your Card Details', 'trustflowpay-gateway' ); ?></h2>
                <p class="redirect-message"><?php esc_html_e( 'Please complete your payment below. You will be returned to the store after payment.', 'trustflowpay-gateway' ); ?></p>

                <!-- TrustFlowPay PGH Checkout form - must target checkout-iframe as per integration docs -->
                <form id="trustflowpay-payment-form" method="post" action="<?php echo esc_url( $payment_endpoint ); ?>" target="checkout-iframe">
                    <?php foreach ( $request_params as $key => $value ) : ?>
                        <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
                    <?php endforeach; ?>
                </form>

                <!-- Iframe must have ID and name "checkout-iframe" as per TrustFlowPay integration requirements -->
                <iframe name="checkout-iframe" id="checkout-iframe" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px; margin-top: 20px;"></iframe>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var form = document.getElementById('trustflowpay-payment-form');

                        // Check if TrustFlowPay's checkoutSubmitHandler is available
                        if (typeof checkoutSubmitHandler === 'function') {
                            try {
                                // Use TrustFlowPay's submit handler if available
                                checkoutSubmitHandler(form);
                            } catch(e) {
                                console.warn('TrustFlowPay checkoutSubmitHandler error, using fallback:', e);
                                form.submit();
                            }
                        } else {
                            // Fallback to standard submit if TrustFlowPay JS not loaded
                            console.info('TrustFlowPay checkout.min.js not loaded, using standard form submit');
                            form.submit();
                        }
                    });
                </script>
            <?php else : ?>
                <h2><?php esc_html_e( 'Redirecting to Payment Gateway', 'trustflowpay-gateway' ); ?></h2>
                <div class="spinner"></div>
                <p class="redirect-message"><?php esc_html_e( 'Please wait while we redirect you to complete your payment...', 'trustflowpay-gateway' ); ?></p>

                <form id="trustflowpay-payment-form" method="post" action="<?php echo esc_url( $payment_endpoint ); ?>">
                    <?php foreach ( $request_params as $key => $value ) : ?>
                        <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
                    <?php endforeach; ?>
                </form>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Auto-submit the form for redirect
                        document.getElementById('trustflowpay-payment-form').submit();
                    });
                </script>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}
