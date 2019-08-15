<?php

namespace App;

/**
 * Class WipeiApi
 * @author Javier Ugarte
 */
class WipeiApi
{
    const WIPEI_ENDPOINT = 'https://api.wipei.com.ar/';

    const WIPEI_STATUS = [
        'pending'           => 'pendiente',
        'approved'          => 'exitoso',
        'cancelled'         => 'error',
        'pending_approved'  => 'exitoso',
        'pending_cancelled' => 'error',
    ];

    const WIPEI_URL_NOTIFY_ROUTE  = 'payment.notify';
    const WIPEI_URL_FAILURE_ROUTE = 'payment.failure';
    const WIPEI_URL_SUCCESS_ROUTE = 'payment.success';

    protected $client_id = '';
    protected $client_secret = '';

    protected $token = '';

    /**
     * @param \App\Shop $shop
     */
    public function __construct(\App\Shop $shop)
    {
        $this->client_id = $shop->client_id;
        $this->client_secret = $shop->client_secret;
    }

    /**
     * Create an order through the Wipei API
     *      Return true if created successfully
     *      Return false if order wasn't created
     *
     * @return Boolean
     */
    public function createOrderForButton(\App\Button $button)
    {
        if (! $this->fetchToken()) {
            $button->status = 'error';
            $button->update();
            return false;
        }

        $data = [
            'total'                 => $button->total,
            'external_reference'    => $button->btn_reference,
            'items'                 => $button->items,
            'url_success'           => route(SELF::WIPEI_URL_SUCCESS_ROUTE),
            'url_failure'           => route(SELF::WIPEI_URL_FAILURE_ROUTE),
            'url_notify'            => route(SELF::WIPEI_URL_NOTIFY_ROUTE),
        ];

        $response = $this->callApi('order', $data);

        if ($response->status == 200) {
            $button->init_point = $response->data->init_point;

            $order_id = explode('=', $response->data->init_point);
            $order_id = (integer)end($order_id);
            $button->associated_order = $order_id;

            $button->status = 'exitoso';
            $button->update();

            return true;
        }

        $button->status = 'error';
        $button->update();
        return false;
    }


    /**
     * Update button status with updated data from the Wipei API
     *      Return true if updated successfully
     *      Return false if button wasn't updated
     *
     * @return Boolean
     */
    public function updateButtonDetails(\App\Button $button)
    {
        if (! $this->fetchToken()) {
            return false;
        }

        if (! $button->associated_order) {
            return false;
        }

        $response = $this->callApi('order_store?order='.$button->associated_order, [], true, 'GET');

        if ($response->status == 200) {
            $button->payment_status = SELF::WIPEI_STATUS[$response->data->status];
            $button->update();
            return true;
        }

        return false;
    }


    /**
     * Get the API token
     *      Return true if token was successfully returned
     *      Return false if credentials were not correct
     *
     * @return Boolean
     */
    protected function fetchToken()
    {
        $data = [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret
        ];

        $response = $this->callApi('token', $data, false);

        if ($response->status == 200) {
            $this->token = $response->data->access_token;
            return true;
        }

        return false;
    }


    /**
     * Call the Wipei API
     *
     * @return the API's response
     */
    protected function callApi($endpoint, $data = [], $with_token = true, $method = 'POST')
    {
        $handle = curl_init();
        $url    = SELF::WIPEI_ENDPOINT . $endpoint;

        $data_string = json_encode($data);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ];

        if ($with_token) {
            $headers[] = 'Authorization: ' . $this->token;
        }

        curl_setopt_array($handle,
            array(
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POSTFIELDS     => $data_string,
                CURLOPT_HTTPHEADER     => $headers,
            )
        );


        $output = curl_exec($handle);

        $response = (object) [
            'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
            'data'   => json_decode($output),
        ];

        curl_close($handle);
        return $response;
    }


    /*
     * GETTERS
     * */

    public function getClientId()
    {
        return $this->client_id;
    }
    public function getClientSecret()
    {
        return $this->client_secret;
    }
    public function getToken()
    {
        return $this->token;
    }
}
