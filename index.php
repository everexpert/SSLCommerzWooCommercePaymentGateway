<?php
/*
	Plugin Name: SSLCommerz WooCommerce Payment Gateway
	Plugin URI: http://SSLCommerz.com
	Description: SSLCommerz Woocommerce Payment Gateway allows you to accept payment on your Woocommerce store via Visa Cards, Mastercards, Ameriacan Express.
	Version: 1.0.3
	Author: JM Redwan
    Copyright: © 20015-2016 SSLCommerz.
    License: GNU General Public License v3.0
    License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
    if ( ! defined( 'ABSPATH' ) )
        exit;
    add_action('plugins_loaded', 'woocommerce_sslcommerz_init', 0);

    function woocommerce_sslcommerz_init() {

        if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

    /**
     * Gateway class
     */
    class WC_sslcommerz extends WC_Payment_Gateway {
        public function __construct(){

            // Go wild in here
            $this -> id           = 'sslcommerz';
            $this -> method_title = __('sslcommerz', 'suman');
            $this -> icon         =  plugins_url( 'images/SSLCommerz.png' , __FILE__ );
            $this -> has_fields   = false;
            
            $this -> init_form_fields();
            $this -> init_settings();

             $this -> title            = $this -> settings['title'];
            $this -> description      = $this -> settings['description'];
            $this -> merchant_id      = $this -> settings['merchant_id'];
	$this -> store_password   = $this -> settings['store_password'];
           $this->testmode              = $this->get_option( 'testmode' );
            $this->testurl           = 'https://sandbox.sslcommerz.com/gwprocess/v3/process.php';
            $this -> liveurl  = 'https://securepay.sslcommerz.com/gwprocess/v3/process.php';
            $this->notify_url        = WC()->api_request_url( 'WC_sslcommerz' );
            $this -> redirect_page_id = $this -> settings['redirect_page_id'];
            $this -> msg['message'] = "";
            $this -> msg['class']   = "";
            
            //add_action('init', array(&$this, 'check_SSLCommerz_response'));
            //update for woocommerce >2.0
           // add_action( 'woocommerce_api_wc_sslcommerz', array( $this, 'check_SSLCommerz_response' ) );
            add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_SSLCommerz_response' ) );
            add_action('valid-SSLCommerz-request', array($this, 'successful_request'));
            if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            } else {
                add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
            }
            add_action('woocommerce_receipt_SSLCommerz', array($this, 'receipt_page'));
           // add_action('woocommerce_thankyou_SSLCommerz',array($this, 'thankyou_page'));
        }

        function init_form_fields(){

            $this -> form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'jmredwan'),
                    'type' => 'checkbox',
                    'label' => __('Enable SSLCommerz Payment Module.', 'jmredwan'),
                    'default' => 'no'),
                'title' => array(
                    'title' => __('Title:', 'jmredwan'),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'jmredwan'),
                    'default' => __('SSLCommerz', 'jmredwan')),
                'description' => array(
                    'title' => __('Description:', 'jmredwan'),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'jmredwan'),
                    'default' => __('Pay securely by Credit or Debit card or internet banking through SSLCommerz Secure Servers.', 'jmredwan')),
                'merchant_id' => array(
                    'title' => __('Store ID', 'jmredwan'),
                    'type' => 'text',
                    'description' => __('ACCESS CREDENTIALS')),
			
			 'store_password' => array(
                    'title' => __('Store Password', 'jmredwan'),
                    'type' => 'text',
                    'description' => __('ACCESS CREDENTIALS!It is required at payment validation.Note: No need to change the store password')),

            'testmode' => array(
                            'title'       => __( 'SSLCommerz sandbox', 'woocommerce' ),
                            'type'        => 'checkbox',
                            'label'       => __( 'Enable SSLCommerz sandbox', 'woocommerce' ),
                            'default'     => 'no',
                            'description' => __( 'SSLCommerz sandbox can be used to test payments.' ),
                    ),
					
					
					
                  'fail_page_id' => array(
                    'title' => __('Return Page Fail'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of Fail page"
                ),
                'redirect_page_id' => array(
                    'title' => __('Return Page'),
                    'type' => 'select',
                    'options' => $this -> get_pages('Select Page'),
                    'description' => "URL of success page"
                )
                );


}
        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
        public function admin_options(){
            echo '<h3>'.__('SSLCommerz Payment Gateway', 'suman').'</h3>';
            echo '<p>'.__('SSLCommerz is most popular payment gateway for online shopping in Bangladesh').'</p>';
            echo '<table class="form-table">';
            $this -> generate_settings_html();
            echo '</table>';

        }
        /**
         *  There are no payment fields for SSLCommerz, but we want to show the description if set.
         **/
        function payment_fields(){
            if($this -> description) echo wpautop(wptexturize($this -> description));
        }
        /**
         * Receipt Page
         **/
        function receipt_page($order){

            echo '<p>'.__('Thank you for your order, please click the button below to pay with SSLCommerz.', 'suman').'</p>';
            echo $this -> generate_SSLCommerz_form($order);
        }
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
            $order = new WC_Order($order_id);
            return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url( true ));
        }
        /**
         * Check for valid SSLCommerz server callback completed
         **/
        function check_SSLCommerz_response(){
            global $woocommerce;
            $info = explode("_", $_REQUEST['tran_id']);
            $order_id=$info[0];

           $order = wc_get_order($info[0] );
           $fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
           $fail_url = add_query_arg( 'wc-api', get_class( $this ), $fail_url );
           //print_r($_POST);exit;

            if( isset($_REQUEST['tran_id'])){

				
                $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
                $fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
                $order_id = $info[0];
                $this -> msg['class'] = 'error';
                $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
				
				
				if(isset($_POST['val_id'])){
					$val_id = urldecode($_POST['val_id']); 
					}
				else {
					 $val_id = ''; 
					}
                //$val_id=0;
                 $store_id=urlencode($this -> merchant_id );
                 $store_passwd=urlencode($this ->store_password);
				 
				 
				 
				 
				 if(empty($val_id)){
						 if ('yes' == $this->testmode) { 
						  $valid_url_own = ("https://sandbox.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php?tran_id=".$order_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");  
						 
						  } else{
							 $valid_url_own = ("https://securepay.sslcommerz.com/validator/api/merchantTransIDvalidationAPI.php?tran_id=".$order_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json"); 
						  }
						 
			$ownvalid = curl_init();
			curl_setopt($ownvalid, CURLOPT_URL, $valid_url_own);
			curl_setopt($ownvalid, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ownvalid, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ownvalid, CURLOPT_SSL_VERIFYPEER, false);
			
			$ownvalid_result = curl_exec($ownvalid);
			
			$ownvalid_code = curl_getinfo($ownvalid, CURLINFO_HTTP_CODE);
			
			if($ownvalid_code == 200 && !( curl_errno($ownvalid)))
			{
				$result_own = json_decode($ownvalid_result, true);
				$lastupdate_no = $result_own['no_of_trans_found']-1;	
				$own_data = $result_own['element']; 
				$val_id = $own_data[$lastupdate_no]['val_id'];
				//echo $own_data[0]['val_id'];
			}
						 
					 
						 
}


			
                if ('yes' == $this->testmode) { 
                $requested_url = ("https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");  
                } else{
               $requested_url = ("https://securepay.sslcommerz.com/validator/api/validationserverAPI.php?val_id=".$val_id."&Store_Id=".$store_id."&Store_Passwd=".$store_passwd."&v=1&format=json");
                }  
				
                //$amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
                $handle = curl_init();
curl_setopt($handle, CURLOPT_URL, $requested_url);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);

$result = curl_exec($handle);

$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if($code == 200 && !( curl_errno($handle)))
{	
                    # TO CONVERT AS ARRAY
	# $result = json_decode($result, true);
	# $status = $result['status'];	
	
	# TO CONVERT AS OBJECT
	$result = json_decode($result);
		//print_r($result);
	# TRANSACTION INFO
	$status = $result->status;	
	$tran_date = $result->tran_date;
	$tran_id = $result->tran_id;
	$val_id = $result->val_id;
	$amount = $result->amount;
	$store_amount = $result->store_amount;
	$bank_tran_id = $result->bank_tran_id;
	$card_type = $result->card_type;
	
	# ISSUER INFO
	$card_no = $result->card_no;
	$card_issuer = $result->card_issuer;
	$card_brand = $result->card_brand;
	$card_issuer_country = $result->card_issuer_country;
	$card_issuer_country_code = $result->card_issuer_country_code;   
	
	//Payment Risk Status
	$risk_level = $result->risk_level;
	$risk_title = $result->risk_title;
	$message = '';
	
				
					$message .= 'Payment Status = ' . $status . "\n";
				    
					$message .= 'Bank txnid = ' . $bank_tran_id . "\n";
				   
					$message .= 'Your Oder id = ' . $tran_id . "\n";
					
					$message .= 'Payment Date = ' . $tran_date . "\n";  
				   
					$message .= 'Card Number = ' .$card_no . "\n"; 
				   
					$message .= 'Card Type = ' .$card_brand .'-'. $card_type . "\n"; 
				    
					$message .= 'Transaction Risk Level = ' .$risk_level . "\n"; 
				   
					$message .= 'Transaction Risk Description = ' .$risk_title . "\n"; 

                    if($status=='VALID')
                    {
                        if($risk_level==0){ $pay_status = 'success';}
                        if($risk_level==1){ $pay_status = 'risk';} 
                    }
                    elseif($status=='VALIDATED'){
                        if($risk_level==0){ $pay_status = 'success';}
                        if($risk_level==1){ $pay_status = 'risk';} 
                     }
                    else
                    {
                         $pay_status = 'failed';
                    }
                }
         
                if($order_id != ''){
                    try{
                         $order = wc_get_order($info[0] );
                        $merchant_id = $_REQUEST['[tran_id'];
                        $amount = $_REQUEST['amount'];
                       
                        $transauthorised = false;
                        //echo $pay_status;exit;
                                if($pay_status=="success"){
                                    //echo 'hi';exit;
                                    $transauthorised = true;
                                    $this -> msg['message'] = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be shipping your order to you soon.";
                                    $this -> msg['class'] = 'success';
                                    $order -> update_status('Processing');
                                        $order -> payment_complete();
                                        $order -> add_order_note($message);
                                        $order -> add_order_note($this->msg['message']);
                                        $woocommerce -> cart -> empty_cart();
                                     $return_url = $order->get_checkout_order_received_url();
                                    $redirect_url  = str_replace( 'http:', 'http:', $return_url );
                                   
                                }
								else if($pay_status=="risk"){
                                    $order -> update_status('on-hold');
                                    $order -> add_order_note($message);
                                    $this -> msg['message'] = "Thank you for shopping with us. However, Your account has been charged and your transaction is Pendding. After Geting Verified from SSLCommerz. It will updated soon. Please Co-Operate with SSLCommerz.";
                                    $this -> msg['class'] = 'Failed';
                                   //$woocommerce -> cart -> empty_cart();
                                   //wc_add_notice( __( 'Unfortunately your card was declined and the order could not be processed. Please try again with a different card or payment method.', 'Error' ) );
                                   wc_add_notice( __( 'Unfortunately your card was declined and the order could not be processed. Please try again with a different card or payment method.', 'woocommerce' ), 'error' );
                                   $redirect_url  = $order->get_cancel_order_url();
                                    
                                   $redirect_url  = $fail_url;
                                }
								else if($pay_status=="failed"){
                                    $order -> update_status('failed');
                                    $order -> add_order_note($message);
                                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been Failed.";
                                    $this -> msg['class'] = 'Failed';
                                   //$woocommerce -> cart -> empty_cart();
                                   //wc_add_notice( __( 'Unfortunately your card was declined and the order could not be processed. Please try again with a different card or payment method.', 'Error' ) );
                                   wc_add_notice( __( 'Unfortunately your card was declined and the order could not be processed. Please try again with a different card or payment method.', 'woocommerce' ), 'error' );
                                   $redirect_url  = $order->get_cancel_order_url();
                                    
                                   $redirect_url  = $fail_url;
                                }
                                else{
                                    $this -> msg['class'] = 'error';
                                    $this -> msg['message'] = "Thank you for shopping with us. However, the transaction has been declined.";
                                    //Here you need to put in the routines for a failed
                                    //transaction such as sending an email to customer
                                    //setting database status etc etc
                                }
                           
                            //removed for WooCOmmerce 2.0
                            //add_action('the_content', array(&$this, 'showMessage'));
                        }catch(Exception $e){
                            // $errorOccurred = true;
                            $msg = "Error";
                        }

                }
               
                wp_redirect( $redirect_url );
            }


}
       /*
        //Removed For WooCommerce 2.0
       function showMessage($content){
            return '<div class="box '.$this -> msg['class'].'-box">'.$this -> msg['message'].'</div>'.$content;
        }*/
        /**
         * Generate SSLCommerz button link
         **/
        public function generate_SSLCommerz_form($order_id){
            global $woocommerce;
            $order = new WC_Order($order_id);
            $order_id = $order_id.'_'.date("ymds");
            $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);
            $fail_url = ($this -> fail_page_id=="" || $this -> fail_page_id==0)?get_site_url() . "/":get_permalink($this -> fail_page_id);
           $redirect_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
            $fail_url = add_query_arg( 'wc-api', get_class( $this ), $fail_url );
            $declineURL = $order->get_cancel_order_url();
            $SSLCommerz_args = array(
                'store_id'      => $this -> merchant_id,
                'total_amount'           => $order -> order_total,
                'tran_id'         => $order_id,
                 'success_url' => $redirect_url,
                'fail_url' => $fail_url,
                'cancel_url' => $declineURL,
                'cus_name'     => $order -> billing_first_name .' '. $order -> billing_last_name,
                'cus_add1'  => trim($order -> billing_address_1, ','),
                'cus_country'  => wc()->countries -> countries [$order -> billing_country],
                'cus_state'    => $order -> billing_state,
                'cus_city'     => $order -> billing_city,
                'cus_postcode'      => $order -> billing_postcode,
                'cus_phone'      => $order->billing_phone,
                'cus_email'    => $order -> billing_email,
                'ship_name'    => $order -> shipping_first_name .' '. $order -> shipping_last_name,
                'ship_add1' => $order -> shipping_address_1,
                'ship_country' => $order -> shipping_country,
                'ship_state'   => $order -> shipping_state,
                'delivery_tel'     => '',
                'ship_city'    => $order -> shipping_city,
                'ship_postcode'     => $order -> shipping_postcode,
                'currency'         => get_woocommerce_currency()
                );

foreach($SSLCommerz_args as $param => $value) {
 $paramsJoined[] = "$param=$value";
}
   $paramsJoined = array();
            foreach($SSLCommerz_args as $key => $value){
                $paramsJoined[] = "<input type='hidden' name='$key' value='$value'/>";
            }
         //   print_r($paramsJoined);exit;
$SSLCommerz_args_array   = array();
//$SSLCommerz_args_array[] = "<input type='hidden' name='encRequest' value='$encrypted_data'/>";
//$SSLCommerz_args_array[] = "<input type='hidden' name='access_code' value='{$this->access_code}'/>";

wc_enqueue_js( '
    $.blockUI({
        message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to SSLCommerz to make payment.', 'woocommerce' ) ) . '",
        baseZ: 99999,
        overlayCSS:
        {
            background: "#fff",
            opacity: 0.6
        },
        css: {
            padding:        "20px",
            zindex:         "9999999",
            textAlign:      "center",
            color:          "#555",
            border:         "3px solid #aaa",
            backgroundColor:"#fff",
            cursor:         "wait",
            lineHeight:     "24px",
        }
    });
jQuery("#submit_SSLCommerz_payment_form").click();
' );
//jQuery("#submit_SSLCommerz_payment_form").click();
if ( 'yes' == $this->testmode ) {
                    $liveurl = $this->testurl ;
            } else {
                    $liveurl = $this->liveurl ;
            }
$form = '<form action="' . esc_url( $liveurl ) . '" method="post" id="SSLCommerz_payment_form" target="_top">
' . implode( '', $paramsJoined ) . '
<!-- Button Fallback -->
<div class="payment_buttons">
<input type="submit" class="button alt" id="submit_SSLCommerz_payment_form" value="' . __( 'Pay via SSLCommerz', 'woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
</div>
<script type="text/javascript">
jQuery(".payment_buttons").hide();
</script>
</form>';
return $form;



}


        // get all pages
function get_pages($title = false, $indent = true) {
    $wp_pages = get_pages('sort_column=menu_order');
    $page_list = array();
    if ($title) $page_list[] = $title;
    foreach ($wp_pages as $page) {
        $prefix = '';
                // show indented child pages?
        if ($indent) {
            $has_parent = $page->post_parent;
            while($has_parent) {
                $prefix .=  ' - ';
                $next_page = get_page($has_parent);
                $has_parent = $next_page->post_parent;
            }
        }
                // add to page list array array
        $page_list[$page->ID] = $prefix . $page->post_title;
    }
    return $page_list;
}

}


function my_woocommerce_add_error( $error ) {
    if( 'The generic error message' == $error ) {
        $error = 'The shiny brand new error message';
    }
    return $error;
}
add_filter( 'woocommerce_add_', 'my_woocommerce_add_error' );
    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_sslcommerz_gateway($methods) {
        $methods[] = 'WC_sslcommerz';
        //$methods[] = 'thankyou_page';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_sslcommerz_gateway' );
}

/*
SSLCommerz functions
 */


    //********** Hexadecimal to Binary function for php 4.0 version ********



?>
