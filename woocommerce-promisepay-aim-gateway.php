<?php
/*
Plugin Name: Promisepay gateway - WooCommerce Gateway
Plugin URI: www.no.com
Description: Extends WooCommerce by Adding the Promisepay gateway
Version: 1
Author: Sam Lee
Author URI: www.no.com
*/

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'spyr_promisepay_aim_init', 0 );
function spyr_promisepay_aim_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-promisepay-aim.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'spyr_add_promisepay_aim_gateway' );
	function spyr_add_promisepay_aim_gateway( $methods ) {
		$methods[] = 'SPYR_PromisePay_AIM';
		return $methods;
	}
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'SPYR_PromisePay_AIM_action_links' );
function SPYR_PromisePay_AIM_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'spyr-promisepay_aim' ) . '</a>',
	);

	//xli
	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}