<?php
/**
 * Plugin Name: Pardakhtpal Gateway For Pro-VIP
 * Plugin URI: -
 * Description: This plugin lets you use pardakhtpal gateway in pro-vip wp plugin.
 * Version: 1.0
 * Author: Farhan
 * Author URI: wp-src.ir
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
defined( 'ABSPATH' ) OR exit;

if( !function_exists( 'init_pardakhtpal_gateway_pv_class' ) ){

    add_action( 'plugins_loaded', 'init_pardakhtpal_gateway_pv_class' );

    function init_pardakhtpal_gateway_pv_class(){
        add_filter( 'pro_vip_currencies_list', 'currencies_check' );
        
        function currencies_check( $list ) {
            if(!in_array('IRT', $list)){
                $list[ 'IRT' ] = array(
                    'name'   => 'تومان ایران',
                    'symbol' => 'تومان'
                );
            }
            
            if( !in_array('IRR', $list) ){
                $list[ 'IRR' ] = array(
                    'name'   => 'ریال ایران',
                    'symbol' => 'ریال'
                );
            }
            return $list;
        }

        if( class_exists( 'Pro_VIP_Payment_Gateway' ) && !class_exists( 'Pro_VIP_Pardakhtpal_Gateway' ) ){
            
            class Pro_VIP_Pardakhtpal_Gateway extends Pro_VIP_Payment_Gateway{
                
                public
                $id = 'pardakhtpal',
                $settings = array(),
                $frontendLabel = 'پرداخت پال',
                $adminLabel = 'پرداخت پال';
                
                public function __construct() {
                    parent::__construct();
                }
                
                public function beforePayment( Pro_VIP_Payment $payment ) {
                    
                    $target_url = 'http://www.pardakhtpal.com/WebService/WebService.asmx?wsdl';

                    $API = $this->settings['api_key']; //Required 
                    $amount = intval( $payment->price ); // Required
                    $orderId = $payment->paymentId; // Required 
                    $description = 'پرداخت فاکتور به شماره ی' . $orderId; // Required 
                    $callbackURL = add_query_arg( 'order', $orderId, $this->getReturnUrl() ); // $this->getReturnUrl();
                    //$currency = $order->get_order_currency();
                    
                    if ( pvGetOption( 'currency' ) === 'IRT' ) {
                        $amount *= 10;
                    }
                    
                    $client = new SoapClient( $target_url ); 
                    
                    $params = array( 'API' => $API , 'Amount' => $amount, 'CallBack' => $callbackURL, 'OrderId' => $orderId, 'Text' => $description );
                    
                    $res = $client->requestpayment( $params );
                    $Result = $res->requestpaymentResult;
                    
                    if( strlen( $Result ) == 8 ){
                        
                        $payment->key  = $orderId;
                        $payment->user = get_current_user_id();
                        $payment->save();
                        
                        $payment_url = 'http://www.pardakhtpal.com/payment/pay_invoice/';
                        
                        Header( "Location: $payment_url" . $Result ); 
                    }
                    else{
                        pvAddNotice( 'خطا در هنگام اتصال به پرداخت پال.' );
                        return;
                    }
                }

                public function afterPayment() {
                    if ( isset($_GET['order']) ) 
                        $orderId = $_GET['order'];
                    else
                        $orderId = 0;
                    
                    if ( $orderId ) {
                        $payment = new Pro_VIP_Payment( $orderId );
                        $API = $this->settings['api_key']; //Required 
                        $amount = intval( $payment->price ); //  - ریال به مبلغ Required 
                        $authority = $_POST['au'];
                        
                        if ( pvGetOption( 'currency' ) === 'IRT' ) {
                            $amount *= 10;
                        }
                        
                        if( strlen( $authority ) > 4 ){ 
                            $target_url = 'http://www.pardakhtpal.com/WebService/WebService.asmx?wsdl';
                            
                            $client = new SoapClient( $target_url ); 

                            $params = array( 'API' => $API , 'Amount' => $amount, 'InvoiceId' => $authority ); 

                            $res = $client->verifypayment( $params ); 
                            $Result = $res->verifypaymentResult; 
                            
                            if( $Result == 1 ){
                                pvAddNotice( 'پرداخت شما با موفقیت انجام شد. کد پیگیری: ' . $orderId, 'success' );
                                $payment->status = 'publish';
                                $payment->save();
                                
                                $this->paymentComplete( $payment );
                            }
                            else{
                                pvAddNotice( 'خطایی به هنگام پرداخت پیش آمده. کد خطا عبارت است از :' . $Result . ' . برای آگاهی از دلیل خطا کد آن را به پرداخت پال ارائه نمایید.' );
                                $this->paymentFailed( $payment );

                                return false;
                            }
                        }
                        else{
                            pvAddNotice( 'به نظر می رسد عملیات پرداخت توسط شما لغو گردیده، اگر چنین نیست مجددا اقدام به پرداخت فاکتور نمایید.' );
                            $this->paymentFailed( $payment );

                            return false;
                        }
                    }
                }
                
                public function adminSettings( PV_Framework_Form_Builder $form ) {

                    $form->textfield( 'api_key' )->label( 'کلید API' );
                }
            }
            
            Pro_VIP_Payment_Gateway::registerGateway( 'Pro_VIP_Pardakhtpal_Gateway' );
        }
    }
}
?>