<?php
/**
 * E2E webhook scenarios. Run through tests/e2e/run.sh (wp eval-file inside the cli container).
 *
 * Orders are paid the way a store sees it: checkout runs the gateway's process_payment(),
 * "Pay for this order" runs WooCommerce's real WC_Form_Handler::pay_action(), and QvaPay's
 * webhook is POSTed over HTTP to /wp-json/qvapay/v1/webhook, signed as QvaPay signs it.
 */

// An Error, not an Exception: pay_action() catches Exception and would swallow it
class QvaPay_E2E_Redirect extends Error {}

// wp eval-file runs this file inside a function: shared state goes through $GLOBALS
$GLOBALS['qvapay_e2e_failures'] = array();

function qvapay_e2e_expect( $label, $actual, $expected ) {
    if ( $actual === $expected ) {
        WP_CLI::log( "   ok    {$label}: {$actual}" );
        return;
    }
    $GLOBALS['qvapay_e2e_failures'][] = $label;
    WP_CLI::log( "   FAIL  {$label}: got {$actual}, expected {$expected}" );
}

function qvapay_e2e_webhook( $order_id, $uuid, $amount, $secret = null ) {
    $secret = $secret ?? get_option( 'woocommerce_qvapay_settings' )['app_secret'];
    $raw    = wp_json_encode( array(
        'transaction_uuid' => $uuid,
        'remote_id'        => (string) $order_id,
        'status'           => 'paid',
        'amount'           => $amount,
    ) );
    $res = wp_remote_post( 'http://wp/?rest_route=/qvapay/v1/webhook', array(
        'timeout' => 20,
        'headers' => array(
            'Content-Type'       => 'application/json',
            'x-qvapay-signature' => 'sha256=' . hash_hmac( 'sha256', $raw, $secret ),
            'x-qvapay-timestamp' => (string) time(),
        ),
        'body'    => $raw,
    ) );
    if ( is_wp_error( $res ) ) {
        WP_CLI::error( 'webhook request failed: ' . $res->get_error_message() );
    }
    $body = json_decode( wp_remote_retrieve_body( $res ), true );
    $tag  = isset( $body['error'] ) ? $body['error'] : implode( ',', array_keys( array_filter( (array) $body ) ) );
    return wp_remote_retrieve_response_code( $res ) . ' ' . $tag;
}

function qvapay_e2e_order( $total ) {
    $product = new WC_Product_Simple();
    $product->set_name( 'Widget' );
    $product->set_regular_price( $total );
    $product->save();

    $order = wc_create_order( array( 'customer_id' => $GLOBALS['qvapay_e2e_customer'] ) );
    $order->add_product( $product, 1 );
    $order->set_payment_method( 'qvapay' );
    $order->set_billing_email( 'buyer@example.test' );
    $order->calculate_totals();
    $order->save();
    return $order->get_id();
}

function qvapay_e2e_checkout( $order_id ) {
    WC()->payment_gateways()->payment_gateways()['qvapay']->process_payment( $order_id );
    return qvapay_e2e_last_invoice();
}

// The real handler behind the "Pay for this order" button; returns the invoice it issued
function qvapay_e2e_pay_for_order( $order_id ) {
    global $wp;
    wp_set_current_user( $GLOBALS['qvapay_e2e_customer'] );
    $order                          = wc_get_order( $order_id );
    $_GET['key']                    = $order->get_order_key();
    $_POST['woocommerce_pay']       = '1';
    $_POST['payment_method']        = 'qvapay';
    $_POST['woocommerce-pay-nonce'] = wp_create_nonce( 'woocommerce-pay' );
    $_REQUEST                       = array_merge( $_GET, $_POST );
    $wp->query_vars['order-pay']    = $order_id;

    $capture = function ( $location ) { throw new QvaPay_E2E_Redirect( $location ); };
    add_filter( 'wp_redirect', $capture, PHP_INT_MIN );
    $level    = ob_get_level();
    $redirect = '(none)';
    try {
        WC_Form_Handler::pay_action();
    } catch ( QvaPay_E2E_Redirect $e ) {
        $redirect = $e->getMessage();
    }
    remove_filter( 'wp_redirect', $capture, PHP_INT_MIN );
    while ( ob_get_level() > $level ) {
        ob_end_clean();
    }
    $_GET = $_POST = $_REQUEST = array();
    wp_set_current_user( 0 );

    $invoice = qvapay_e2e_last_invoice();
    qvapay_e2e_expect( '"Pay for this order" redirects to the new invoice', $redirect, 'https://www.qvapay.com/pay/' . $invoice['uuid'] );
    return $invoice;
}

function qvapay_e2e_last_invoice() {
    $invoices = get_option( 'qvapay_e2e_invoices', array() );
    return end( $invoices );
}

function qvapay_e2e_status( $order_id ) {
    // The webhook changed the order in the web container; drop this process's cached copy
    wp_cache_flush();
    $order = wc_get_order( $order_id );
    return $order->get_status() . ( $order->is_paid() ? ' (paid)' : '' );
}

function qvapay_e2e_has_note( $order_id, $needle ) {
    wp_cache_flush();
    foreach ( wc_get_order_notes( array( 'order_id' => $order_id ) ) as $note ) {
        if ( false !== strpos( $note->content, $needle ) ) {
            return 'yes';
        }
    }
    return 'no';
}

WC()->frontend_includes();
WC()->session  = new WC_Session_Handler();
WC()->session->init();
WC()->customer = new WC_Customer( 0, true );
WC()->cart     = new WC_Cart();

