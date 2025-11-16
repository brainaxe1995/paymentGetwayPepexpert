<?php
/**
 * TrustFlowPay Payment Gateway Class
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Gateway_TrustFlowPay extends WC_Payment_Gateway {

    protected $testmode;
    protected $base_url;
    protected $app_id;
    protected $secret_key;
    protected $currency_code;
    protected $order_success_status;
    protected $checkout_display_mode;

    public function __construct() {
        $this->id                 = 'trustflowpay';
        $this->method_title       = __( 'TrustFlowPay', 'trustflowpay-gateway' );
        $this->method_description = __( 'Accept card payments via TrustFlowPay PGH Checkout API (Redirect or Iframe mode)', 'trustflowpay-gateway' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled               = $this->get_option( 'enabled' );
        $this->title                 = $this->get_option( 'title' );
        $this->description           = $this->get_option( 'description' );
        $this->testmode              = 'yes' === $this->get_option( 'testmode', 'yes' );
        $this->order_success_status  = $this->get_option( 'order_success_status', 'processing' );
        $this->checkout_display_mode = $this->get_option( 'checkout_display_mode', 'redirect' );

        if ( $this->testmode ) {
            $this->base_url      = $this->get_option( 'sandbox_base_url' );
            $this->app_id        = $this->get_option( 'sandbox_app_id' );
            $this->secret_key    = $this->get_option( 'sandbox_secret_key' );
            $this->currency_code = $this->get_option( 'sandbox_currency_code' );
        } else {
            $this->base_url      = $this->get_option( 'production_base_url' );
            $this->app_id        = $this->get_option( 'production_app_id' );
            $this->secret_key    = $this->get_option( 'production_secret_key' );
            $this->currency_code = $this->get_option( 'production_currency_code' );
        }

        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_api_wc_gateway_trustflowpay_return', array( $this, 'handle_return' ) );
        add_action( 'woocommerce_api_wc_gateway_trustflowpay_callback', array( $this, 'handle_webhook' ) );
        add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_action' ), 10, 2 );
        add_action( 'admin_post_trustflowpay_check_status', array( $this, 'admin_check_status' ) );
    }

    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'trustflowpay-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable TrustFlowPay Payment Gateway', 'trustflowpay-gateway' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'trustflowpay-gateway' ),
                'default'     => __( 'Credit Card / Debit Card', 'trustflowpay-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'trustflowpay-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This controls the description which the user sees during checkout.', 'trustflowpay-gateway' ),
                'default'     => __( 'Pay securely with your credit or debit card via TrustFlowPay.', 'trustflowpay-gateway' ),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __( 'Test Mode', 'trustflowpay-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable Test Mode (Sandbox)', 'trustflowpay-gateway' ),
                'default'     => 'yes',
                'description' => __( 'Use sandbox credentials for testing. Disable for production.', 'trustflowpay-gateway' ),
            ),
            'checkout_display_mode' => array(
                'title'       => __( 'Checkout Display Mode', 'trustflowpay-gateway' ),
                'type'        => 'select',
                'description' => __( 'Choose how to display the payment form. Redirect: full-page redirect to TrustFlowPay. Iframe: embedded payment form on your site.', 'trustflowpay-gateway' ),
                'default'     => 'redirect',
                'options'     => array(
                    'redirect' => __( 'Redirect to TrustFlowPay (Hosted Page)', 'trustflowpay-gateway' ),
                    'iframe'   => __( 'Inline Iframe (Embedded Form)', 'trustflowpay-gateway' ),
                ),
                'desc_tip'    => true,
            ),
            'order_success_status' => array(
                'title'       => __( 'Order Success Status', 'trustflowpay-gateway' ),
                'type'        => 'select',
                'description' => __( 'Order status to set when payment is successful.', 'trustflowpay-gateway' ),
                'default'     => 'processing',
                'options'     => array(
                    'processing' => __( 'Processing', 'trustflowpay-gateway' ),
                    'completed'  => __( 'Completed', 'trustflowpay-gateway' ),
                ),
                'desc_tip'    => true,
            ),
            'sandbox_settings' => array(
                'title'       => __( 'Sandbox Settings', 'trustflowpay-gateway' ),
                'type'        => 'title',
                'description' => __( 'Sandbox credentials for testing (provided by TrustFlowPay).', 'trustflowpay-gateway' ),
            ),
            'sandbox_base_url' => array(
                'title'       => __( 'Sandbox Base URL', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'TrustFlowPay sandbox base URL.', 'trustflowpay-gateway' ),
                'default'     => 'https://sandbox.trustflowpay.com/',
                'desc_tip'    => true,
            ),
            'sandbox_app_id' => array(
                'title'       => __( 'Sandbox APP_ID', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Your TrustFlowPay sandbox APP_ID.', 'trustflowpay-gateway' ),
                'default'     => '1107251111162944',
                'desc_tip'    => true,
            ),
            'sandbox_secret_key' => array(
                'title'       => __( 'Sandbox Secret Key (Salt)', 'trustflowpay-gateway' ),
                'type'        => 'password',
                'description' => __( 'Your TrustFlowPay sandbox secret key for hash generation.', 'trustflowpay-gateway' ),
                'default'     => '2be3c4b527d14c12',
                'desc_tip'    => true,
            ),
            'sandbox_currency_code' => array(
                'title'       => __( 'Sandbox Currency Code', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'ISO numeric currency code (e.g., 840 for USD).', 'trustflowpay-gateway' ),
                'default'     => '840',
                'desc_tip'    => true,
            ),
            'production_settings' => array(
                'title'       => __( 'Production Settings', 'trustflowpay-gateway' ),
                'type'        => 'title',
                'description' => __( 'Production credentials (provided by TrustFlowPay).', 'trustflowpay-gateway' ),
            ),
            'production_base_url' => array(
                'title'       => __( 'Production Base URL', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'TrustFlowPay production base URL.', 'trustflowpay-gateway' ),
                'default'     => 'https://pay.trustflowpay.com/',
                'desc_tip'    => true,
            ),
            'production_app_id' => array(
                'title'       => __( 'Production APP_ID', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Your TrustFlowPay production APP_ID.', 'trustflowpay-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'production_secret_key' => array(
                'title'       => __( 'Production Secret Key (Salt)', 'trustflowpay-gateway' ),
                'type'        => 'password',
                'description' => __( 'Your TrustFlowPay production secret key for hash generation.', 'trustflowpay-gateway' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'production_currency_code' => array(
                'title'       => __( 'Production Currency Code', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'ISO numeric currency code (e.g., 840 for USD).', 'trustflowpay-gateway' ),
                'default'     => '840',
                'desc_tip'    => true,
            ),
            'webhook_settings' => array(
                'title'       => __( 'Webhook & Return URL Settings', 'trustflowpay-gateway' ),
                'type'        => 'title',
                'description' => __( 'Provide these URLs to TrustFlowPay support for configuration.', 'trustflowpay-gateway' ),
            ),
            'return_url_display' => array(
                'title'       => __( 'Return URL', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'This is your Return URL. Copy and provide to TrustFlowPay.', 'trustflowpay-gateway' ),
                'default'     => WC()->api_request_url( 'wc_gateway_trustflowpay_return' ),
                'custom_attributes' => array( 'readonly' => 'readonly' ),
                'desc_tip'    => false,
            ),
            'callback_url_display' => array(
                'title'       => __( 'Callback URL (Webhook)', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'This is your Callback/Webhook URL. Copy and provide to TrustFlowPay.', 'trustflowpay-gateway' ),
                'default'     => WC()->api_request_url( 'wc_gateway_trustflowpay_callback' ),
                'custom_attributes' => array( 'readonly' => 'readonly' ),
                'desc_tip'    => false,
            ),
            'debug' => array(
                'title'       => __( 'Debug Log', 'trustflowpay-gateway' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'trustflowpay-gateway' ),
                'default'     => 'yes',
                'description' => sprintf( __( 'Log TrustFlowPay events. You can check logs at %s.', 'trustflowpay-gateway' ), '<code>WooCommerce &gt; Status &gt; Logs</code>' ),
            ),
        );
    }

    /**
     * Process payment
     */
    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        // Generate unique internal ORDER_ID
        $internal_order_id = 'TFP-' . $order_id . '-' . time();
        $order->update_meta_data( '_trustflowpay_order_id', $internal_order_id );
        $order->update_meta_data( '_trustflowpay_app_id', $this->app_id );
        $order->save();

        // Convert amount to minor units
        $amount_minor = $this->convert_to_minor_units( $order->get_total(), $order->get_currency() );

        // Build request parameters (ONLY those we will actually send)
        $params = array(
            'APP_ID'               => $this->app_id,
            'ORDER_ID'             => $internal_order_id,
            'TXNTYPE'              => 'SALE',
            'CURRENCY_CODE'        => $this->currency_code,
            'AMOUNT'               => $amount_minor,
            'CUST_FIRST_NAME'      => $order->get_billing_first_name() ? $order->get_billing_first_name() : 'Guest',
            'CUST_LAST_NAME'       => $order->get_billing_last_name() ? $order->get_billing_last_name() : 'Customer',
            'CUST_NAME'            => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
            'CUST_STREET_ADDRESS1' => $order->get_billing_address_1() ? $order->get_billing_address_1() : 'N/A',
            'CUST_CITY'            => $order->get_billing_city() ? $order->get_billing_city() : 'N/A',
            'CUST_STATE'           => $order->get_billing_state() ? $order->get_billing_state() : 'N/A',
            'CUST_COUNTRY'         => $order->get_billing_country() ? $order->get_billing_country() : 'US',
            'CUST_ZIP'             => $order->get_billing_postcode() ? $order->get_billing_postcode() : '00000',
            'CUST_PHONE'           => $order->get_billing_phone() ? $order->get_billing_phone() : '0000000000',
            'CUST_EMAIL'           => $order->get_billing_email() ? $order->get_billing_email() : 'noreply@example.com',
            'PRODUCT_DESC'         => sprintf( 'Order #%s on %s', $order_id, get_bloginfo( 'name' ) ),
            'RETURN_URL'           => WC()->api_request_url( 'wc_gateway_trustflowpay_return' ),
        );

        // Add optional address line 2 if present
        if ( $order->get_billing_address_2() ) {
            $params['CUST_STREET_ADDRESS2'] = $order->get_billing_address_2();
        }

        // Add shipping details if available
        if ( $order->has_shipping_address() ) {
            $params['CUST_SHIP_NAME']            = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
            $params['CUST_SHIP_STREET_ADDRESS1'] = $order->get_shipping_address_1() ? $order->get_shipping_address_1() : 'N/A';
            $params['CUST_SHIP_CITY']            = $order->get_shipping_city() ? $order->get_shipping_city() : 'N/A';
            $params['CUST_SHIP_STATE']           = $order->get_shipping_state() ? $order->get_shipping_state() : 'N/A';
            $params['CUST_SHIP_COUNTRY']         = $order->get_shipping_country() ? $order->get_shipping_country() : 'US';
            $params['CUST_SHIP_ZIP']             = $order->get_shipping_postcode() ? $order->get_shipping_postcode() : '00000';

            if ( $order->get_shipping_address_2() ) {
                $params['CUST_SHIP_STREET_ADDRESS2'] = $order->get_shipping_address_2();
            }
        }

        // Generate hash for EXACTLY these parameters
        $params['HASH'] = $this->generate_hash( $params );

        // Store params for the redirect page
        $order->update_meta_data( '_trustflowpay_request_params', wp_json_encode( $params ) );
        $order->save();

        wc_trustflowpay_log( 'Payment request initiated for Order #' . $order_id . ' - ORDER_ID: ' . $internal_order_id . ' - Amount (minor units): ' . $amount_minor );

        return array(
            'result'   => 'success',
            'redirect' => add_query_arg( 'order_id', $order_id, WC()->api_request_url( 'wc_gateway_trustflowpay_redirect' ) ),
        );
    }

    /**
     * Convert amount to minor units (e.g., dollars to cents)
     */
    protected function convert_to_minor_units( $amount, $currency ) {
        // For most currencies, multiply by 100 (2 decimal places)
        // For zero-decimal currencies (e.g., JPY), multiply by 1
        $zero_decimal_currencies = array( 'JPY', 'KRW', 'VND', 'CLP', 'TWD' );

        if ( in_array( strtoupper( $currency ), $zero_decimal_currencies, true ) ) {
            return (int) round( $amount );
        }

        return (int) round( $amount * 100 );
    }

    /**
     * Generate SHA-512 hash for request/response validation
     */
    public function generate_hash( $params ) {
        // Remove HASH from params if present
        $hash_params = $params;
        unset( $hash_params['HASH'] );

        // Sort by key name ascending
        ksort( $hash_params );

        // Build param string: KEY1=VALUE1~KEY2=VALUE2~...
        $param_string = '';
        foreach ( $hash_params as $key => $value ) {
            if ( $param_string !== '' ) {
                $param_string .= '~';
            }
            $param_string .= $key . '=' . $value;
        }

        // Append secret key in lowercase (NO separator)
        $final_string = $param_string . strtolower( $this->secret_key );

        // SHA-512 and uppercase
        $hash = strtoupper( hash( 'sha512', $final_string ) );

        wc_trustflowpay_log( 'Hash generated for fields: ' . implode( ', ', array_keys( $hash_params ) ) );
        wc_trustflowpay_log( 'Hash string (without secret): ' . $param_string, 'debug' );

        return $hash;
    }

    /**
     * Validate hash from TrustFlowPay
     */
    public function validate_hash( $params, $received_hash ) {
        $calculated_hash = $this->generate_hash( $params );
        $is_valid = hash_equals( $calculated_hash, $received_hash );

        if ( ! $is_valid ) {
            wc_trustflowpay_log( 'HASH VALIDATION FAILED! Calculated: ' . substr( $calculated_hash, 0, 20 ) . '... | Received: ' . substr( $received_hash, 0, 20 ) . '...', 'error' );
        } else {
            wc_trustflowpay_log( 'Hash validation successful', 'debug' );
        }

        return $is_valid;
    }

    /**
     * Handle return from TrustFlowPay
     */
    public function handle_return() {
        wc_trustflowpay_log( '========== RETURN URL HANDLER CALLED ==========' );
        wc_trustflowpay_log( 'POST data keys: ' . implode( ', ', array_keys( $_POST ) ), 'debug' );
        wc_trustflowpay_log( 'GET data keys: ' . implode( ', ', array_keys( $_GET ) ), 'debug' );

        // TrustFlowPay sends response via POST, but fallback to GET if needed
        $params = ! empty( $_POST ) ? $_POST : $_GET;

        // Remove WordPress API routing parameter
        unset( $params['wc-api'] );

        if ( empty( $params ) ) {
            wc_trustflowpay_log( 'Return handler called with no parameters', 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        // Log received fields
        wc_trustflowpay_log( 'Return parameters received: ' . wp_json_encode( array_keys( $params ) ), 'debug' );

        $order_id       = isset( $params['ORDER_ID'] ) ? sanitize_text_field( $params['ORDER_ID'] ) : '';
        $response_code  = isset( $params['RESPONSE_CODE'] ) ? sanitize_text_field( $params['RESPONSE_CODE'] ) : '';
        $status         = isset( $params['STATUS'] ) ? sanitize_text_field( $params['STATUS'] ) : '';
        $txn_id         = isset( $params['TXN_ID'] ) ? sanitize_text_field( $params['TXN_ID'] ) : '';
        $pg_ref_num     = isset( $params['PG_REF_NUM'] ) ? sanitize_text_field( $params['PG_REF_NUM'] ) : '';
        $response_msg   = isset( $params['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $params['RESPONSE_MESSAGE'] ) : '';
        $received_hash  = isset( $params['HASH'] ) ? sanitize_text_field( $params['HASH'] ) : '';

        wc_trustflowpay_log( 'Return data - ORDER_ID: ' . $order_id . ' | RESPONSE_CODE: ' . $response_code . ' | STATUS: ' . $status );

        if ( ! $order_id ) {
            wc_trustflowpay_log( 'No ORDER_ID in return data', 'error' );
            wc_add_notice( __( 'Payment failed: Invalid response from payment gateway.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        $order = $this->get_order_by_internal_id( $order_id );

        if ( ! $order ) {
            wc_trustflowpay_log( 'Order not found for ORDER_ID: ' . $order_id, 'error' );
            wc_add_notice( __( 'Payment failed: Order not found.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        // Validate hash if present
        if ( $received_hash ) {
            if ( ! $this->validate_hash( $params, $received_hash ) ) {
                wc_trustflowpay_log( 'Hash validation failed for Order #' . $order->get_id(), 'error' );
                $order->add_order_note( __( 'TrustFlowPay payment failed: Invalid hash signature.', 'trustflowpay-gateway' ) );
                wc_add_notice( __( 'Payment verification failed. Please contact support.', 'trustflowpay-gateway' ), 'error' );
                wp_redirect( $order->get_cancel_order_url() );
                exit;
            }
        } else {
            wc_trustflowpay_log( 'No HASH in return response - proceeding without hash validation', 'warning' );
        }

        // Process the payment response
        $this->process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params );

        // Redirect based on payment result
        if ( $response_code === '000' && $status === 'Captured' ) {
            wc_trustflowpay_log( 'Payment successful, redirecting to thank you page' );
            wp_redirect( $this->get_return_url( $order ) );
        } else {
            wc_trustflowpay_log( 'Payment not successful, redirecting to order pay page' );
            wc_add_notice( __( 'Payment could not be completed. Please try again.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( $order->get_checkout_payment_url( false ) );
        }

        exit;
    }

    /**
     * Handle webhook/callback from TrustFlowPay
     */
    public function handle_webhook() {
        wc_trustflowpay_log( '========== WEBHOOK HANDLER CALLED ==========' );

        $raw_body = file_get_contents( 'php://input' );
        wc_trustflowpay_log( 'Webhook raw body: ' . substr( $raw_body, 0, 500 ), 'debug' );

        if ( empty( $raw_body ) ) {
            wc_trustflowpay_log( 'Webhook called with empty body', 'error' );
            status_header( 400 );
            exit;
        }

        $data = json_decode( $raw_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wc_trustflowpay_log( 'Webhook JSON decode error: ' . json_last_error_msg(), 'error' );
            status_header( 400 );
            exit;
        }

        // Handle both array response and object response
        $params = null;
        if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
            // Array format: [ { transaction data }, { API_RESPONSE } ]
            $params = $data[0];
            wc_trustflowpay_log( 'Webhook received array format response', 'debug' );
        } elseif ( is_array( $data ) && isset( $data['ORDER_ID'] ) ) {
            // Single object format
            $params = $data;
            wc_trustflowpay_log( 'Webhook received single object format response', 'debug' );
        } else {
            wc_trustflowpay_log( 'Webhook response format not recognized', 'error' );
            status_header( 400 );
            exit;
        }

        wc_trustflowpay_log( 'Webhook parameters received: ' . wp_json_encode( array_keys( $params ) ), 'debug' );

        $order_id       = isset( $params['ORDER_ID'] ) ? sanitize_text_field( $params['ORDER_ID'] ) : '';
        $response_code  = isset( $params['RESPONSE_CODE'] ) ? sanitize_text_field( $params['RESPONSE_CODE'] ) : '';
        $status         = isset( $params['STATUS'] ) ? sanitize_text_field( $params['STATUS'] ) : '';
        $txn_id         = isset( $params['TXN_ID'] ) ? sanitize_text_field( $params['TXN_ID'] ) : '';
        $pg_ref_num     = isset( $params['PG_REF_NUM'] ) ? sanitize_text_field( $params['PG_REF_NUM'] ) : '';
        $response_msg   = isset( $params['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $params['RESPONSE_MESSAGE'] ) : '';
        $received_hash  = isset( $params['HASH'] ) ? sanitize_text_field( $params['HASH'] ) : '';

        wc_trustflowpay_log( 'Webhook data - ORDER_ID: ' . $order_id . ' | RESPONSE_CODE: ' . $response_code . ' | STATUS: ' . $status );

        if ( ! $order_id ) {
            wc_trustflowpay_log( 'No ORDER_ID in webhook data', 'error' );
            status_header( 400 );
            exit;
        }

        $order = $this->get_order_by_internal_id( $order_id );

        if ( ! $order ) {
            wc_trustflowpay_log( 'Order not found for ORDER_ID: ' . $order_id, 'error' );
            status_header( 404 );
            exit;
        }

        // Validate hash if present
        if ( $received_hash ) {
            if ( ! $this->validate_hash( $params, $received_hash ) ) {
                wc_trustflowpay_log( 'Webhook hash validation failed for Order #' . $order->get_id(), 'error' );
                $order->add_order_note( __( 'TrustFlowPay webhook failed: Invalid hash signature.', 'trustflowpay-gateway' ) );
                status_header( 400 );
                exit;
            }
        } else {
            wc_trustflowpay_log( 'No HASH in webhook response - proceeding without hash validation', 'warning' );
        }

        // Process the payment response
        $this->process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params );

        status_header( 200 );
        exit;
    }

    /**
     * Process payment response (used by return, webhook, and status enquiry)
     */
    protected function process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params ) {
        wc_trustflowpay_log( 'Processing payment response for Order #' . $order->get_id() . ' - Response Code: ' . $response_code . ' - Status: ' . $status );

        // Store transaction details
        $order->update_meta_data( '_trustflowpay_response_code', $response_code );
        $order->update_meta_data( '_trustflowpay_status', $status );
        $order->update_meta_data( '_trustflowpay_txn_id', $txn_id );
        $order->update_meta_data( '_trustflowpay_pg_ref_num', $pg_ref_num );
        $order->update_meta_data( '_trustflowpay_response_message', $response_msg );
        $order->update_meta_data( '_trustflowpay_full_response', wp_json_encode( $params ) );
        $order->save();

        // CRITICAL: Only RESPONSE_CODE = "000" AND STATUS = "Captured" means success
        if ( $response_code === '000' && $status === 'Captured' ) {
            wc_trustflowpay_log( 'Payment successful for Order #' . $order->get_id() );

            // Check if already processed to avoid duplicate processing
            if ( ! $order->is_paid() ) {
                $order->payment_complete( $txn_id );
                $order->update_status( $this->order_success_status );
                $order->add_order_note(
                    sprintf(
                        __( 'TrustFlowPay payment completed. Transaction ID: %1$s | PG Reference: %2$s | Status: %3$s', 'trustflowpay-gateway' ),
                        $txn_id,
                        $pg_ref_num,
                        $status
                    )
                );
                wc_trustflowpay_log( 'Order #' . $order->get_id() . ' marked as paid and set to ' . $this->order_success_status );
            } else {
                wc_trustflowpay_log( 'Order #' . $order->get_id() . ' already marked as paid, skipping duplicate processing', 'warning' );
            }
        } elseif ( in_array( $status, array( 'Pending', 'Timeout', 'Enrolled', 'Authentication Successful' ), true ) ) {
            // Pending/in-progress statuses
            wc_trustflowpay_log( 'Payment pending/in-progress for Order #' . $order->get_id() . ' - Status: ' . $status );
            $order->update_status( 'on-hold' );
            $order->add_order_note(
                sprintf(
                    __( 'TrustFlowPay payment pending. Response Code: %1$s | Status: %2$s | Message: %3$s', 'trustflowpay-gateway' ),
                    $response_code,
                    $status,
                    $response_msg
                )
            );
        } else {
            // Failed/declined
            wc_trustflowpay_log( 'Payment failed for Order #' . $order->get_id() . ' - Response Code: ' . $response_code . ' - Status: ' . $status, 'error' );
            $order->update_status( 'failed' );
            $order->add_order_note(
                sprintf(
                    __( 'TrustFlowPay payment failed. Response Code: %1$s | Status: %2$s | Message: %3$s', 'trustflowpay-gateway' ),
                    $response_code,
                    $status,
                    $response_msg
                )
            );
        }
    }

    /**
     * Status enquiry - check payment status with TrustFlowPay
     */
    public function status_enquiry( $order ) {
        wc_trustflowpay_log( '========== STATUS ENQUIRY STARTED FOR ORDER #' . $order->get_id() . ' ==========' );

        $internal_order_id = $order->get_meta( '_trustflowpay_order_id', true );
        $app_id            = $order->get_meta( '_trustflowpay_app_id', true );
        $txn_id            = $order->get_meta( '_trustflowpay_txn_id', true );

        if ( ! $internal_order_id ) {
            wc_trustflowpay_log( 'Cannot perform status enquiry: ORDER_ID not found in order meta', 'error' );
            return array(
                'success' => false,
                'message' => 'ORDER_ID not found',
            );
        }

        // Get amount in minor units
        $amount_minor = $this->convert_to_minor_units( $order->get_total(), $order->get_currency() );

        // Build status enquiry request
        $request_data = array(
            'APP_ID'        => $app_id ? $app_id : $this->app_id,
            'ORDER_ID'      => $internal_order_id,
            'CURRENCY_CODE' => $this->currency_code,
            'AMOUNT'        => $amount_minor,
        );

        // Add TXN_ID if available
        if ( $txn_id ) {
            $request_data['TXN_ID'] = $txn_id;
        }

        // Generate hash
        $request_data['HASH'] = $this->generate_hash( $request_data );

        $endpoint = rtrim( $this->base_url, '/' ) . '/pgui/services/paymentServices/transactionStatus';

        wc_trustflowpay_log( 'Status enquiry request to: ' . $endpoint );
        wc_trustflowpay_log( 'Status enquiry request data (without secret): ' . wp_json_encode( array_diff_key( $request_data, array( 'HASH' => '' ) ) ), 'debug' );

        $response = wp_remote_post(
            $endpoint,
            array(
                'method'  => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( $request_data ),
                'timeout' => 30,
            )
        );

        if ( is_wp_error( $response ) ) {
            wc_trustflowpay_log( 'Status enquiry request failed: ' . $response->get_error_message(), 'error' );
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        wc_trustflowpay_log( 'Status enquiry HTTP response code: ' . $response_code );
        wc_trustflowpay_log( 'Status enquiry response body: ' . substr( $response_body, 0, 500 ), 'debug' );

        if ( $response_code !== 200 ) {
            wc_trustflowpay_log( 'Status enquiry failed with HTTP code: ' . $response_code, 'error' );
            return array(
                'success' => false,
                'message' => 'HTTP error: ' . $response_code,
            );
        }

        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wc_trustflowpay_log( 'Status enquiry JSON decode error: ' . json_last_error_msg(), 'error' );
            return array(
                'success' => false,
                'message' => 'Invalid JSON response',
            );
        }

        // Handle both array and object response formats
        $txn_data = null;
        if ( is_array( $data ) && isset( $data[0] ) && is_array( $data[0] ) ) {
            // Array format: [ { transaction data }, { API_RESPONSE } ]
            $txn_data = $data[0];
            wc_trustflowpay_log( 'Status enquiry received array format response', 'debug' );
        } elseif ( is_array( $data ) && isset( $data['ORDER_ID'] ) ) {
            // Single object format
            $txn_data = $data;
            wc_trustflowpay_log( 'Status enquiry received single object format response', 'debug' );
        } else {
            wc_trustflowpay_log( 'Status enquiry response format not recognized', 'error' );
            return array(
                'success' => false,
                'message' => 'Unrecognized response format',
            );
        }

        $status_response_code = isset( $txn_data['RESPONSE_CODE'] ) ? sanitize_text_field( $txn_data['RESPONSE_CODE'] ) : '';
        $status               = isset( $txn_data['STATUS'] ) ? sanitize_text_field( $txn_data['STATUS'] ) : '';
        $status_txn_id        = isset( $txn_data['TXN_ID'] ) ? sanitize_text_field( $txn_data['TXN_ID'] ) : '';
        $status_pg_ref_num    = isset( $txn_data['PG_REF_NUM'] ) ? sanitize_text_field( $txn_data['PG_REF_NUM'] ) : '';
        $status_response_msg  = isset( $txn_data['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $txn_data['RESPONSE_MESSAGE'] ) : '';
        $received_hash        = isset( $txn_data['HASH'] ) ? sanitize_text_field( $txn_data['HASH'] ) : '';

        wc_trustflowpay_log( 'Status enquiry result - RESPONSE_CODE: ' . $status_response_code . ' | STATUS: ' . $status );

        // Validate hash if present
        if ( $received_hash ) {
            if ( ! $this->validate_hash( $txn_data, $received_hash ) ) {
                wc_trustflowpay_log( 'Status enquiry hash validation failed', 'error' );
                $order->add_order_note( __( 'TrustFlowPay status enquiry failed: Invalid hash signature.', 'trustflowpay-gateway' ) );
                return array(
                    'success' => false,
                    'message' => 'Hash validation failed',
                );
            }
        } else {
            wc_trustflowpay_log( 'No HASH in status enquiry response - proceeding without hash validation', 'warning' );
        }

        // Process the payment response
        $this->process_payment_response( $order, $status_response_code, $status, $status_txn_id, $status_pg_ref_num, $status_response_msg, $txn_data );

        return array(
            'success'       => true,
            'response_code' => $status_response_code,
            'status'        => $status,
            'message'       => $status_response_msg,
        );
    }

    /**
     * Add "Check TrustFlowPay Status" action to order actions
     */
    public function add_order_action( $actions, $order ) {
        if ( $order->get_payment_method() === $this->id && in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
            $actions['trustflowpay_check_status'] = array(
                'url'    => wp_nonce_url( admin_url( 'admin-post.php?action=trustflowpay_check_status&order_id=' . $order->get_id() ), 'trustflowpay_check_status' ),
                'name'   => __( 'Check TrustFlowPay Status', 'trustflowpay-gateway' ),
                'action' => 'trustflowpay_check_status',
            );
        }

        return $actions;
    }

    /**
     * Handle admin status check action
     */
    public function admin_check_status() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( 'Unauthorized' );
        }

        check_admin_referer( 'trustflowpay_check_status' );

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( 'Order not found' );
        }

        $result = $this->status_enquiry( $order );

        if ( $result['success'] ) {
            $message = sprintf(
                'Status enquiry completed. Response Code: %s | Status: %s | Message: %s',
                $result['response_code'],
                $result['status'],
                $result['message']
            );
        } else {
            $message = 'Status enquiry failed: ' . $result['message'];
        }

        wp_redirect( add_query_arg( array(
            'post'    => $order_id,
            'action'  => 'edit',
            'message' => urlencode( $message ),
        ), admin_url( 'post.php' ) ) );
        exit;
    }

    /**
     * Get order by internal ORDER_ID
     */
    protected function get_order_by_internal_id( $internal_order_id ) {
        global $wpdb;

        $post_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_trustflowpay_order_id' AND meta_value = %s LIMIT 1",
                $internal_order_id
            )
        );

        if ( $post_id ) {
            return wc_get_order( $post_id );
        }

        return false;
    }

    /**
     * Get base URL (public method for redirect page)
     */
    public function get_base_url() {
        return $this->base_url;
    }
}
