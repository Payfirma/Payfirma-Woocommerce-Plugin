<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly


/**
 * Validate checkout checks for Credit Card and CVV2 numbers.  Shows formatted errors if blank.
 */

add_action('woocommerce_checkout_process', 'payfirma_checkout_field_checks');

function payfirma_checkout_field_checks() {
    global $woocommerce;

    // Check if set, if its not set add an error.
    if (!$_POST['card_number'] && $_POST['payment_method']=='payfirma_gateway' ){
        wc_add_notice( 'Please enter your <strong>Credit Card number</strong>.', $notice_type = 'error' );
    } 
    
    if ($_POST['card_number'] && $_POST['payment_method']=='payfirma_gateway' ){

        $cardNumber = str_replace(' ', '', sanitize($_POST['card_number']));
        if (!is_numeric($cardNumber)){
            wc_add_notice( '<strong>Credit Card number</strong> includes characters.', $notice_type = 'error' );
        }
    } 
    	
    if (!$_POST['cvv2'] && $_POST['payment_method']=='payfirma_gateway'){
        wc_add_notice( 'Please enter your <strong>CVV2 number</strong>', $notice_type = 'error' ); 
    }

    if ($_POST['cvv2'] && $_POST['payment_method']=='payfirma_gateway'){
        if (!is_numeric($_POST['cvv2'])){
            wc_add_notice( '<strong>CVV2 number</strong> includes characters.', $notice_type = 'error' ); 
        }
    }
}


/**
 * Strips input of inappropriate characters
 * @param $input
 * @return mixed
 */
function cleanInput($input) {

    $search = array(
        '@<script[^>]*?>.*?</script>@si',   // Strip out javascript
        '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
        '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
        '@<![\s\S]*?--[ \t\n\r]*>@'         // Strip multi-line comments
    );

    $output = preg_replace($search, '', $input);
    return $output;
}

/**
 * Clean input (both arrays and strings)
 * @param $input
 * @return mixed|string
 */
function sanitize($input) {
    if (is_array($input)) {
        foreach($input as $var=>$val) {
            $output[$var] = sanitize($val);
        }
    }
    else {

        $input = stripslashes($input);
        $input  = cleanInput($input);

    }
    return $input;
}

/**
 * Class WC_Gateway_Payfirma - Handles all Payfirma Payment Gateway functionality
 */
class WC_Gateway_Payfirma extends WC_Payment_Gateway
{
    public $id;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $client_secret;
    public $client_id;
    public $keys_val;
    public $http_force;
    public $env_error;
    public $disablegateway_js;
    public $sslcheck;
    public $forcesslchecked;
    public $api_info_valid;
    public $currency_valid;
    public $enabled;

    public function __construct()
    {

        global $woocommerce;

        // define variables
        $this->id = 'payfirma_gateway';
        $this->has_fields = true; //  – puts payment form fields into payment options on payment page.
        $this->method_title = __('Payfirma', 'woocommerce'); //– Title of the payment method shown on the admin page.
        $this->method_description = 'Use Payfirma to process your payments.'; //– Description for the payment method shown on the admin page.
        // load the settings
        $this->init_form_fields();
        $this->init_settings();

        // define user set variables

        $this->title = $this->get_option('title');
        //$this->enabled = $this->get_option('enabled');
        $this->description = $this->get_option('description');
        $this->client_secret = $this->get_option('client_secret');
        $this->client_id = $this->get_option('client_id');
        $this->keys_val = get_option('woocommerce_payfirma_gateway_keys_val');
        //$this->http_force = get_option('woocommerce_unforce_ssl_checkout');
        $this->http_force = 'no';
        
        $this->env_error = 'false';
        $this->disablegateway_js ='';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // if force http after checkout is checked don't run SSL checkout page validation
        if ($this->http_force != true) {
            $this->sslcheck = check_ssl_checkoutpage();
        } else {
            $this->sslcheck = 'na';
        }

        // check if force ssl option is checked
        $this->forcesslchecked = force_ssl_checked();

        // check for existing client_secret key signature
        if (is_array($this->keys_val) && $this->keys_val['status'] == 'true'):


            if ($this->client_id == $this->keys_val['client_id'] && $this->client_secret == $this->keys_val['client_secret']):

                // if they exist and entered keys = saved keys
                $this->api_info_valid = 'true';

            else:

                $this->api_info_valid = validate_payfirma_api($this->client_id, $this->client_secret);

            endif;
        else:

            $this->api_info_valid = validate_payfirma_api($this->client_id, $this->client_secret);

        endif;

        // check if currency is valid
        $this->currency_valid = $this->is_currency_valid();

        if ($this->sslcheck == 'false' || $this->forcesslchecked != 'true' || $this->api_info_valid != 'true' || $this->http_force !='no' || $this->currency_valid=='false') {

            // disable gateway
            $this->enabled = false;

            // include jquery to uncheck enabled box
            $this->disablegateway_js = '<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $( "#woocommerce_payfirma_gateway_enabled" ).prop( "checked", false );
            });
            </script>';

