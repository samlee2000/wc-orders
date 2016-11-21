<?php

// Report all PHP errors (see changelog)
error_reporting(E_ALL);
//$USER_ID_OFFSET = 10000;
define("USER_ID_OFFSET", 10000);

function pp_id_from_wc_id($wc_id){
	return USER_ID_OFFSET + $wc_id;
}
function add_promisepay_user($environment_url, $user) {
		$environment_url_user =  $environment_url ."users";

		$email = ($user->user_email === "samlee2000@gmail.com") ?  
				 "nobody@no.com": $user->user_email;

		$payload_user = array(
			"id" =>  pp_id_from_wc_id( $user->ID ),
			"first_name"         	=> empty($user->first_name) ? "<no_first_name>" :  $user->first_name, //buyer
			"last_name"          	=> empty($user->lastname )  ? "<no_last_name>"  : $user->last_name,
			"email"              	=> $email,
			"state"					=> 'NY',
			"country"				=> 'USA',
		  );

		error_log( " payload user build-query::: " . http_build_query($payload_user) );
		//add buyer to Promisepay 

		$response = wp_remote_post( $environment_url_user, array(
			'method'    => 'POST',
			'headers'	=> array(
				"Authorization" => 'Basic c2FtbGVlMjAwMEBnbWFpbC5jb206c2Vjb25kZ2Y',
				),
			'body'      => http_build_query( $payload_user ),
			'timeout'   => 120,
			'sslverify' => false,
			) 
		);

		if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-promisepay-aim' ) );

		if ( empty( $response['body'] ) ){

			throw new Exception( __( 'PromisPay\'s Response empty, from the url:'.$environment_url_user, 'spyr-promisepay-aim' ) );
		}

		return $response;
	     
}

function  add_promisepay_buyer($environment_url){
		$current_user = wp_get_current_user();
		if (!$current_user->exists()){
			//abort?
		}
		
		return add_promisepay_user ($environment_url, $current_user); 

}


function  add_promisepay_seller($environment_url, $order_id){
			
		
		$seller_id = dokan_get_seller_id_by_order( $order_id );

		$seller = new WP_user($seller_id);
		
		return add_promisepay_user($environment_url, $seller);

}

function  add_promisepay_item($environment_url, $order_id) {
		$customer_order = new WC_Order( $order_id );
		// Are we testing right now or is it a real transaction
		
		$environment_url_items =  $environment_url ."items";

		$current_user = wp_get_current_user();
		if (!$current_user->exists()){
			//abort?
		}

		$order_id  = $customer_order->get_order_number();
		$seller_id = dokan_get_seller_id_by_order( $order_id );

		error_log("order id" . $order_id);
		error_log( "seller id " . $seller_id  );

        $order_items = $customer_order->get_items();

        foreach( $order_items as $product ) {
            $prodct_name[] = $product['name']; 
        }

        $product_list = implode( '_', $prodct_name );

        error_log("products in the order :". $product_list);

		//add the item to Promiepay:
		$payload_item = array(
			"id"	=>  "WooCommerceBidC".$order_id,
			"amount"  => $customer_order-> get_total() *100 , //in # of cents , as promisepay likes
			"name"   => $product_list,
			"payment_type" => 1, //escrow
			"buyer_id" =>   pp_id_from_wc_id ($current_user->ID),
			"seller_id" =>  pp_id_from_wc_id ($seller_id),

		);

		//implode(" | ", payload_item) ; 
		//echo http_build_query( $payload_item ) ;
		error_log( " payload item build-query: " . http_build_query($payload_item) );
		error_log(" payload item " . print_r($payload_item )); 

		$response = wp_remote_post( $environment_url_items, array(
			'method'    => 'POST',
			'headers'	=> array(
				"Authorization" => 'Basic c2FtbGVlMjAwMEBnbWFpbC5jb206c2Vjb25kZ2Y',
				),
			'body'      => http_build_query( $payload_item ),
			'timeout'   => 120,
			'sslverify' => false,
			) 
		);

		if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'spyr-promisepay-aim' ) );

		if ( empty( $response['body'] ) ){

			throw new Exception( __( 'PromisPay\'s Response empty, from the url:'.$environment_url_user, 'spyr-promisepay-aim' ) );
		}

		return $response;

	}

/* PromisPay AIM Payment Gateway Class */
class SPYR_PromisePay_AIM extends WC_Payment_Gateway {

	// Setup our Gateway's id, description and other values
	function __construct() {

		// The global ID for this Payment method
		$this->id = "SPYR_PromisePay_AIM";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "PromisPay AIM", 'spyr-promisepay-aim' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "PromisPay AIM Payment Gateway Plug-in for WooCommerce (xli)", 'spyr-promisepay-aim' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "PromisPay AIM", 'spyr-promisepay-aim' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration.
		// With promisepay escrow, no direct payment is made on the checkout page, thus false
		$this->has_fields = false;

		// Supports the default credit card form
	//	$this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		

		//'woocommerce_api_'.strtolower(get_class($this)) will result in 'woocommerce_api_spyr_promisepay_aim'

    	add_action('woocommerce_api_'.strtolower(get_class($this)), array(&$this, 'handle_callback'));
  		error_log ( "adding callback on URL: " . 'woocommerce_api_'.strtolower(get_class($this)) ); 

	} // End __construct()