$customer = wc_create_new_customer( 'buyer-' . wp_generate_password( 8, false ) . '@example.test', '', wp_generate_password() );
if ( is_wp_error( $customer ) ) {
    WP_CLI::error( $customer->get_error_message() );
}
$GLOBALS['qvapay_e2e_customer'] = $customer;

WP_CLI::log( '1. Customer pays the FIRST invoice after "Pay for this order" issued a second' );
$order = qvapay_e2e_order( '25.00' );
$a     = qvapay_e2e_checkout( $order );
qvapay_e2e_pay_for_order( $order );
qvapay_e2e_expect( 'webhook for invoice A', qvapay_e2e_webhook( $order, $a['uuid'], $a['amount'] ), '200 received' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );

WP_CLI::log( '2. Customer pays the latest invoice' );
$order = qvapay_e2e_order( '25.00' );
qvapay_e2e_checkout( $order );
$b = qvapay_e2e_pay_for_order( $order );
qvapay_e2e_expect( 'webhook for invoice B', qvapay_e2e_webhook( $order, $b['uuid'], $b['amount'] ), '200 received' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );

WP_CLI::log( '3. Customer pays both invoices' );
$order = qvapay_e2e_order( '25.00' );
$a     = qvapay_e2e_checkout( $order );
$b     = qvapay_e2e_pay_for_order( $order );
qvapay_e2e_expect( 'webhook for invoice A', qvapay_e2e_webhook( $order, $a['uuid'], $a['amount'] ), '200 received' );
qvapay_e2e_expect( 'webhook for invoice B', qvapay_e2e_webhook( $order, $b['uuid'], $b['amount'] ), '200 received,already_processed' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );
qvapay_e2e_expect( 'note about the second payment', qvapay_e2e_has_note( $order, $b['uuid'] . ', 25 USD) para este pedido, que ya estaba pagado con ' . $a['uuid'] ), 'yes' );

WP_CLI::log( '4. Total raised from 25 to 40 between invoices; customer pays the 25 invoice' );
$order = qvapay_e2e_order( '25.00' );
$a     = qvapay_e2e_checkout( $order );
$extra = new WC_Product_Simple();
$extra->set_name( 'Extra' );
$extra->set_regular_price( '15.00' );
$extra->save();
$edited = wc_get_order( $order );
$edited->add_product( $extra, 1 );
$edited->calculate_totals();
$edited->save();
$b = qvapay_e2e_pay_for_order( $order );
qvapay_e2e_expect( 'webhook for the 25 invoice', qvapay_e2e_webhook( $order, $a['uuid'], $a['amount'] ), '200 received,amount_short' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'on-hold' );
qvapay_e2e_expect( 'note about the short invoice', qvapay_e2e_has_note( $order, 'menos que el total actual del pedido (40.00 USD)' ), 'yes' );
qvapay_e2e_expect( 'webhook for the 40 invoice', qvapay_e2e_webhook( $order, $b['uuid'], $b['amount'] ), '200 received' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );

WP_CLI::log( '5. Webhook with a uuid never issued for this order' );
$order = qvapay_e2e_order( '25.00' );
qvapay_e2e_checkout( $order );
qvapay_e2e_expect( 'webhook for a foreign uuid', qvapay_e2e_webhook( $order, 'ffffffff-0000-4000-8000-000000000999', 25 ), '400 uuid_mismatch' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'pending' );

// Orders still pending from v1.0.0 only have the single meta, written exactly like this
function qvapay_e2e_legacy_order( $uuid ) {
    $order_id = qvapay_e2e_order( '25.00' );
    $order    = wc_get_order( $order_id );
    $order->update_meta_data( '_qvapay_transaction_uuid', $uuid );
    $order->update_status( 'pending' );
    $order->save();
    return $order_id;
}

WP_CLI::log( '6. Order from v1.0.0 (only _qvapay_transaction_uuid) paid with its invoice' );
$order = qvapay_e2e_legacy_order( 'eeeeeeee-0000-4000-8000-000000000001' );
qvapay_e2e_expect( 'webhook for another uuid', qvapay_e2e_webhook( $order, 'eeeeeeee-0000-4000-8000-000000000002', 25 ), '400 uuid_mismatch' );
qvapay_e2e_expect( 'webhook for its uuid', qvapay_e2e_webhook( $order, 'eeeeeeee-0000-4000-8000-000000000001', 25 ), '200 received' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );

WP_CLI::log( '7. Order from v1.0.0, "Pay for this order" after upgrading, customer pays the pre-upgrade invoice' );
$order = qvapay_e2e_legacy_order( 'eeeeeeee-0000-4000-8000-000000000003' );
qvapay_e2e_pay_for_order( $order );
qvapay_e2e_expect( 'webhook for the pre-upgrade invoice', qvapay_e2e_webhook( $order, 'eeeeeeee-0000-4000-8000-000000000003', 25 ), '200 received' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'processing (paid)' );

WP_CLI::log( '8. Webhook signed with the wrong secret' );
$order = qvapay_e2e_order( '25.00' );
$a     = qvapay_e2e_checkout( $order );
qvapay_e2e_expect( 'forged webhook', qvapay_e2e_webhook( $order, $a['uuid'], $a['amount'], 'not-the-secret' ), '401 signature_mismatch' );
qvapay_e2e_expect( 'order', qvapay_e2e_status( $order ), 'pending' );

$failures = $GLOBALS['qvapay_e2e_failures'];
if ( $failures ) {
    WP_CLI::error( count( $failures ) . ' expectation(s) failed: ' . implode( '; ', $failures ) );
}
WP_CLI::success( 'All webhook scenarios passed.' );
