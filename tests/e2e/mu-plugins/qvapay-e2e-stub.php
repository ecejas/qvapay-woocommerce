<?php
/**
 * E2E ONLY (never shipped): answers QvaPay's create_invoice locally so the real gateway
 * code runs without network access. Each call returns a fresh uuid and is recorded in
 * the `qvapay_e2e_invoices` option for the scenarios to read.
 */
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
    if ( false === strpos( $url, 'qvapay.com/api/v2/create_invoice' ) ) {
        return $pre;
    }
    $seq = (int) get_option( 'qvapay_e2e_invoice_seq', 0 ) + 1;
    update_option( 'qvapay_e2e_invoice_seq', $seq );

    $uuid     = sprintf( '00000000-0000-4000-8000-%012d', $seq );
    $body     = json_decode( $args['body'], true );
    $invoices = get_option( 'qvapay_e2e_invoices', array() );
    $invoices[] = array( 'uuid' => $uuid, 'amount' => $body['amount'], 'remote_id' => $body['remote_id'] );
    update_option( 'qvapay_e2e_invoices', $invoices );

    return array(
        'headers'  => array(),
        'body'     => wp_json_encode( array( 'transaction_uuid' => $uuid, 'url' => 'https://www.qvapay.com/pay/' . $uuid ) ),
        'response' => array( 'code' => 200, 'message' => 'OK' ),
        'cookies'  => array(),
        'filename' => null,
    );
}, 10, 3 );
