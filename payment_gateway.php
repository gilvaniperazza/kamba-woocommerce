<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function phpAlert($msg) {
    echo '<script type="text/javascript">alert("' . $msg . '")</script>';
}

Class WP_Gateway_Kamba extends WC_Payment_Gateway{
	
	private $testmode;
	private $merchant_id;
	private $api_key;
	
	
	public function __construct()
	{
		$this->id                   = "kamba-woocommerce";
		$this->icon                 = "https://avatars0.githubusercontent.com/u/29748778?s=200&v=4";
		$this->has_fields           = false;
		$this->method_title         = "Kamba Checkout";
		$this->method_description   = "Recargas de saldo e pagamentos na Internet com dinheiro";
	
		$this->init_form_fields();
		$this->init_settings();
		
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->testmode         = 'yes' === $this->get_option( 'testmode', 'no' );
		$this->merchant_id      = $this->get_option( 'client_id' );
		$this->api_key          = $this->get_option( 'client_secret' );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}
	
	public function init_form_fields()
	{
		$this->form_fields = include("kamba-settings.php");		
	}

	
	public function process_payment($orderId)
	{
		include_once "lib/KambaCheckout.php";
		phpAlert("Creating Kamba Checkout Order for order id: $orderId");
		phpAlert("Merchant ID: $this->merchant_id | API Key: $this->api_key  | Testmode: $this->testmode ");
		
		$order = new WC_Order( $orderId );
		// Get an instance of the WC_Order object
		$product_names = array();

		// Iterating through each WC_Order_Item_Product objects
		foreach ($order->get_items() as $item_key => $item_values) {
			// Item ID is directly accessible from the $item_key in the foreach loop or
			$item_id = $item_values->get_id();
			## Using WC_Order_Item_Product methods ##
			$item_name = $item_values->get_name(); // Name of the product
			array_push($product_names, $item_name);
			$item_type = $item_values->get_type(); // Type of the order item ("line_item")

			$product_id = $item_values->get_product_id(); // the Product id
			$product = $item_values->get_product(); // the WC_Product object
			## Access Order Items data properties (in an array of values) ##
			$item_data = $item_values->get_data();

			$product_name = $item_data['name'];
			$product_id = $item_data['product_id'];
		
		}

		try {
			
			$request = new KambaCheckout($this->merchant_id, $this->api_key, $this->testmode);
            
            $request_data['channel']                = "WEBSITE";
            $request_data['currency'] 		        = "AOA";
			$request_data['initial_amount'] 	    = $this->get_order_total();
			$request_data['notes'] 		            = $product_names;
			$request_data['redirect_url_success'] 	= site_url() ."/checkout/paywithkamba/";
            $request_data['payment_method']         = "WALLET";
			$response = $request->createCheckout(json_encode($request_data));
            
			if(isset($response))
			{	phpAlert("is set!");
				$response = json_decode($response);
				$url = $response->redirect_url_success;
				WC()->session->set( 'payment_request_id',  $response->id);	
				phpAlert($url);
				
				return array(
                    'result'    => 'success', 
                    'redirect'  => $url
				);
			}
		
		} catch(CurlException $e) {
			phpAlert("An error occurred on line " . $e->getLine() . " with message " .  $e->getMessage());
			phpAlert("Traceback: " . (string)$e);
			$json = array(
				"result"    =>  "failure",
				"messages"  =>  "<ul class=\"woocommerce-error\">\n\t\t\t<li>" . $e->getMessage() . "</li>\n\t</ul>\n",
				"refresh"   =>  "false",
				"reload"    =>  "false"
				);
			phpAlert($json);
			die(json_encode($json));
		} catch(ValidationException $e) {
			phpAlert("Validation Exception Occured with response ".print_r($e->getResponse(), true));
			$errors_html = "<ul class=\"woocommerce-error\">\n\t\t\t";
			foreach( $e->getErrors() as $error)
			{
				$errors_html .="<li>".$error."</li>";
				
			}
			$errors_html .= "</ul>";
			$json = array(
				"result"    =>  "failure",
				"messages"  =>  $errors_html,
				"refresh"   =>  "false",
				"reload"    =>  "false"
				);
				phpAlert($json);
			die(json_encode($json));
		}
		catch(Exception $e){
			
			phpAlert("An error occurred on line " . $e->getLine() . " with message " .  $e->getMessage());
			phpAlert("Traceback: " . $e->getTraceAsString());
			$json = array(
				"result"    =>  "failure",
				"messages"  =>  "<ul class=\"woocommerce-error\">\n\t\t\t<li>".$e->getMessage()."</li>\n\t</ul>\n",
				"refresh"   =>  "false",
				"reload"    =>  "false"
				);
			phpAlert($json);
			die(json_encode($json));
			
		}
	}
	
	public static function log( $message ) 
	{
		kamba_log($message);
	}
	
}