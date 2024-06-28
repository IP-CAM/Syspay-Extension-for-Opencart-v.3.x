<?php
class ModelExtensionPaymentSyspaypayment extends Model {
    private $module_name = 'syspaypayment';
    private $lang_prefix = '';
    private $module_path = '';
    private $setting_prefix = '';
	private $libraryList = array('SyspayPaymentHelper.php');
	private $helper = null;

    // Constructor
    public function __construct($registry) {
        parent::__construct($registry);

        // Set the variables
        $this->lang_prefix = $this->module_name .'_';
        $this->setting_prefix = 'payment_' . $this->module_name . '_';
        $this->module_path = 'extension/payment/' . $this->module_name;
        $this->loadLibrary();
        $this->helper = $this->getHelper();
    }

	public function getMethod($address, $total) {
        // Condition check
        $syspay_geo_zone_id = $this->config->get($this->setting_prefix . 'geo_zone_id');
        $sql = 'SELECT * FROM `' . DB_PREFIX . 'zone_to_geo_zone`';
        $sql .= ' WHERE geo_zone_id = "' . (int)$syspay_geo_zone_id . '"';
        $sql .= ' AND country_id = "' . (int)$address['country_id'] . '"';
        $sql .= ' AND (zone_id = "' . (int)$address['zone_id'] . '" OR zone_id = "0")';
        $query = $this->db->query($sql);
        unset($sql);

        $status = false;
        if ($total <= 0) {
            $status = false;
        } elseif (!$syspay_geo_zone_id) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        // Set the payment method parameters
        $this->load->language($this->module_path);
        $method_data = array();
        if ($status === true) {
            $method_data = array(
                'code' => $this->module_name,
                'title' => $this->language->get($this->lang_prefix . 'text_title'),
                'terms' => '',
                'sort_order' => $this->config->get($this->setting_prefix . 'sort_order')
            );
        }
        return $method_data;
    }
	
    // Load the libraries
	public function loadLibrary() {
		foreach ($this->libraryList as $path) {
			include_once($path);
		}
	}

    // Get the helper
    public function getHelper() {
        $merchant_id = $this->config->get($this->setting_prefix . 'merchant_id');
        $mode = $this->config->get($this->setting_prefix . 'mode');
        $helper = new SyspayPaymentHelper();
        $helper->setMerchantId($merchant_id);
        
        $helper->setMode($mode);

        return $helper;
    }


}
