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

    public function __construct() {
        $this->id                 = 'trustflowpay';
        $this->method_title       = __( 'TrustFlowPay', 'trustflowpay-gateway' );
        $this->method_description = __( 'Accept card payments via TrustFlowPay PGH Checkout API', 'trustflowpay-gateway' );
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled              = $this->get_option( 'enabled' );
        $this->title                = $this->get_option( 'title' );
        $this->description          = $this->get_option( 'description' );
        $this->testmode             = 'yes' === $this->get_option( 'testmode', 'yes' );
        $this->order_success_status = $this->get_option( 'order_success_status', 'processing' );

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

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __( 'Enable/Disable', 'trustflowpay-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable TrustFlowPay Gateway', 'trustflowpay-gateway' ),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __( 'Title', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Payment method title displayed to customers at checkout.', 'trustflowpay-gateway' ),
                'default'     => __( 'Credit/Debit Card (TrustFlowPay)', 'trustflowpay-gateway' ),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __( 'Description', 'trustflowpay-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Payment method description displayed to customers at checkout.', 'trustflowpay-gateway' ),
                'default'     => __( 'Pay securely with your credit or debit card.', 'trustflowpay-gateway' ),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'   => __( 'Mode', 'trustflowpay-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable Sandbox Mode', 'trustflowpay-gateway' ),
                'default' => 'yes',
            ),
            'sandbox_settings' => array(
                'title'       => __( 'Sandbox Settings', 'trustflowpay-gateway' ),
                'type'        => 'title',
                'description' => '',
            ),
            'sandbox_base_url' => array(
                'title'       => __( 'Sandbox Base URL', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'default'     => 'https://sandbox.trustflowpay.com',
                'desc_tip'    => true,
            ),
            'sandbox_app_id' => array(
                'title'       => __( 'Sandbox App ID', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'default'     => '1107251111162944',
                'desc_tip'    => true,
            ),
            'sandbox_secret_key' => array(
                'title'       => __( 'Sandbox Secret Key (Salt)', 'trustflowpay-gateway' ),
                'type'        => 'password',
                'default'     => '2be3c4b527d14c12',
                'desc_tip'    => true,
            ),
            'sandbox_currency_code' => array(
                'title'       => __( 'Sandbox Currency Code', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Numeric ISO 4217 currency code (e.g., 840 for USD).', 'trustflowpay-gateway' ),
                'default'     => '840',
                'desc_tip'    => true,
            ),
            'production_settings' => array(
                'title'       => __( 'Production Settings', 'trustflowpay-gateway' ),
                'type'        => 'title',
                'description' => '',
            ),
            'production_base_url' => array(
                'title'       => __( 'Production Base URL', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'production_app_id' => array(
                'title'       => __( 'Production App ID', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'production_secret_key' => array(
                'title'       => __( 'Production Secret Key (Salt)', 'trustflowpay-gateway' ),
                'type'        => 'password',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'production_currency_code' => array(
                'title'       => __( 'Production Currency Code', 'trustflowpay-gateway' ),
                'type'        => 'text',
                'description' => __( 'Numeric ISO 4217 currency code.', 'trustflowpay-gateway' ),
                'default'     => '',
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
            'debug' => array(
                'title'   => __( 'Debug Mode', 'trustflowpay-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable logging', 'trustflowpay-gateway' ),
                'default' => 'no',
            ),
        );
    }

    public function admin_options() {
        ?>
        <h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
        </table>
        
        <h3><?php _e( 'Webhook URLs', 'trustflowpay-gateway' ); ?></h3>
        <p><?php _e( 'Configure these URLs in your TrustFlowPay merchant account:', 'trustflowpay-gateway' ); ?></p>
        <table class="form-table">
            <tr>
                <th><?php _e( 'Return URL:', 'trustflowpay-gateway' ); ?></th>
                <td><code><?php echo esc_html( WC()->api_request_url( 'wc_gateway_trustflowpay_return' ) ); ?></code></td>
            </tr>
            <tr>
                <th><?php _e( 'Callback URL:', 'trustflowpay-gateway' ); ?></th>
                <td><code><?php echo esc_html( WC()->api_request_url( 'wc_gateway_trustflowpay_callback' ) ); ?></code></td>
            </tr>
        </table>

        <h3><?php _e( 'Sandbox Test Cards', 'trustflowpay-gateway' ); ?></h3>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php _e( 'Card Number', 'trustflowpay-gateway' ); ?></th>
                    <th><?php _e( 'CVV', 'trustflowpay-gateway' ); ?></th>
                    <th><?php _e( 'Expiry', 'trustflowpay-gateway' ); ?></th>
                    <th><?php _e( 'PIN', 'trustflowpay-gateway' ); ?></th>
                    <th><?php _e( 'Scheme', 'trustflowpay-gateway' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr><td>4111110000000211</td><td>123</td><td>12/2030</td><td>111111</td><td>Visa</td></tr>
                <tr><td>4111110000000021</td><td>123</td><td>12/2030</td><td>111111</td><td>Visa</td></tr>
                <tr><td>5100000000000511</td><td>123</td><td>12/2030</td><td>111111</td><td>MasterCard</td></tr>
                <tr><td>5100000000000321</td><td>123</td><td>12/2030</td><td>111111</td><td>MasterCard</td></tr>
                <tr><td>3550998650131033</td><td>123</td><td>12/2030</td><td>111111</td><td>JCB</td></tr>
                <tr><td>3566000020000410</td><td>123</td><td>12/2030</td><td>111111</td><td>JCB</td></tr>
            </tbody>
        </table>
        <?php
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );

        $internal_order_id = 'TFP-' . $order_id . '-' . time();
        $order->update_meta_data( '_trustflowpay_order_id', $internal_order_id );
        $order->update_meta_data( '_trustflowpay_app_id', $this->app_id );
        $order->save();

        $amount_minor = $this->convert_to_minor_units( $order->get_total(), $order->get_currency() );

        $params = array(
            'APP_ID'                   => $this->app_id,
            'ORDER_ID'                 => $internal_order_id,
            'TXNTYPE'                  => 'SALE',
            'CURRENCY_CODE'            => $this->currency_code,
            'AMOUNT'                   => $amount_minor,
            'CUST_FIRST_NAME'          => $order->get_billing_first_name(),
            'CUST_LAST_NAME'           => $order->get_billing_last_name(),
            'CUST_NAME'                => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'CUST_STREET_ADDRESS1'     => $order->get_billing_address_1(),
            'CUST_CITY'                => $order->get_billing_city(),
            'CUST_STATE'               => $order->get_billing_state(),
            'CUST_COUNTRY'             => $order->get_billing_country(),
            'CUST_ZIP'                 => $order->get_billing_postcode(),
            'CUST_PHONE'               => $order->get_billing_phone(),
            'CUST_EMAIL'               => $order->get_billing_email(),
            'PRODUCT_DESC'             => sprintf( 'Order #%s on %s', $order_id, get_bloginfo( 'name' ) ),
            'RETURN_URL'               => WC()->api_request_url( 'wc_gateway_trustflowpay_return' ),
        );

        if ( $order->get_billing_address_2() ) {
            $params['CUST_STREET_ADDRESS2'] = $order->get_billing_address_2();
        }

        if ( $order->get_shipping_first_name() ) {
            $params['CUST_SHIP_FIRST_NAME']       = $order->get_shipping_first_name();
            $params['CUST_SHIP_LAST_NAME']        = $order->get_shipping_last_name();
            $params['CUST_SHIP_EMAIL']            = $order->get_billing_email();
            $params['CUST_SHIP_STREET_ADDRESS1']  = $order->get_shipping_address_1();
            $params['CUST_SHIP_CITY']             = $order->get_shipping_city();
            $params['CUST_SHIP_STATE']            = $order->get_shipping_state();
            $params['CUST_SHIP_COUNTRY']          = $order->get_shipping_country();
            $params['CUST_SHIP_ZIP']              = $order->get_shipping_postcode();
            $params['CUST_SHIP_PHONE']            = $order->get_billing_phone();
            
            if ( $order->get_shipping_address_2() ) {
                $params['CUST_SHIP_STREET_ADDRESS2'] = $order->get_shipping_address_2();
            }
        }

        $params['HASH'] = $this->generate_hash( $params );

        $order->update_meta_data( '_trustflowpay_request_params', json_encode( $params ) );
        $order->save();

        wc_trustflowpay_log( 'Payment request initiated for Order #' . $order_id . ' - ORDER_ID: ' . $internal_order_id );

        return array(
            'result'   => 'success',
            'redirect' => add_query_arg( 'order_id', $order_id, WC()->api_request_url( 'wc_gateway_trustflowpay_redirect' ) ),
        );
    }

    public function generate_hash( $params ) {
        $hash_params = $params;
        unset( $hash_params['HASH'] );
        
        ksort( $hash_params );
        
        $param_string = '';
        foreach ( $hash_params as $key => $value ) {
            if ( $param_string !== '' ) {
                $param_string .= '~';
            }
            $param_string .= $key . '=' . $value;
        }
        
        $final_string = $param_string . strtolower( $this->secret_key );
        
        $hash = strtoupper( hash( 'sha512', $final_string ) );
        
        wc_trustflowpay_log( 'Hash generated for params: ' . wp_json_encode( array_keys( $hash_params ) ) );
        
        return $hash;
    }

    public function validate_hash( $params, $received_hash ) {
        $calculated_hash = $this->generate_hash( $params );
        return hash_equals( $calculated_hash, $received_hash );
    }

    public function convert_to_minor_units( $amount, $currency ) {
        $decimals_map = array(
            'AED' => 2, 'USD' => 2, 'EUR' => 2, 'GBP' => 2, 'INR' => 2,
            'BDT' => 2, 'BHD' => 3, 'KWD' => 3, 'OMR' => 3, 'JOD' => 3,
            'JPY' => 0, 'VND' => 0, 'SAR' => 2, 'QAR' => 2,
        );
        
        $decimals = isset( $decimals_map[ $currency ] ) ? $decimals_map[ $currency ] : 2;
        
        return intval( round( $amount * pow( 10, $decimals ) ) );
    }

    public function handle_return() {
        wc_trustflowpay_log( 'Return URL handler called' );

        $params = array_merge( $_POST, $_GET );
        
        if ( empty( $params ) ) {
            wc_add_notice( __( 'Payment response data missing.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        wc_trustflowpay_log( 'Return params received: ' . wp_json_encode( array_keys( $params ) ) );

        if ( ! isset( $params['HASH'] ) ) {
            wc_add_notice( __( 'Payment response hash missing.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        $received_hash = $params['HASH'];
        if ( ! $this->validate_hash( $params, $received_hash ) ) {
            wc_trustflowpay_log( 'Hash validation failed on return URL', 'error' );
            wc_add_notice( __( 'Payment verification failed.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        $order_id_internal = isset( $params['ORDER_ID'] ) ? sanitize_text_field( $params['ORDER_ID'] ) : '';
        $order = $this->get_order_by_internal_id( $order_id_internal );

        if ( ! $order ) {
            wc_add_notice( __( 'Order not found.', 'trustflowpay-gateway' ), 'error' );
            wp_redirect( wc_get_checkout_url() );
            exit;
        }

        $response_code = isset( $params['RESPONSE_CODE'] ) ? sanitize_text_field( $params['RESPONSE_CODE'] ) : '';
        $status        = isset( $params['STATUS'] ) ? sanitize_text_field( $params['STATUS'] ) : '';
        $txn_id        = isset( $params['TXN_ID'] ) ? sanitize_text_field( $params['TXN_ID'] ) : '';
        $pg_ref_num    = isset( $params['PG_REF_NUM'] ) ? sanitize_text_field( $params['PG_REF_NUM'] ) : '';
        $response_msg  = isset( $params['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $params['RESPONSE_MESSAGE'] ) : '';

        $this->process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params );

        if ( '000' === $response_code && 'Captured' === $status ) {
            WC()->cart->empty_cart();
            wp_redirect( $this->get_return_url( $order ) );
        } else {
            wp_redirect( wc_get_checkout_url() );
        }
        exit;
    }

    public function handle_webhook() {
        wc_trustflowpay_log( 'Webhook handler called' );

        $raw_body = file_get_contents( 'php://input' );
        $params   = json_decode( $raw_body, true );

        if ( empty( $params ) || ! is_array( $params ) ) {
            wc_trustflowpay_log( 'Invalid webhook JSON', 'error' );
            status_header( 400 );
            echo 'Invalid JSON';
            exit;
        }

        wc_trustflowpay_log( 'Webhook params received: ' . wp_json_encode( array_keys( $params ) ) );

        if ( ! isset( $params['HASH'] ) ) {
            wc_trustflowpay_log( 'Webhook hash missing', 'error' );
            status_header( 400 );
            echo 'Hash missing';
            exit;
        }

        $received_hash = $params['HASH'];
        if ( ! $this->validate_hash( $params, $received_hash ) ) {
            wc_trustflowpay_log( 'Webhook hash validation failed', 'error' );
            status_header( 400 );
            echo 'Hash validation failed';
            exit;
        }

        $order_id_internal = isset( $params['ORDER_ID'] ) ? sanitize_text_field( $params['ORDER_ID'] ) : '';
        $order = $this->get_order_by_internal_id( $order_id_internal );

        if ( ! $order ) {
            wc_trustflowpay_log( 'Webhook order not found: ' . $order_id_internal, 'error' );
            status_header( 404 );
            echo 'Order not found';
            exit;
        }

        $response_code = isset( $params['RESPONSE_CODE'] ) ? sanitize_text_field( $params['RESPONSE_CODE'] ) : '';
        $status        = isset( $params['STATUS'] ) ? sanitize_text_field( $params['STATUS'] ) : '';
        $txn_id        = isset( $params['TXN_ID'] ) ? sanitize_text_field( $params['TXN_ID'] ) : '';
        $pg_ref_num    = isset( $params['PG_REF_NUM'] ) ? sanitize_text_field( $params['PG_REF_NUM'] ) : '';
        $response_msg  = isset( $params['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $params['RESPONSE_MESSAGE'] ) : '';

        $this->process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params );

        status_header( 200 );
        echo wp_json_encode( array( 'status' => 'ok' ) );
        exit;
    }

    protected function process_payment_response( $order, $response_code, $status, $txn_id, $pg_ref_num, $response_msg, $params ) {
        $order->update_meta_data( '_trustflowpay_response_code', $response_code );
        $order->update_meta_data( '_trustflowpay_status', $status );
        $order->update_meta_data( '_trustflowpay_txn_id', $txn_id );
        $order->update_meta_data( '_trustflowpay_pg_ref_num', $pg_ref_num );
        $order->update_meta_data( '_trustflowpay_response_message', $response_msg );
        $order->update_meta_data( '_trustflowpay_response_data', wp_json_encode( $params ) );
        $order->save();

        if ( '000' === $response_code && 'Captured' === $status ) {
            if ( ! $order->is_paid() ) {
                $order->payment_complete( $txn_id );
                $order->update_status( $this->order_success_status );
                $order->add_order_note(
                    sprintf(
                        __( 'TrustFlowPay payment captured. TXN_ID: %s, PG_REF_NUM: %s, RESPONSE_CODE: %s, STATUS: %s', 'trustflowpay-gateway' ),
                        $txn_id,
                        $pg_ref_num,
                        $response_code,
                        $status
                    )
                );
                wc_trustflowpay_log( 'Order #' . $order->get_id() . ' marked as paid - TXN_ID: ' . $txn_id );
            }
        } elseif ( in_array( $status, array( 'Pending', 'Timeout', 'Enrolled' ), true ) ) {
            $order->update_status( 'on-hold' );
            $order->add_order_note(
                sprintf(
                    __( 'TrustFlowPay payment status: %s. RESPONSE_CODE: %s, RESPONSE_MESSAGE: %s', 'trustflowpay-gateway' ),
                    $status,
                    $response_code,
                    $response_msg
                )
            );
            wc_trustflowpay_log( 'Order #' . $order->get_id() . ' set to on-hold - STATUS: ' . $status );
        } else {
            $order->update_status( 'failed' );
            $order->add_order_note(
                sprintf(
                    __( 'TrustFlowPay payment failed. RESPONSE_CODE: %s, STATUS: %s, RESPONSE_MESSAGE: %s', 'trustflowpay-gateway' ),
                    $response_code,
                    $status,
                    $response_msg
                )
            );
            wc_add_notice( __( 'Payment failed: ', 'trustflowpay-gateway' ) . $response_msg, 'error' );
            wc_trustflowpay_log( 'Order #' . $order->get_id() . ' failed - RESPONSE_CODE: ' . $response_code . ', STATUS: ' . $status, 'error' );
        }
    }

    protected function get_order_by_internal_id( $internal_order_id ) {
        global $wpdb;
        
        $order_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_trustflowpay_order_id' AND meta_value = %s LIMIT 1",
                $internal_order_id
            )
        );

        if ( $order_id ) {
            return wc_get_order( $order_id );
        }

        return false;
    }

    public function status_enquiry( $order ) {
        $internal_order_id = $order->get_meta( '_trustflowpay_order_id' );
        $stored_app_id     = $order->get_meta( '_trustflowpay_app_id' );
        $amount_minor      = $this->convert_to_minor_units( $order->get_total(), $order->get_currency() );
        $txn_id            = $order->get_meta( '_trustflowpay_txn_id' );

        $request = array(
            'APP_ID'        => $stored_app_id ? $stored_app_id : $this->app_id,
            'ORDER_ID'      => $internal_order_id,
            'CURRENCY_CODE' => $this->currency_code,
            'AMOUNT'        => $amount_minor,
        );

        if ( $txn_id ) {
            $request['TXN_ID'] = $txn_id;
        }

        $request['HASH'] = $this->generate_hash( $request );

        $endpoint = rtrim( $this->base_url, '/' ) . '/pgui/services/paymentServices/transactionStatus';

        wc_trustflowpay_log( 'Status Enquiry request to: ' . $endpoint );

        $response = wp_remote_post( $endpoint, array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $request ),
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) ) {
            wc_trustflowpay_log( 'Status Enquiry error: ' . $response->get_error_message(), 'error' );
            return array( 'error' => $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        wc_trustflowpay_log( 'Status Enquiry response: ' . $body );

        if ( empty( $data ) || ! is_array( $data ) ) {
            return array( 'error' => 'Invalid response from status enquiry API' );
        }

        if ( isset( $data['HASH'] ) && ! $this->validate_hash( $data, $data['HASH'] ) ) {
            wc_trustflowpay_log( 'Status Enquiry hash validation failed', 'error' );
            return array( 'error' => 'Hash validation failed' );
        }

        $response_code = isset( $data['RESPONSE_CODE'] ) ? sanitize_text_field( $data['RESPONSE_CODE'] ) : '';
        $status        = isset( $data['STATUS'] ) ? sanitize_text_field( $data['STATUS'] ) : '';
        $txn_id_resp   = isset( $data['TXN_ID'] ) ? sanitize_text_field( $data['TXN_ID'] ) : '';
        $pg_ref_num    = isset( $data['PG_REF_NUM'] ) ? sanitize_text_field( $data['PG_REF_NUM'] ) : '';
        $response_msg  = isset( $data['RESPONSE_MESSAGE'] ) ? sanitize_text_field( $data['RESPONSE_MESSAGE'] ) : '';

        $this->process_payment_response( $order, $response_code, $status, $txn_id_resp, $pg_ref_num, $response_msg, $data );

        return $data;
    }

    public function add_order_action( $actions, $order ) {
        if ( $order->get_payment_method() === $this->id ) {
            $actions['trustflowpay_check_status'] = array(
                'url'    => wp_nonce_url( admin_url( 'admin-post.php?action=trustflowpay_check_status&order_id=' . $order->get_id() ), 'trustflowpay_check_status' ),
                'name'   => __( 'Check TrustFlowPay Status', 'trustflowpay-gateway' ),
                'action' => 'trustflowpay_check_status',
            );
        }
        return $actions;
    }

    public function admin_check_status() {
        if ( ! current_user_can( 'edit_shop_orders' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'trustflowpay-gateway' ) );
        }

        check_admin_referer( 'trustflowpay_check_status' );

        $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );

        if ( ! $order ) {
            wp_die( __( 'Order not found.', 'trustflowpay-gateway' ) );
        }

        $result = $this->status_enquiry( $order );

        if ( isset( $result['error'] ) ) {
            $order->add_order_note( sprintf( __( 'TrustFlowPay Status Enquiry failed: %s', 'trustflowpay-gateway' ), $result['error'] ) );
        } else {
            $order->add_order_note(
                sprintf(
                    __( 'TrustFlowPay Status Enquiry completed. RESPONSE_CODE: %s, STATUS: %s', 'trustflowpay-gateway' ),
                    isset( $result['RESPONSE_CODE'] ) ? $result['RESPONSE_CODE'] : 'N/A',
                    isset( $result['STATUS'] ) ? $result['STATUS'] : 'N/A'
                )
            );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
        exit;
    }
}

add_action( 'woocommerce_api_wc_gateway_trustflowpay_redirect', 'wc_trustflowpay_redirect_page' );

function wc_trustflowpay_redirect_page() {
    $order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
    $order    = wc_get_order( $order_id );

    if ( ! $order ) {
        wp_die( __( 'Order not found.', 'trustflowpay-gateway' ) );
    }

    $gateways = WC()->payment_gateways->payment_gateways();
    $gateway  = isset( $gateways['trustflowpay'] ) ? $gateways['trustflowpay'] : null;

    if ( ! $gateway ) {
        wp_die( __( 'Payment gateway not found.', 'trustflowpay-gateway' ) );
    }

    $request_params_json = $order->get_meta( '_trustflowpay_request_params' );
    $request_params      = json_decode( $request_params_json, true );

    if ( empty( $request_params ) ) {
        wp_die( __( 'Payment request data not found.', 'trustflowpay-gateway' ) );
    }

    $testmode = 'yes' === $gateway->get_option( 'testmode', 'yes' );
    $base_url = $testmode ? $gateway->get_option( 'sandbox_base_url' ) : $gateway->get_option( 'production_base_url' );
    $action_url = rtrim( $base_url, '/' ) . '/pgui/jsp/paymentrequest';

    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?php _e( 'Redirecting to payment...', 'trustflowpay-gateway' ); ?></title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 20px auto; }
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <h2><?php _e( 'Redirecting to TrustFlowPay...', 'trustflowpay-gateway' ); ?></h2>
        <div class="spinner"></div>
        <p><?php _e( 'Please wait while we redirect you to the secure payment page.', 'trustflowpay-gateway' ); ?></p>
        
        <form id="trustflowpay-form" action="<?php echo esc_url( $action_url ); ?>" method="post">
            <?php foreach ( $request_params as $key => $value ) : ?>
                <input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
            <?php endforeach; ?>
            
            <input type="hidden" name="BROWSER_USER_AGENT" id="browser-user-agent" value="">
            <input type="hidden" name="BROWSER_LANGUAGE" id="browser-language" value="">
            <input type="hidden" name="BROWSER_JAVA_ENABLED" id="browser-java-enabled" value="">
            <input type="hidden" name="BROWSER_COLOR_DEPTH" id="browser-color-depth" value="">
            <input type="hidden" name="BROWSER_SCREEN_HEIGHT" id="browser-screen-height" value="">
            <input type="hidden" name="BROWSER_SCREEN_WIDTH" id="browser-screen-width" value="">
            <input type="hidden" name="BROWSER_TZ" id="browser-tz" value="">
            <input type="hidden" name="BROWSER_ACCEPT_HEADER" id="browser-accept-header" value="">
        </form>

        <script>
            document.getElementById('browser-user-agent').value = navigator.userAgent;
            document.getElementById('browser-language').value = navigator.language || navigator.languages[0] || '';
            document.getElementById('browser-java-enabled').value = navigator.javaEnabled() ? 'true' : 'false';
            document.getElementById('browser-color-depth').value = screen.colorDepth;
            document.getElementById('browser-screen-height').value = screen.height;
            document.getElementById('browser-screen-width').value = screen.width;
            document.getElementById('browser-tz').value = new Date().getTimezoneOffset();
            document.getElementById('browser-accept-header').value = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
            
            document.getElementById('trustflowpay-form').submit();
        </script>
    </body>
    </html>
    <?php
    exit;
}