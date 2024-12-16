<?php
class ControllerExtensionPaymentSyspaypayment extends Controller {
    private $module_name = 'syspaypayment';
    private $lang_prefix = '';
    private $module_path = '';
    private $id_prefix = '';
    private $setting_prefix = '';
    private $model_name = '';
    private $name_prefix = '';
    private $chosen_payment_session_name = 'chosen_payment';
    private $helper = null;

    

    // Constructor
    public function __construct($registry) {
        parent::__construct($registry);

        // Set the variables

        // payment
        $this->lang_prefix = $this->module_name .'_';
        $this->id_prefix = 'payment-' . $this->module_name;
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
        $this->module_path = 'extension/payment/' . $this->module_name;
        $this->model_name = 'model_extension_payment_' . $this->module_name;
        $this->name_prefix = 'payment_' . $this->module_name;
        $this->load->model($this->module_path);
        $this->{$this->model_name}->loadLibrary();
        $this->helper = $this->{$this->model_name}->getHelper();
        
    }

    // Checkout confirm order page
    public function index() {
        
        // PAYMENT
        if(true)
        {
            // Get the translations
            $this->load->language($this->module_path);
            $data['text_checkout_button'] = $this->language->get($this->lang_prefix . 'text_checkout_button');
            $data['text_title'] = $this->language->get($this->lang_prefix . 'text_title');
            $data['entry_payment_method'] = $this->language->get($this->lang_prefix . 'entry_payment_method');
            

            if (isset($this->session->data[$this->module_name][$this->chosen_payment_session_name]) === true) {
                $chosen_payment = $this->session->data[$this->module_name][$this->chosen_payment_session_name];
                $data['chosen_payemnt'] = $this->language->get($this->lang_prefix . 'text_' . $chosen_payment);
            } else {
                $data['chosen_payemnt'] = '';
            }

            // Set the view data
            $data['id_prefix'] = $this->id_prefix;
            $data['module_name'] = $this->module_name;
            $data['name_prefix'] = $this->name_prefix;
            $data['redirect_url'] = $this->url->link(
                $this->module_path . '/redirect',
                '',
                $this->url_secure
            );


            $view_data_name = $this->module_name . '_' . 'payment_methods';
            
            // Get SYS Pay payment methods
            $syspay_payment_methods = $this->config->get($this->setting_prefix . 'payment_methods');

            if (empty($syspay_payment_methods) === true) {
                $syspay_payment_methods = array();
            } else {
                // Get the translation of payment methods
                foreach ($syspay_payment_methods as $name) {
                    $lower_name = strtolower($name);
                    $lang_key = $this->lang_prefix . 'text_' . $lower_name;
                    $data[$view_data_name][$lower_name] = $this->language->get($lang_key);
                    unset($lang_key, $lower_name);
                }
            }
        }

       


        // var_dump($data);

        // Load the template
        $view_path = $this->module_path;
        return $this->load->view($this->module_path, $data);
    }

    // Ajax API to save chosen payment
    public function savePayment() {
        $function_name = __FUNCTION__;
        $white_list = array('cp');
        $inputs = $this->helper->only($_POST, $white_list);

        // Check the received variables
        if ($inputs === false) {
            $this->helper->echoJson(array('response' => $function_name . ' failed(1)'));
        }

        // Save chosen payment
        $this->session->data[$this->module_name][$this->chosen_payment_session_name] = $inputs['cp'];

        $this->helper->echoJson(array('response' => 'ok', 'input'=> $inputs));
    }

    // Ajax API to clean SYS Pay session
    public function cleanSession() {
        if (isset($this->session->data[$this->module_name][$this->chosen_payment_session_name]) === true) {
            unset($this->session->data[$this->module_name][$this->chosen_payment_session_name]);
        }

        $this->helper->echoJson(array('response' => 'ok'));
    }

