<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QvaPay_Plugin {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
        add_action( 'rest_api_init', array( 'QvaPay_Webhook', 'register_routes' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( QVAPAY_WC_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
    }

    public function register_gateway( $gateways ) {
        $gateways[] = 'QvaPay_Gateway';
        return $gateways;
    }

    public function plugin_action_links( $links ) {
        $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=qvapay' );
        $settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Ajustes', 'qvapay-for-woocommerce' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }
}
