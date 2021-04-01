<?php
// let the awesome begin
add_action( 'plugins_loaded', 'init_Payfirma_Gateway' );


/**
 * Initiate Payfirma Gateway and include the gateway code
 *
 */
function init_Payfirma_Gateway() {

   include( plugin_dir_path( __FILE__ ) . 'class/class.payfirma.php');

}

/**
 * Add the Payfirma Gateway to list of WooCommerce Gateways
 *
 */
function add_Payfirma( $methods ) {
    $methods[] = 'WC_Gateway_Payfirma';
    return $methods;
}

add_filter( 'woocommerce_payment_gateways', 'add_Payfirma' );
