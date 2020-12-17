<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */

class WipeiPaymentModuleFrontController extends ModuleFrontController {
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

    public $token;
	public $ssl = true;

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent() {
		parent::initContent();
        $cart = $this->context->cart;

		if (!$this->module->checkCurrency($cart))
            Tools::redirect('index.php?controller=order');

        if (_PS_VERSION_ > "1.7.0.0") {
            $total = sprintf(
                $this->getTranslator()->trans('%1$s (tax incl.)', array(), 'Modules.Wipei.Shop'),
                Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH))
            );
        } else {
            $total = Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH));
        }

		$products = $this->context->cart->getProducts(true);

        $items = array();
		foreach ($products as $product){
            $items[] = array(
                'name' => $product['name'],
                'quantity' => $product['cart_quantity'],
                'price' => round($product['price_wt'], 2)
            );
        }

		$data = [
            'total'                 => $cart->getOrderTotal(true, Cart::BOTH),
            'external_reference'    => $this->context->cart->id.'-'.mt_rand(111, 999),
            'items'                 => $items,
            'url_success'           => $this->context->link->getModuleLink('wipei', 'validation', [], true),
            'url_failure'           => $this->context->link->getPageLink('order', true, NULL, "step=3"),
            'url_notify'            =>  _PS_BASE_URL_._MODULE_DIR_.'wipei/notify.php',
        ];

        $response = $this->callApi('order', $data, true, 'POST');

        if ($response->status == 200) {
            $init_point = $response->data->init_point;
            $order_id = explode('=', $response->data->init_point);
            $order_id = (integer)end($order_id);
            $this->module->orderw = $order_id ;
            $status = 'exitoso';
        }

        $titlel = Configuration::get(
            'WIPEI_TITLEL',
            $this->context->language->id
        );

		$this->context->smarty->assign(
            array(
                'back_url' => $this->context->link->getPageLink('order', true, NULL, "step=3"),
                'titlel' => $titlel,

                'confirm_url' => $this->context->link->getModuleLink('wipei', 'validation', [], true),
                'image_url' => $this->module->getPathUri() . 'views/img/logo.png',
                'urlwipei' => (!isset($init_point) ? '#' : $init_point),

                'cust_currency' => $cart->id_currency,
                'currencies' => $this->module->getCurrency((int)$cart->id_currency),
                'total' => $total,
                'this_path' => $this->module->getPathUri(),
                'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
            )
        );

        if (_PS_VERSION_ > "1.7.0.0") {
            $this->setTemplate('module:wipei/views/templates/front/payment_execution.tpl');
        } else {
            $this->setTemplate('payment_execution2.tpl');
        }
    }

    protected function fetchToken() {
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
