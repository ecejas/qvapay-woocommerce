<?php
/**
 * Block-based checkout payment method type. Loaded lazily by
 * QvaPay_Blocks_Integration::register() once we know the abstract base
 * class from WooCommerce Blocks is available.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
    return;
}

class QvaPay_Blocks_Method extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

    protected $name = 'qvapay';

    public function initialize() {
        $this->settings = get_option( 'woocommerce_qvapay_settings', array() );
    }

    public function is_active() {
        if ( ! isset( $this->settings['enabled'] ) || 'yes' !== $this->settings['enabled'] ) {
            return false;
        }
        if ( 'USD' !== get_woocommerce_currency() ) {
            return false;
        }
        if ( empty( $this->settings['app_id'] ) || empty( $this->settings['app_secret'] ) ) {
            return false;
        }
        return true;
    }

    public function get_payment_method_script_handles() {
        $handle = 'qvapay-blocks';
        wp_register_script(
            $handle,
            QVAPAY_WC_PLUGIN_URL . 'assets/js/checkout-blocks.js',
            array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n' ),
            QVAPAY_WC_VERSION,
            true
        );
        return array( $handle );
    }

    public function get_supported_features() {
        return array( 'products' );
    }

    public function get_payment_method_data() {
        return array(
            'title'       => isset( $this->settings['title'] ) && '' !== $this->settings['title']
                ? $this->settings['title']
                : __( 'QvaPay', 'qvapay-for-woocommerce' ),
            'description' => isset( $this->settings['description'] ) && '' !== $this->settings['description']
                ? $this->settings['description']
                : __( 'Paga con tu balance de QvaPay.', 'qvapay-for-woocommerce' ),
            'icon'        => QVAPAY_WC_PLUGIN_URL . 'assets/images/icon.svg',
            'supports'    => array( 'products' ),
        );
    }
}
