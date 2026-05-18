<?php
/**
 * WooCommerce Blocks (the block-based checkout) integration.
 *
 * Hooks straight into woocommerce_blocks_payment_method_type_registration
 * so the listener exists before Blocks fires the registration phase —
 * going through woocommerce_blocks_loaded is unreliable because Blocks
 * may already have fired the registration action by the time our hook
 * callback runs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class QvaPay_Blocks_Integration {

    public static function register( $registry = null ) {
        if ( null === $registry ) {
            return;
        }
        if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
            return;
        }

        require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-blocks-method.php';

        if ( ! class_exists( 'QvaPay_Blocks_Method' ) ) {
            return;
        }

        $registry->register( new QvaPay_Blocks_Method() );
    }
}