            // process errors
            $this->env_error = $this->gen_payfirma_error();
        }
    }



    /**
     * Create the settings page for the Payfirma Payment Gateway.
     *
     * @access public
     */
    public function admin_options()
    {
    ?>

        <script type="text/javascript">

            // validate the form to prevent activating the gateway without the data needed for it to work.

            jQuery(document).ready(function () {
                jQuery("#mainform").validate({
                    rules: {
                        woocommerce_payfirma_gateway_client_secret: {
                            required: {
                                depends: function(element) {
                                    return jQuery("input[name='woocommerce_payfirma_gateway_enabled']:checked").length == 1
                                }
                            }
                        },
                        woocommerce_payfirma_gateway_client_id: {
                            required: {
                                depends: function(element) {
                                    return jQuery("input[name='woocommerce_payfirma_gateway_enabled']:checked").length == 1
                                }
                            }
                        }
                    },
                    messages: {
                        woocommerce_payfirma_gateway_client_secret: {
                            required: "Please enter your Payfirma Client Secret"
                        },
                        woocommerce_payfirma_gateway_client_id: {
                            required: "Please enter your Payfirma Client ID"
                        }
                    }
                });
            });

        </script>
        <?php

        // load disabling jquery if gateway disabled
        echo $this->disablegateway_js;

        ?>
        <h3><?php _e('Payfirma Payments for WooCommerce', 'woocommerce'); ?></h3>
        <p><?php _e('Payfirma works by processing the <strong>credit card</strong> payment via your Payfirma HQ account.', 'woocommerce'); ?></p>

            <table class="form-table">
                <?php

                // load error message if errors
                if($this->env_error !='false'){
                    echo $this->env_error;
                }

                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

        <?php
    }

    /**
     * Run gateway error checks and if errors, create error message
     * @return string
     */
    public function gen_payfirma_error(){

        $errortext = '';
        $errors = array();

        // if force http after checkout is checked
        if($this->http_force !='no') {
            $errors[] = 'Please uncheck the "Force HTTP when leaving the checkout" option located on
                     the <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=advanced">advanced Options</a> page.';
        }

        // if force ssl option is not checked
        if($this->forcesslchecked !='true'){
            $errors[] = 'Please check the "Force Secure Checkout" box on the
                <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=advanced">Checkout Options</a> advanced tab.';
        }

        // if currency is invalid
        if ($this->currency_valid != 'true') {
            $errors[] = 'Supported currencies are CAD and USD. Please switch to a supported currency on the
                <a href="' . get_admin_url() . 'admin.php?page=wc-settings&tab=general">General Options</a> tab.';
        }

        // if form submitted run additional checks
        if($_POST) {

            // if SSL on checkout page check fails
            if ($this->sslcheck == 'false') {
                $errors[] = 'Please install a valid SSL certificate at your domain, for use with the checkout page';
            }

            // if Payfirma API credentials are invalid
            if($this->api_info_valid!='true'){
                $errors[] = 'Please re-enter your Client ID and Client Secret.';
            }

        }

        // if error(s) found return error message
        if(!empty($errors)){
            $errortext = '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'woocommerce') . '</strong>:<br />';

            foreach($errors as $error){
                $errortext .= __($error, 'woocommerce').'<br />';
            }
            $errortext.='</p></div>';
        }

        return $errortext;
    }

    /**
     * Check if the selected currency is valid for the Payfirma Gateway.
     *
     * @access public
     * @return bool
     */
    public function is_currency_valid()
    {
        if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_paypal_supported_currencies', array('CAD', 'USD')))) return 'false';

        return 'true';
    }

    /**
     * Show the Payfirma Gateway Settings Form Fields in the Settings page
     *
     * @access public
     * @return void
     */
    public function init_form_fields()
    {

        // set the form fields array for the Payfirma Payment Gateway settings page.
        $this->form_fields = array(

            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Credit Card', 'woocommerce'),
            ),
            'description' => array(
                'title' => __('Description:', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                'default' => __('Pay securely with your Credit Card.', 'woocommerce'),
            ),
            'client_id' => array(
                'title' => __('Client ID', 'woocommerce'),
                'type' => 'text',
                'description' => __('*Your Client ID can be found and changed via the <a href="https://hq.payfirma.com/#settings/view/ecommerce">eCommerce Settings page of PayHQ.</a>', 'woocommerce'),
                'default' => ''
            ),
            'client_secret' => array(
                'title' => __('Client Sercet', 'woocommerce'),
                'type' => 'text',
                'description' => __('*Your Client Sercet can be found and changed via the <a href="https://hq.payfirma.com/#settings/view/ecommerce">eCommerce Settings page of PayHQ.</a>', 'woocommerce'),
                'default' => ''
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Payfirma', 'woocommerce'),
                'default' => 'no'
            )
        );

    }

    /**
     * Initialise the payment fields on the front-end payment form
     *
     * @access public
     */
    public function payment_fields()
    {
        // ** use payfirma form_field to create new form fields.

       global $woocommerce;

        echo'<p>' . __('Credit Card Number*:', 'woocommerce') . ' <input type="text" name="card_number" id="card_number" class="payfirma_card_number"/></p>
            <p class="payfirma_card_input_padding">' . __('Expires Month*: ', 'woocommerce') . $this->payfirma_select_month_list() .' &nbsp;'.
            __('Year*: ', 'woocommerce') . $this->payfirma_select_year_list() . ' </p>
         <p>' . __('Security Code*:', 'woocommerce') . ' <input type="text" size="5" name="cvv2" id="cvv2" /> <img src="'.plugins_url().'/Payfirma_Woo_Gateway/img/pf13-logo.png"></p>';
       
        ?> 
            <script type="text/javascript">
                // ADD CARD ACTION
                jQuery(document).ready(function () {
                    jQuery("#card_number").inputmask({"mask": "9999 9999 9999 9999"});
                });
            </script>
    <?php
    
    }


    /**
     * Generate month selector for payment form
     *
     * @access public
     * @return string
     */
    public function payfirma_select_month_list()
    {
        $return = '<select name="card_expiry_month" class="payfirma_dropdown">';

        // build the dropdown with months 1-12
        for ($i = 1; $i <= 12; $i++) {

            $value = $i;
            if($i < 10 ) {
                $value = "0".$i;
            }
            $return .= '<option value="' . $value . '">' . $value . '</option>';
        }

        $return .= '</select>';

        // return full dropdown html
        return $return;
    }

    /**
     * Generate year selector for payment form
     *
     * @access public
     * @return string
     */
    public function payfirma_select_year_list()
    {
        $return = '<select name="card_expiry_year" class="payfirma_dropdown">';

        // current year
        $year = date('Y');

        // only go out 10 years in the list
        $year_end = $year + 10;

        // build the dropdown with years current through current+10.
        for ($i = $year; $i <= $year_end; $i++) {
            $return .= '<option value="' . substr($i, 2, 3) . '">' . $i . '</option>';
        }

        $return .= '</select>';

        // return the full dropdown html
        return $return;
    }

    /**
     * Process Payfirma Payment
     *
     * @access public
     * @param $order_id
     * @return array
     */
    function process_payment($order_id)
    {
        global $woocommerce;

        // get the order by order_id
        $order = new WC_Order($order_id);

        // get all of the args  -> see get_paypal_args
        $payfirma_args = $this->get_payfirma_args($order);

        //CHECK VALIDATION CARD NUMBER AND CVV 


        // Get access_token from v2 auth
        $access_token = $this->get_access_token();
        if($access_token == null){

            wc_add_notice( '<strong>Authentication Failed:</strong>Invalid token for payment', $notice_type = 'error' );
            return;
        }

        // send data to Payfirma
        $payfirma_result = $this->post_to_payfirma($payfirma_args, $access_token);

       if ($payfirma_result['transaction_result'] === 'APPROVED'):

            // mark payment as complete
            $order->payment_complete();

            // Reduce stock levels - May be obsolete in newer versions of WooCommerce
            //$order->reduce_order_stock();

            // Remove cart
            $woocommerce->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );

       // payment declined
       elseif ($payfirma_result['transaction_result'] === 'DECLINED'):
           $error_message = 'Your payment declined.  Please enter your payment details again.';
           wc_add_notice( '<strong>Payment declined:</strong>Please enter your payment details again', $notice_type = 'error' );
           return;

       // API issue
       else:
           wc_add_notice( '<strong>Payment error:</strong>Please enter your payment details again', $notice_type = 'error' );
           return;
       endif;

    }

    /**
     * Build array of args to send to Payfirma for processing
     *
     * @access private
     * @return array
     */
    private function get_payfirma_args($order)
    {
        global $woocommerce;
        $version_num = get_woo_version();

        $cardNumber = str_replace(' ', '', sanitize($_POST['card_number']));

        // payfirma required arguments
        $payfirma_args = json_encode(array(
            'first_name' => $order->billing_first_name,
            'last_name' => $order->billing_last_name,
            'company' => $order->billing_company,
            'address1' => $order->billing_address_1,
            'address2' => $order->billing_address_2,
            'city' => $order->billing_city,
            'province' => $order->billing_state,
            'postal_code' => $order->billing_postcode,
            'country' => $order->billing_country,
            'email' => $order->billing_email,
            'amount_tax' => $order->get_total_tax(),
            'amount' => $order->order_total,
            'order_id' => $order->id,
            'currency' => get_woocommerce_currency(),
            'telephone'=> $order->billing_phone,
            // from the cc form on the payment page.
            'card_number' => $cardNumber,
            'card_expiry_month' => sanitize($_POST['card_expiry_month']),
            'card_expiry_year' => sanitize($_POST['card_expiry_year']),
            'cvv2' => sanitize($_POST['cvv2']),
            'description'=>'Order #'.$order->id.' from '.get_bloginfo('url'),
			'do_not_store'=>'true',
            'ecom_source'=>'WooCommercev'.$version_num
        ));

        // return the array of arguments
        return $payfirma_args;
    }

    /**
     * Open a cURL connection to Payfirma API, post values, return the result object
     *
     * @access private
     * @return object
     */
    private function post_to_payfirma($payfirma_args, $access_token)
    {
        $sale_url = 'https://apigateway.payfirma.com/transaction-service/sale';

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $sale_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payfirma_args,
            CURLOPT_HTTPHEADER => array(
            "authorization: Bearer ".$access_token,
            "cache-control: no-cache",
            "Content-Type:application/json"
            ),
        ));

        //execute post
        $result = curl_exec($curl);

        //close connection
        curl_close($curl);

        //parse the result into an object
        $json_feed_object = json_decode($result, true);
    
        return $json_feed_object;
    }


    private function get_access_token(){

        $client_id = $this->client_id;
        $client_secret = $this->client_secret;
    
        $token_url = "https://auth.payfirma.com/oauth/token?grant_type=client_credentials";
        $authroization_encoded = base64_encode($client_id.":".$client_secret);  
    
        // Initialize session and set URL.
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $token_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => array(
            "authorization: Basic ".$authroization_encoded,
            "cache-control: no-cache",
            "Content-Type:application/x-www-form-urlencoded"
            ),
        ));
    
        // since we won't know the file location to allow verifypeer, disable this check
        //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
        // execute the cURL
        $curl_result = curl_exec($ch);
    
        // Get the response and close the channel.
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
    
        if($httpcode == 200){
            $responseArray = json_decode($curl_result, true);           
            return $responseArray['access_token'];
        }else{
            return null;
        }
    }
}

