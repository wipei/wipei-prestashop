<?php
class WipeiNotifyModuleFrontController extends ModuleFrontController
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


    public $ssl = true;

	public function initContent() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $response = $this->callApi('order_store?order='.Tools::getValue('id'), [], true, 'GET');

            if (!isset($response->data->external_reference)) {
                exit;
            }

            $result = Db::getInstance()->ExecuteS(
                'SELECT *
                FROM `'._DB_PREFIX_.'orders`
                WHERE `id_cart` = '.substr_replace($response->data->external_reference ,"", -4)
            );

            if ($result) {
                if ($response->data->status == "approved" or $response->data->status == "pending_approved") {
                    foreach ($result as $ord) {
                        $objOrder = new Order($ord['id_order']);
                        $history = new OrderHistory();
                        $history->id_order = (int)$objOrder->id;
                        $history->changeIdOrderState(
                            Configuration::get('PS_OS_PAYMENT'),
                            (int)($objOrder->id)
                        );
                        $history->add(true);
                        // $objOrder->setCurrentState(Configuration::get('PS_OS_PAYMENT'));
                    }

                    echo 'OK';
                    exit;
                }

                if ($response->data->status == "cancelled" or $response->data->status == "pending_cancelled") {
                    foreach ($result as $ord) {
                        $objOrder = new Order($ord['id_order']);
                        $history = new OrderHistory();
                        $history->id_order = (int)$objOrder->id;
                        $history->changeIdOrderState(
                            Configuration::get('PS_OS_CANCELED'),
                            (int)($objOrder->id)
                        );
                        $history->add(true);
                        // $objOrder->setCurrentState(Configuration::get('PS_OS_CANCELED'));
                    }

                    echo 'OK';
                    exit;
                }

            } else {

                if ($response->data->status == "approved" or $response->data->status == "pending_approved") {
                    $cart = new Cart(substr_replace($response->data->external_reference ,"", -4));
                    $customer = new Customer($cart->id_customer);

                    $currency = $this->context->currency;
                    $total = (float)$cart->getOrderTotal(true, Cart::BOTH);


                    $this->module->validateOrder(
                        $cart->id,
                        Configuration::get('PS_OS_PAYMENT'),
                        $total,
                        $this->module->displayName,
                        NULL,
                        array(),
                        (int)$currency->id,
                        false,
                        $customer->secure_key
                    );

                    echo 'OK';
                    exit;
                }

                if ($response->data->status == "cancelled" or $response->data->status == "pending_cancelled") {
                    $cart = $this->context->cart;

                    $customer = new Customer($cart->id_customer);
                    if (!Validate::isLoadedObject($customer))
                        Tools::redirect('index.php?controller=order&step=1');

                    $currency = $this->context->currency;
                    $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
                    $mailVars = array(
                        '{bankwire_owner}' => Configuration::get('BANK_WIRE_OWNER'),
                        '{bankwire_details}' => nl2br(Configuration::get('BANK_WIRE_DETAILS')),
                        '{bankwire_address}' => nl2br(Configuration::get('BANK_WIRE_ADDRESS'))
                    );

                    $this->module->validateOrder(
                        $cart->id,
                        Configuration::get('PS_OS_CANCELED'),
                        $total,
                        $this->module->displayName,
                        NULL,
                        $mailVars,
                        $this->context->currency->id,
                        false,
                        $customer->secure_key
                    );

                    echo 'OK';
                    exit;
                }
            }
        }
    }

    public function fetchToken() {
        $data = [
            'client_id'     => Configuration::get('WIPEI_API'),
            'client_secret' => Configuration::get('WIPEI_PROFILE')
        ];

        $response = $this->callApi('token', $data, false);
        if ($response->status == 200) {
            $this->token = $response->data->access_token;
            $this->module->tokenw = $response->data->access_token;
            return true;
        }

        return false;
    }

	public function callApi($endpoint, $data = [], $with_token = true, $method = 'POST') {
        $handle = curl_init();
        $url    = SELF::WIPEI_ENDPOINT . $endpoint;

        $data_string = json_encode($data);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string)
        ];

        if ($with_token) {
            $headers[] = 'Authorization: ' . $this->getToken();
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

	public function getClientId() {
        return Configuration::get('WIPEI_API');
    }

    public function getClientSecret() {
        return Configuration::get('WIPEI_PROFILE');
    }

	public function getToken() {
		$this->fetchToken();
        return $this->token;
    }
}
