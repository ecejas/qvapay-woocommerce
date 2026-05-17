<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QvaPay_Webhook {

    const TIMESTAMP_TOLERANCE = 300;

    public static function register_routes() {
        register_rest_route(
            'qvapay/v1',
            '/webhook',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function handle( WP_REST_Request $request ) {
        $raw       = $request->get_body();
        $signature = $request->get_header( 'x_qvapay_signature' );
        $timestamp = $request->get_header( 'x_qvapay_timestamp' );

        if ( empty( $raw ) || empty( $signature ) || empty( $timestamp ) ) {
            return self::reject( 'missing_headers', 401 );
        }

        $now  = time();
        $diff = abs( $now - (int) $timestamp );
        if ( $diff > self::TIMESTAMP_TOLERANCE ) {
            return self::reject( 'timestamp_out_of_range', 401 );
        }

        $settings = get_option( 'woocommerce_qvapay_settings', array() );
        $secret   = isset( $settings['app_secret'] ) ? $settings['app_secret'] : '';

        if ( empty( $secret ) ) {
            return self::reject( 'secret_not_configured', 503 );
        }

        if ( 0 !== strpos( $signature, 'sha256=' ) ) {
            return self::reject( 'bad_signature_format', 401 );
        }

        $expected = 'sha256=' . hash_hmac( 'sha256', $raw, $secret );
        if ( ! hash_equals( $expected, $signature ) ) {
            return self::reject( 'signature_mismatch', 401 );
        }

        $payload = json_decode( $raw, true );
        if ( ! is_array( $payload ) ) {
            return self::reject( 'invalid_json', 400 );
        }

        $uuid      = isset( $payload['transaction_uuid'] ) ? sanitize_text_field( $payload['transaction_uuid'] ) : '';
        $remote_id = isset( $payload['remote_id'] ) ? sanitize_text_field( $payload['remote_id'] ) : '';
        $status    = isset( $payload['status'] ) ? sanitize_text_field( $payload['status'] ) : '';
        $amount    = isset( $payload['amount'] ) ? sanitize_text_field( (string) $payload['amount'] ) : '';

        if ( '' === $uuid || '' === $remote_id || '' === $status ) {
            return self::reject( 'missing_payload_fields', 400 );
        }

        $order = wc_get_order( (int) $remote_id );
        if ( ! $order ) {
            QvaPay_Logger::error( sprintf( 'webhook for unknown order %s (txn %s)', $remote_id, $uuid ) );
            return new WP_REST_Response( array( 'received' => true, 'order_found' => false ), 200 );
        }

        if ( $order->get_payment_method() !== 'qvapay' ) {
            return self::reject( 'wrong_payment_method', 400 );
        }

        $stored_uuid = $order->get_meta( QvaPay_Gateway::ORDER_META_TXN_UUID );
        if ( $stored_uuid && $stored_uuid !== $uuid ) {
            QvaPay_Logger::error( sprintf( 'uuid mismatch order %s: stored=%s received=%s', $remote_id, $stored_uuid, $uuid ) );
            return self::reject( 'uuid_mismatch', 400 );
        }

        if ( $order->is_paid() || in_array( $order->get_status(), array( 'processing', 'completed', 'refunded' ), true ) ) {
            return new WP_REST_Response( array( 'received' => true, 'already_processed' => true ), 200 );
        }

        if ( 'paid' === $status ) {
            $order->payment_complete( $uuid );
            $order->add_order_note(
                sprintf(
                    /* translators: 1: transaction UUID, 2: amount in USD */
                    __( 'QvaPay: pago confirmado. UUID: %1$s. Monto: %2$s USD.', 'qvapay-for-woocommerce' ),
                    $uuid,
                    $amount
                )
            );

            QvaPay_Logger::log( sprintf( 'order %s marked paid (txn %s)', $remote_id, $uuid ) );

            return new WP_REST_Response( array( 'received' => true ), 200 );
        }

        QvaPay_Logger::log( sprintf( 'webhook status=%s for order %s ignored', $status, $remote_id ) );
        return new WP_REST_Response( array( 'received' => true, 'ignored_status' => $status ), 200 );
    }

    private static function reject( $code, $http_status ) {
        QvaPay_Logger::error( sprintf( 'webhook rejected: %s (HTTP %d)', $code, $http_status ) );
        return new WP_REST_Response( array( 'error' => $code ), $http_status );
    }
}