    // Redirect to AIO
    public function redirect() {
        try {
            // Load translation
            $this->load->language($this->module_path);

            $payment_methods = $this->config->get($this->setting_prefix . 'payment_methods');


            // Check choose payment
            if (isset($this->session->data[$this->module_name][$this->chosen_payment_session_name]) === false) {
                throw new Exception($this->language->get($this->setting_prefix . 'error_payment_missing'));
            }
            $choose_payment = $this->session->data[$this->module_name][$this->chosen_payment_session_name];

            // Validate choose payment
            if (in_array($choose_payment, $payment_methods) === false) {
                throw new Exception($this->language->get($this->lang_prefix . 'error_invalid_payment'));
            }

            // Validate the order id
            if (isset($this->session->data['order_id']) === false) {
                throw new Exception($this->language->get($this->lang_prefix . 'error_order_id_miss'));
            }
            $order_id = $this->session->data['order_id'];

            // Get the order info
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder($order_id);
            $order_total = $order['total'];

            // Update order status and comments
            $comment = $this->language->get($this->lang_prefix . 'text_' . $choose_payment);
            $status_id = $this->config->get($this->setting_prefix . 'create_status');
            $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, false);

            // Clear the cart
            $this->cart->clear();

            // Add to activity log
            $this->load->model('account/activity');
            if (empty($this->customer->isLogged()) === false) {
                $activity_key = 'order_account';
                $activity_data = array(
                    'customer_id' => $this->customer->getId(),
                    'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
                    'order_id'    => $order_id
                );
            } else {
                $activity_key = 'order_guest';
                $guest = $this->session->data['guest'];
                $activity_data = array(
                    'name'     => $guest['firstname'] . ' ' . $guest['lastname'],
                    'order_id' => $order_id
                );
            }
            $this->model_account_activity->addActivity($activity_key, $activity_data);

            // Clean the session
            $session_list = array(
                'shipping_method',
                'shipping_methods',
                'payment_method',
                'payment_methods',
                'guest',
                'comment',
                'order_id',
                'coupon',
                'reward',
                'voucher',
                'vouchers',
                'totals',
                'error',
            );
            foreach ($session_list as $name) {
                unset($this->session->data[$name]);
            }

            

            // Checkout
            $helper_data = array(
                'choosePayment' => $choose_payment,
                'hashKey' => $this->config->get($this->setting_prefix . 'hash_key'),
                'hashIv' => $this->config->get($this->setting_prefix . 'hash_iv'),
                'returnUrl' => $this->url->link($this->module_path . '/response', '', true),
                'clientBackUrl' =>$this->url->link('checkout/success'),
                'orderId' => $order_id,
                'total' => $order_total,
                'itemName' => $this->language->get($this->lang_prefix . 'text_item_name'),
                'cartName' => $this->language->get($this->lang_prefix . 'text_trand_desc'),
                'currency' => $this->config->get('config_currency'),
                'needExtraPaidInfo' => 'Y',
                'mode' => $this->config->get($this->setting_prefix . 'mode'),
            );
            
            $this->helper->checkout($helper_data);

        } catch (Exception $e) {
            // Process the exception
            $this->session->data['error'] = $e->getMessage();
            $this->response->redirect($this->url->link('checkout/checkout', '', $this->url_secure));
        }
    }

    // Process AIO response
    public function response() {
        // Load the model and translation
        
        $this->load->language($this->module_path);
        $this->load->model('checkout/order');

        // Set the default result message
        $result_message = '1|OK';
        $order_id = null;
        $order = null;
        try {
            // Get valid feedback
            $helper_data = array(
                'hashKey' => $this->config->get($this->setting_prefix . 'hash_key'),
                'hashIv' => $this->config->get($this->setting_prefix . 'hash_iv'),
            );
            
            $feedback = $this->helper->getValidFeedback($helper_data);
            unset($helper_data);
            $order_id = $this->helper->getOrderId($feedback['MerchantTradeNo']);
            // Get the cart order info
            $order = $this->model_checkout_order->getOrder($order_id);
            $order_status_id = $order['order_status_id'];
            $create_status_id = $this->config->get($this->setting_prefix . 'create_status');
            $order_total = $order['total'];

            // Check the amounts
            if (!$this->helper->validAmount($feedback['TradeAmt'], $order_total)) {
                throw new Exception($this->helper->getAmountError($order_id));
            }

            
            // Get the response state
            $helper_data = array(
                'validState' => ($this->helper->toInt($order_status_id) === $this->helper->toInt($create_status_id)),
                'orderId' => $order_id,
            );
            $response_state = $this->helper->getResponseState($feedback, $helper_data);
            unset($helper_data);
            // Update the order status
            switch($response_state) {
                // Paid
                case 1:

                    $status_id = $this->config->get($this->setting_prefix . 'success_status');
                    $pattern = $this->language->get($this->lang_prefix . 'text_payment_result_comment');
                    $comment = $this->helper->getPaymentSuccessComment($pattern, $feedback);
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, false);
                    unset($status_id, $pattern, $comment);
                    break;

                // Get code 2:ATM
                case 2:
                    $status_id = $order_status_id;
                    $payment_type = $this->helper->getFeedbackPaymentType($feedback['PaymentType']);
                    $pattern = $this->language->get($this->lang_prefix . 'text_' . $payment_type . '_comment');
                    $comment = $this->helper->getObtainingCodeComment($pattern, $feedback);
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, false);
                    unset($status_id, $pattern, $comment);
                    break;

                // State error
                case 5:
                        // Update payment result
                        $status_id = $order_status_id;
                        $pattern = $this->language->get($this->lang_prefix . 'text_payment_result_comment');
                        $comment = $this->helper->getPaymentSuccessComment($pattern, $feedback);
                        $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, false);
                    break;

                // Simulate paid
                case 6:
                    $status_id = $order_status_id;
                    $comment = $this->language->get($this->lang_prefix . 'text_simulate_paid');
                    $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, false, false);
                    unset($status_id, $comment);
                    break;

                default:
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (!is_null($order_id)) {
                $status_id = $this->config->get($this->setting_prefix . 'failed_status');
                $pattern = $this->language->get($this->lang_prefix . 'text_failure_comment');
                $comment = $this->helper->getFailedComment($pattern, $error);
                $this->model_checkout_order->addOrderHistory($order_id, $status_id, $comment, true, false);

                unset($status_id, $pattern, $comment);
            }

            // Set the failure result
            $result_message = '0|' . $error;
        }
        $this->helper->echoAndExit($result_message);
    }



    


}
?>
