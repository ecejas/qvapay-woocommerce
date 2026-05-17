<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QvaPay_Api_Client {

    public static function create_invoice( array $args, array $credentials ) {
        return self::request( 'create_invoice', $args, $credentials );
    }

    public static function get_info( array $credentials ) {
        return self::request( 'info', new stdClass(), $credentials );
    }

    private static function request( $path, $body, array $credentials ) {
        if ( empty( $credentials['app_id'] ) || empty( $credentials['app_secret'] ) ) {
            return new WP_Error(
                'qvapay_missing_credentials',
                __( 'Faltan las credenciales de QvaPay (app-id / app-secret).', 'qvapay-for-woocommerce' )
            );
        }

        $url = trailingslashit( QVAPAY_WC_API_BASE ) . ltrim( $path, '/' );

        $response = wp_remote_post( $url, array(
            'timeout' => 30,
            'headers' => array(
                'app-id'       => $credentials['app_id'],
                'app-secret'   => $credentials['app_secret'],
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'QvaPay-WooCommerce/' . QVAPAY_WC_VERSION,
            ),
            'body'    => wp_json_encode( $body ),
        ) );

        if ( is_wp_error( $response ) ) {
            QvaPay_Logger::error( sprintf( '[%s] transport error: %s', $path, $response->get_error_message() ) );
            return $response;
        }

        $code    = wp_remote_retrieve_response_code( $response );
        $raw     = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $raw, true );

        if ( 200 !== (int) $code ) {
            $msg = is_array( $decoded ) && ! empty( $decoded['error'] )
                ? $decoded['error']
                : sprintf( __( 'QvaPay devolvió HTTP %d.', 'qvapay-for-woocommerce' ), (int) $code );

            QvaPay_Logger::error( sprintf( '[%s] HTTP %d: %s', $path, $code, $raw ) );

            return new WP_Error( 'qvapay_http_error', $msg, array( 'status' => $code, 'body' => $raw ) );
        }

        if ( null === $decoded ) {
            return new WP_Error(
                'qvapay_invalid_json',
                __( 'Respuesta inválida de QvaPay.', 'qvapay-for-woocommerce' )
            );
        }

        return $decoded;
    }
}
