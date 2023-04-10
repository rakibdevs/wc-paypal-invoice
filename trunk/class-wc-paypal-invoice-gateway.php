<?php

class WC_PayPal_Invoice_Gateway extends WC_Payment_Gateway
{
    protected $client_id;

    protected $client_secret;

    protected $environment;

    protected $currency;

    public function __construct()
    {
        $this->id = 'wc_paypal_invoice';
        $this->icon = '';
        $this->method_title = 'PayPal Invoice';
        $this->method_description = 'Send an online invoice that customers can pay instantly, even if they don’t have an account with PayPal.';
        $this->supports = array(
            'products'
        );
        $this->form_fields = [];
        $this->init_settings();
        $this->init_form_fields();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->client_id = $this->get_option('client_id');
        $this->client_secret = $this->get_option('client_secret');
        $this->environment = $this->get_option('environment');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => 'Enable/Disable',
                'label'       => 'Enable PayPal Invoice Payment',
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'description' => 'This controls the title which the user sees during checkout.',
                'default'     => 'PayPal Invoice',
                'desc_tip'    => true,
            ),
            'description' => array(
                'title' => __('Description', 'wc-paypal-invoice-gateway'),
                'type' => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.'),
                'default' => __('Pay with PayPal Invoice'),
                'desc_tip' => true
            ),
            'client_id' => array(
                'title'       => 'Client ID',
                'type'        => 'text',
                'description' => 'Enter your PayPal client ID.',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'client_secret' => array(
                'title'       => 'Client Secret',
                'type'        => 'password',
                'description' => 'Enter your PayPal client secret.',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'environment' => array(
                'title'       => 'Environment',
                'type'        => 'select',
                'description' => 'Choose the environment where you want to process payments.',
                'options'     => array(
                    'sandbox'    => 'Sandbox',
                    'production' => 'Production',
                ),
                'default'     => 'sandbox',
                'desc_tip'    => true,
            ),
        );
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $invoice_data = $this->prepare_invoide_data($order);

        $paypalClient = new PayPalInvoice($this->client_id, $this->client_secret, $this->environment);
        $invoice = $paypalClient->create_invoice($invoice_data);

        if (!empty($invoice)) {
            WC()->cart->empty_cart();
            $order->update_meta_data('_paypal_invoice_id', $invoice['id']);
            $order->save();

            $response = $paypalClient->send_invoice($invoice['id']);

            if (!empty($response)) {
                return [
                    'result' => 'success',
                    'redirect' => $response['href'],
                ];
            }
        }

        wc_add_notice(__('There was an error processing your payment. Please try again later. ', 'paypal-invoice-payment'), 'error');
        return;
    }

    private function prepare_invoide_data($order)
    {
        $this->currency = $order->get_currency();

        return [
            'detail' => [
                'invoice_number' => $order->get_order_key(),
                'invoice_date' => $order->get_date_created()->format('Y-m-d'),
                'currency_code' => $this->currency,
            ],
            'invoicer' => $this->prepare_invoicer(),
            'primary_recipients' => [
                [
                    'billing_info' => $this->prepare_billing_address($order),
                    'shipping_info' => null
                ]
            ],
            'items' => $this->prepare_order_items($order->get_items()),
            'configuration' => [
                'tax_calculated_after_discount' => true,
                'tax_inclusive' => false
            ],
            'amount' => [
                'breakdown' => [
                    'shipping' => [
                        'amount' => [
                            'currency_code' => $this->currency,
                            'value' => $order->get_shipping_total()
                        ],
                    ],
                    'discount' => [
                        'invoice_discount' => [
                            'amount' => [
                                'currency_code' => $this->currency,
                                'value' => $order->get_discount_total()
                            ],
                        ]
                    ],
                    'tax_total' => [
                        'currency_code' => $this->currency,
                        'value' => $order->get_total_tax()
                    ]
                ],
                'currency_code' => $this->currency,
                'value' => $order->get_total()
            ]
        ];
    }

    private function prepare_order_items($items)
    {
        $processed_items = [];
        foreach ($items as $key => $item) {
            $product = $item->get_product();
            $processed_items[] = [
                "name" => $product->get_name(),
                "quantity" => $item->get_quantity(),
                "unit_amount" => [
                    "currency_code" => $this->currency,
                    "value" => $product->get_price()
                ],
                "unit_of_measure" => "QUANTITY"
            ];
        }

        return $processed_items;
    }

    private function prepare_invoicer()
    {
        return [
            'name' => [
                'full_name' => get_bloginfo('name'),
            ],
            'address' => [
                'address_line_1' => get_option('woocommerce_store_address'),
                'admin_area_2' => get_option('woocommerce_store_city'),
                'admin_area_1' => get_option('woocommerce_default_country'),
                'postal_code' => get_option('woocommerce_store_postcode'),
                'country_code' => substr(get_option('woocommerce_default_country', '**'), 0, 2)
            ]
        ];
    }

    private function prepare_billing_address($order)
    {
        return [
            'name' => [
                'given_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'surname' => $order->get_billing_first_name(),
            ],
            "address" => [
                "address_line_1" => $order->get_billing_address_1(),
                "admin_area_2" => $order->get_billing_city(),
                "admin_area_1" => $order->get_billing_state(),
                "postal_code" => $order->get_billing_postcode(),
                "country_code" => $order->get_billing_country()
            ],
            "email_address" => $order->get_billing_email()
        ];
    }
}
