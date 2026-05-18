<?php
/**
 * Plugin Name:       QvaPay for WooCommerce
 * Plugin URI:        https://qvapay.com
 * Description:       Acepta pagos en USD a través de QvaPay como pasarela de pago en WooCommerce.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            QvaPay
 * Author URI:        https://qvapay.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       qvapay-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'QVAPAY_WC_VERSION', '1.0.0' );
define( 'QVAPAY_WC_PLUGIN_FILE', __FILE__ );
define( 'QVAPAY_WC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'QVAPAY_WC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'QVAPAY_WC_API_BASE', 'https://qvapay.com/api/v2' );
define( 'QVAPAY_WC_CHECKOUT_BASE', 'https://www.qvapay.com/pay/' );

add_action( 'plugins_loaded', 'qvapay_wc_bootstrap' );

function qvapay_wc_bootstrap() {
    load_plugin_textdomain(
        'qvapay-for-woocommerce',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );

    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', 'qvapay_wc_missing_wc_notice' );
        return;
    }

    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-logger.php';
    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-api-client.php';
    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-gateway.php';
    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-webhook.php';
    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-blocks-integration.php';
    require_once QVAPAY_WC_PLUGIN_DIR . 'includes/class-qvapay-plugin.php';

    QvaPay_Plugin::instance();
}

function qvapay_wc_missing_wc_notice() {
    echo '<div class="notice notice-error"><p>';
    echo esc_html__( 'QvaPay for WooCommerce requiere que WooCommerce esté instalado y activo.', 'qvapay-for-woocommerce' );
    echo '</p></div>';
}

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
} );
