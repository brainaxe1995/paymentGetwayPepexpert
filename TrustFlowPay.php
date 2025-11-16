<?php
/**
 * Plugin Name: TrustFlowPay Payment Gateway
 * Plugin URI: https://trustflowpay.com
 * Description: WooCommerce payment gateway for TrustFlowPay using PGH Checkout API
 * Version: 1.0.0
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

define( 'WC_TRUSTFLOWPAY_VERSION', '1.0.0' );
define( 'WC_TRUSTFLOWPAY_PLUGIN_FILE', __FILE__ );
define( 'WC_TRUSTFLOWPAY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_TRUSTFLOWPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

add_action( 'plugins_loaded', 'wc_trustflowpay_init', 11 );

function wc_trustflowpay_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'wc_trustflowpay_missing_wc_notice' );
        return;
    }

    require_once WC_TRUSTFLOWPAY_PLUGIN_PATH . 'includes/class-wc-gateway-trustflowpay.php';

    add_filter( 'woocommerce_payment_gateways', 'wc_trustflowpay_add_gateway' );
}

function wc_trustflowpay_add_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_TrustFlowPay';
    return $gateways;
}

function wc_trustflowpay_missing_wc_notice() {
    echo '<div class="error"><p><strong>TrustFlowPay Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
}

function wc_trustflowpay_log( $message, $level = 'info' ) {
    $gateways = WC()->payment_gateways->payment_gateways();
    if ( isset( $gateways['trustflowpay'] ) && 'yes' === $gateways['trustflowpay']->get_option( 'debug' ) ) {
        $logger = wc_get_logger();
        $logger->log( $level, $message, array( 'source' => 'trustflowpay' ) );
    }
}