  	function handle_callback() {
  		echo (" hittting the callback !!! \n");
    			//Handle the thing here!
  		error_log( " handle call back here ");
		// get the raw POST data
		$rawData = file_get_contents("php://input");
		echo $rawData;
		error_log ($rawData);
		echo "\n";
		echo "-the parsed json-------------------------\n";
		$dataJson = json_decode($rawData);

		$json_pretty_string = json_encode($dataJson, JSON_PRETTY_PRINT);
		echo $json_pretty_string;
		error_log ($json_pretty_string);
  		exit ;
  	}

	// Build the administration fields for this specific Gateway
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Enable / Disable', 'spyr-promisepay-aim' ),
				'label'		=> __( 'Enable this payment gateway', 'spyr-promisepay-aim' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'spyr-promisepay-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'spyr-promisepay-aim' ),
				'default'	=> __( 'Credit card', 'spyr-promisepay-aim' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'spyr-promisepay-aim' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'spyr-promisepay-aim' ),
				'default'	=> __( 'Pay securely using your credit card.', 'spyr-promisepay-aim' ),
				'css'		=> 'max-width:350px;'
			),
			/*
			'api_login' => array(
				'title'		=> __( 'PromisPay API Login', 'spyr-promisepay-aim' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'This is the API Login provided by PromisPay when you signed up for an account.', 'spyr-promisepay-aim' ),
			),
			'trans_key' => array(
				'title'		=> __( 'PromisPay Transaction Key', 'spyr-promisepay-aim' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'This is the Transaction Key provided by PromisPay when you signed up for an account.', 'spyr-promisepay-aim' ),
			),
			*/
			'basic_authorization_token' => array(
				'title' 	=> __('The authorization token to login PromisPay account', 'spyr-promisepay-aim'),
				'type'	 	=> 'text',
				'default'  	=> 'c2FtbGVlMjAwMEBnbWFpbC5jb206c2Vjb25kZ2Y',
				'desc_tip'	=> __( 'This can be generated with Postman , based on your PromisPay login/password'),
			),
			'environment' => array(
				'title'		=> __( 'PromisPay Test Mode', 'spyr-promisepay-aim' ),
				'label'		=> __( 'Enable Test Mode', 'spyr-promisepay-aim' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'spyr-promisepay-aim' ),
				'default'	=> 'no',
			),
		);		
	}


	
	// Submit payment and handle response
	public function process_payment( $order_id ) {
		global $woocommerce;
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );
		
		// Are we testing right now or is it a real transaction
		$environment = ( $this->environment == "yes" ) ? 'TRUE' : 'FALSE';

		// Decide which URL to post to
		$environment_url = ( "FALSE" == $environment ) 
						   ? 'https://api.promisepay.com/'
						   : 'https://test.api.promisepay.com/';

		/* 
		1. create the seller / buyer account at Promisepay 
		2. create the item at Promisepay
		2. set the Promiepay order status to 
		*/
		

		$response = add_promisepay_seller($environment_url, $order_id);
		error_log(" seller added");
		
		$response = add_promisepay_buyer ($environment_url);
		error_log ("buyer added");

		$response = add_promisepay_item($environment_url, $order_id);
		error_log ("item added");

		

		// Retrieve the body's resopnse if no errors found
		$response_body = wp_remote_retrieve_body( $response );

		// Parse the response into something we can read
		foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
			$resp = explode( "|", $line );
		}

		// Get the values we need
		$r['response_code']             = $resp[0];
		$r['response_sub_code']         = $resp[1];
		$r['response_reason_code']      = $resp[2];
		$r['response_reason_text']      = $resp[3];

		
		//set to wc-pending
		if(true){
		  $customer_order->update_status('wc-pending', __('Awaiting payment from buyer to the escorw', 'spyr-promisepay-aim'));
		  
		  $customer_order->add_order_note( __( 'set to pending', 'spyr-promisepay-aim' ) );
		  
		  // Empty the cart (Very important step)
		  $woocommerce->cart->empty_cart();

		  // Redirect to thank you page
		  return array(
			     'result'   => 'success',
			     'redirect' => $this->get_return_url( $customer_order ),
			     );
		} else {
		  // Transaction was not succesful
		  // Add notice to the cart
		  wc_add_notice($response_body, 'error');
		  //wc_add_notice( $r['response_reason_text'], 'error' );
		  
		  // Add note to the order for your reference
		  $customer_order->add_order_note( 'Error: '. $r['response_reason_text'] );
		}
		

	}
	
	// Validate fields
	public function validate_fields() {
		return true;
	}
	
	// Check if we are forcing SSL on checkout pages
	// Custom function not required by the Gateway
	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}		
	}

} // End of SPYR_PromisePay_AIM