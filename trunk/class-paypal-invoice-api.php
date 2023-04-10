<?php

class PayPalInvoice
{
    private $client_id;
    private $client_secret;
    private $environment;
    private $access_token;
    private $api_endpoint;

    public function __construct($client_id, $client_secret, $environment = 'sandbox')
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->environment = $environment;
        $this->api_endpoint = $environment === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
        $this->set_access_token();
    }

    public function get_access_token()
    {
        return $this->access_token;
    }

    private function set_access_token()
    {
        $headers = [
            'Authorization: Basic ' . base64_encode($this->client_id . ":" . $this->client_secret),
            'Content-Type: application/x-www-form-urlencoded'
        ];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->api_endpoint . '/v1/oauth2/token');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'client_credentials'
        ]));
        $result = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($result, true);
        if (isset($data['access_token'])) {
            $this->access_token = $data['access_token'];
        }
    }

    public function create_invoice($data)
    {
        return $this->__post($this->api_endpoint . '/v2/invoicing/invoices', $data, [
            'Prefer: return=representation'
        ]);
    }

    public function send_invoice($invoiceId)
    {
        return $this->__post($this->api_endpoint . "/v2/invoicing/invoices/$invoiceId/send", [
            'subject' => get_option('Your order has been created!'),
            'note' => get_option('Thank you so much for creating order with us!'),
            'send_to_recipient' => true,
            'send_to_invoicer' => false
        ]);
    }

    private function __post($url, $data, $headers = [])
    {
        $headers = array_merge($headers, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->get_access_token(),
        ]);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($http_code >= 200 && $http_code < 300) {
            return json_decode($result, true);
        }

        return false;
    }
}
