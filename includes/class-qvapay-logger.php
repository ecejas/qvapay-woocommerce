<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QvaPay_Logger {

    const SOURCE = 'qvapay';

    public static function log( $message, $level = 'info' ) {
        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $settings = get_option( 'woocommerce_qvapay_settings', array() );
        $debug    = isset( $settings['debug'] ) && 'yes' === $settings['debug'];

        if ( ! $debug && 'error' !== $level ) {
            return;
        }

        if ( ! is_string( $message ) ) {
            $message = wp_json_encode( $message );
        }

        wc_get_logger()->log( $level, $message, array( 'source' => self::SOURCE ) );
    }

    public static function error( $message ) {
        self::log( $message, 'error' );
    }
}
