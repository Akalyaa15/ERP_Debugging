<?php

namespace App\Models;
use CodeIgniter\Model;
class Payment_methods_model extends Model
{
    protected $table = 'payment_methods';
    protected $primaryKey = 'id';
    protected $returnType = 'object';

    public function __construct()
    {
        parent::__construct();
    }
    public function get_settings($type = "")
    {
        $settings = [
            "stripe" => [
                ["name" => "pay_button_text", "text" => lang("pay_button_text"), "type" => "text", "default" => "Stripe"],
                ["name" => "secret_key", "text" => "Secret Key", "type" => "text", "default" => ""],
                ["name" => "publishable_key", "text" => "Publishable Key", "type" => "text", "default" => ""]
            ],
            "paypal_payments_standard" => [
                ["name" => "pay_button_text", "text" => lang("pay_button_text"), "type" => "text", "default" => "PayPal Standard"],
                ["name" => "email", "text" => "Email", "type" => "text", "default" => ""],
                ["name" => "paypal_live", "text" => "Paypal Live", "type" => "boolean", "default" => "0"],
                ["name" => "debug", "text" => "Enable Debug", "type" => "boolean", "default" => "0", "help_text" => "Save logs in a file (paypal.log) in root directory during processing the IPN"],
                ["name" => "paypal_ipn_url", "text" => "Paypal IPN URL", "type" => "readonly", "default" => get_uri("paypal_ipn")]
            ],
            "khipu" => [
                ["name" => "pay_button_text", "text" => lang("pay_button_text"), "type" => "text", "default" => "PayPal Pro"],
                ["name" => "api_username", "text" => "API Username", "type" => "text", "default" => ""],
                ["name" => "api_password", "text" => "API Password", "type" => "text", "default" => ""],
                ["name" => "api_signature", "text" => "API Signature", "type" => "text", "default" => ""],
                ["name" => "paypal_live", "text" => "Paypal Live", "type" => "boolean", "default" => "0"]
            ],
            "net_banking" => [
                ["name" => "pay_button_text", "text" => lang("pay_button_text"), "type" => "text", "default" => "Net Banking"]
            ]
        ];

        if ($type) {
            return $settings[$type] ?? [];
        } else {
            return [];
        }
    }

    public function get_one_with_settings($id = 0)
    {
        $info = $this->find($id);
        return $this->_merge_online_settings_with_default($info);
    }

    public function get_oneline_payment_method($type)
    {
        $info = $this->where('deleted', 0)->where('type', $type)->where('online_payable', 1)->first();
        return $this->_merge_online_settings_with_default($info);
    }

    private function _merge_online_settings_with_default($info)
    {
        $settings = $this->get_settings($info->type);
        $settings_data = unserialize($info->settings) ?: [];

        foreach ($settings as $setting) {
            $setting_name = is_array($setting) ? $setting['name'] : '';
            $info->$setting_name = $settings_data[$setting_name] ?? $setting['default'];
        }

        return $info;
    }

    public function get_details($options = [])
    {
        $id = $options['id'] ?? null;
        $where = $id ? "id=$id" : '';

        return $this->where('deleted', 0)->where($where)->findAll();
    }

    public function delete($id = 0, $undo = false)
    {
        $exists = $this->find($id);
        if ($exists && $exists->online_payable == 1) {
            // Online payable types can't be deleted
            return false;
        } else {
            return parent::delete($id, $undo);
        }
    }

    public function get_available_online_payment_methods()
    {
        $settings = $this->where('deleted', 0)->where('online_payable', 1)->where('available_on_invoice', 1)->findAll();

        $final_settings = [];
        foreach ($settings as $setting) {
            $final_settings[] = $this->_merge_online_settings_with_default($setting);
        }
        return $final_settings;
    }

    public function get_available_purchase_order_net_banking_payment_methods()
    {
        $settings = $this->where('deleted', 0)->where('online_payable', 1)->where('available_on_purchase_order', 1)->findAll();

        $final_settings = [];
        foreach ($settings as $setting) {
            $final_settings[] = $this->_merge_online_settings_with_default($setting);
        }
        return $final_settings;
    }

    public function get_available_work_order_net_banking_payment_methods()
    {
        $settings = $this->where('deleted', 0)->where('online_payable', 1)->where('available_on_work_order', 1)->findAll();

        $final_settings = [];
        foreach ($settings as $setting) {
            $final_settings[] = $this->_merge_online_settings_with_default($setting);
        }
        return $final_settings;
    }
}
