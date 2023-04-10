<?php
/*
* Plugin Name: Easy Paypal Invoice
* Description: Add Paypal Invoice as a payment method to woocommerce and get paid instantly by customer, even if they don’t have an account with PayPal.
* Version: 1.0.0
* Requires at least:       5.0
* Tested up to:            6.1
* Author: Md. Rakibul Islam
* Author Email: rakib1708@gmail.com
* Author URI: https://github.com/rakibdevs
* Text Domain: easy-paypal-invoice
*/

add_action('woocommerce_loaded', 'init_paypal_invoice_gateway');

function init_paypal_invoice_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    include_once(plugin_dir_path(__FILE__) . 'class-wc-paypal-invoice-gateway.php');
    include_once(plugin_dir_path(__FILE__) . 'class-paypal-invoice-api.php');

    add_filter('woocommerce_payment_gateways', 'add_paypal_invoice_gateway');
    function add_paypal_invoice_gateway($gateways)
    {
        $gateways[] = 'WC_PayPal_Invoice_Gateway';
        return $gateways;
    }
}
