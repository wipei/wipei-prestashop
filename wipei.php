<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * No redistribute in other sites, or copy.
 *
 * @author    smokestorm <demo@demo.com>
 * @copyright 2007-2017 smokestorm
 * added
 * @license   http://localhost
 */
//use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
class Wipei extends PaymentModule
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
    private $_html = '';
    private $_postErrors = array();
    public $available_fields = array();

    public function __construct()
    {
        $this->module_key = 'c7a30c0be439ec38072db7196d79d9c8';
        $this->name = 'wipei';
        $this->tokenw = '';
        $this->orderw = '';
        if (_PS_VERSION_ < '1.4.0.0') {
            $this->tab = 'Blocks';
        }
        if (_PS_VERSION_ > '1.4.0.0') {
            $this->tab = 'payments_gateways';
            $this->author = 'wipei';
            $this->need_instance = 1;
        }
        if (_PS_VERSION_ > '1.6.0.0') {
            $this->bootstrap = true;
        }
        $this->version = '1.0.0';
        parent::__construct();
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->displayName = $this->l('Wipei');
        $this->description = $this->l('Accept Wipei payment');
    }

    public function install()
    {
        if (!Configuration::updateValue(
            'WIPEI_API',
            'secret'
        ) || !Configuration::updateValue(
                'WIPEI_PROFILE',
                'xxxxxxxxxx'
            ) || !Configuration::updateValue(
                'WIPEI_EVERYWHERE',
                0
            ) || !Configuration::updateValue(
                'WIPEI_KEY',
                'xxx'
            ) || !Configuration::updateValue(
                'WIPEI_PROFILE2',
                '1'
            ) || !Configuration::updateValue(
                'WIPEI_KEY2',
                'xxx'
            ) || !Configuration::updateValue(
                'WIPEI_BOX_WIDTH',
                700
            ) || !Configuration::updateValue(
                'WIPEI_BOX_HEIGHT',
                394
            ) || !Configuration::updateValue(
                'WIPEI_SHOW_TIME',
                3
            ) || !Configuration::updateValue(
                'WIPEI_BTN_COLOR',
                "#BD1E30"
            ) || !Configuration::updateValue(
                'WIPEI_TITLE_COLOR',
                "#FFFFFF"
            ) || !Configuration::updateValue(
                'WIPEI_EVERYWHERE',
                0
            ) || !parent::install() ||  !$this->_createPendingState() || !$this->registerHook('displayBeforeBodyClosingTag') || !$this->registerHook('paymentOptions') || !$this->registerHook('displayPayment')
        ) {
            return false;
        }
        return true;
    }
    public function hookPaymentOptions($params)
    {
      
        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $newOption = new PaymentOption();
        $descl = Configuration::get(
            'WIPEI_DESCL',
            $this->context->language->id
        );
        $newOption->setModuleName($this->name)
        ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true))
                ->setCallToActionText($descl)
                //  ->setLogo(_PS_BASE_URI__.'modules/'.$this->name.'/logo.png')
           ;

                //var_dump($newOption);
        return [$newOption];
    }
    public function hookDisplayPayment()
	{
		//si el modulo no esta activo
		if (!$this->active)
			return;
            $titlel = Configuration::get(
                'WIPEI_TITLEL',
                $this->context->language->id
            );
		$this->smarty->assign(array(
			//'nombre' => Configuration::get($this->getPrefijo('PREFIJO_CONFIG').'_FRONTEND_NAME'),//nombre que se muestra al momento de elegir los metodos de pago 
            'this_path' => $this->_path,
            'titlel' => $titlel,
			'this_path_ejemplo' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
			'module_path' => strtolower(__PS_BASE_URI__.'modules/'.$this->name.'/views/img/logo.png'),

		));
		return $this->display(__FILE__, 'payment.tpl');//asigno el template que quiero usar
	}
    public function checkCurrency($cart)
    {
        $currency_order = new Currency((int)($cart->id_currency));
        $currencies_module = $this->getCurrency((int)$cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
    public function getTemplateVars()
    {
        $cart = $this->context->cart;
        $total = $this->trans(
            '%amount% (tax incl.)',
            array(
                '%amount%' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            ),
            'Modules.Wipei.Admin'
        );

        $wuOrder = Configuration::get('WIPEI_OWNER');
        if (!$wuOrder) {
            $wuOrder = '___________';
        }

        $wuAddress = Tools::nl2br(Configuration::get('WIPEI_DETAILS'));
        if (!$wuAddress) {
            $wuAddress = '___________';
        }
        $wuDet = Tools::nl2br(Configuration::get('WIPEI_ADDRESS'));
        if (!$wuDet) {
            $wuDet = '___________';
        }
        return [
            'wuTotal' => $total,
            'wuDet' => $wuDet,
            'wuOrder' => $wuOrder,
            'wuAddress' => $wuAddress,
        ];
    }
    public function hookPaymentReturn($params)
    {

        if (_PS_VERSION_ > '1.7.0.0') {
            $this->smarty->assign(array(
                    'base_dir' => $this->_path,
                ));
        }

        $state = Configuration::get('WIPEI_STATE1');
        if ($state == Configuration::get('WIPEI_STATE1') || $state == _PS_OS_OUTOFSTOCK_) {
            if (_PS_VERSION_ < '1.5.0.0') {
                $this->context->smarty->assign(
                    array(

                        'total_to_pay' => Tools::displayPrice(
                            $params['total_to_pay'],
                            $params['currencyObj'],
                            false,
                            false
                        ),
                        'wipeiDetails' => $this->details,
                        'wipeiAddress' => $this->address,
                        'wipeiOwner' => $this->owner,
                        'wipeiState1' => $this->state1,
                        'status' => 'ok',
                        'id_order' => $params['objOrder']->id
                    )
                );
            } else {
                $this->context->smarty->assign(
                    array(
                        'total_to_pay' => Tools::displayPrice(
                            $params['order']->getOrdersTotalPaid(),
                            new Currency($params['order']->id_currency),
                            false
                        ),
                        'wipeiDetails' => $this->details,
                        'wipeiAddress' => $this->address,
                        'wipeiOwner' => $this->owner,
                        'wipeiState1' => $this->state1,
                        'status' => 'ok',
                        'id_order' => $params['order']->id
                    )
                );
            }
        } else {
            $this->context->smarty->assign(
                'status',
                'failed'
            );
        }
        return $this->display(
            __FILE__,
            'views/templates/front/payment_return.tpl'
        );
    }

    public function _createPendingState()
    {
        $state = new OrderState();
        $languages = Language::getLanguages();
        $names = array();
        foreach ($languages as $lang) {
            $names[$lang['id_lang']] = 'Wipei payment';
        }
        $state->name = $names;
        $state->color = '#4169AA';
        $state->send_email = true;
        $state->module_name = 'wipei';
        $templ = array();
        foreach ($languages as $lang) {
            $templ[$lang['id_lang']] = 'wipei';
        }
        $state->template = $templ;

        if ($state->save()) {
            Configuration::updateValue(
                'wipei',
                $state->id
            );

            $directory = _PS_MODULE_DIR_.$this->name.'/mails/';
            if ($dhvalue = opendir($directory)) {
                while (($file = readdir($dhvalue)) !== false) {
                    if (is_dir($directory.$file) && $file[0] != '.') {
                        @copy(
                            $directory.$file.'/wipei.html',
                            '../mails/'.$file.'/wipei.html'
                        );
                        @copy(
                            $directory.$file.'/wipei.txt',
                            '../mails/'.$file.'/wipei.txt'
                        );
                        @copy(
                            $directory.$file.'/wipei.txt',
                            '../mails/'.$file.'/wipei.txt'
                        );
                        @copy(
                            $directory.$file.'/wipei.html',
                            '../mails/'.$file.'/wipei.html'
                        );
                    }
                }
                closedir($dhvalue);
            }
        }
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)
                    ->executeS(
                        '
		SELECT *
		FROM `'._DB_PREFIX_.'order_state` os
		WHERE deleted = 0
		ORDER BY `id_order_state` DESC LIMIT 1'
                    );
        Configuration::updateValue(
            'WIPEI_STATE1',
            $result[0]['id_order_state']
        );
        $file = _PS_MODULE_DIR_.$this->name.'/views/img/icon.gif';
        copy(
            $file,
            '../img/os/'.$result[0]['id_order_state'].'.gif'
        );
        return true;
    }

    public function postProcess()
    {
        $errors = array();
        $output = '';
        $table = '';
        /**/
        if (Tools::isSubmit('submitCustomize')) {
            $api = Tools::getValue('api');
            $profile = Tools::getValue('profile');
            $profile2 = Tools::getValue('profile2');
            $key = Tools::getValue('key');
            $WIPEI_BOX_HEIGHT = Tools::getValue('WIPEI_BOX_HEIGHT');
            if (!Validate::isInt($WIPEI_BOX_HEIGHT)) {
                $errors[] = $this->l('Invalid value in height');
            }
            $WIPEI_BOX_WIDTH = Tools::getValue('WIPEI_BOX_WIDTH');
            if (!Validate::isInt($WIPEI_BOX_WIDTH)) {
                $errors[] = $this->l('Invalid value in width');
            }
            $WIPEI_SHOW_TIME = Tools::getValue('WIPEI_SHOW_TIME');
            if (!Validate::isInt($WIPEI_SHOW_TIME)) {
                $errors[] = $this->l('Invalid value in seconds');
            }
            $datec = Tools::getValue('datec');
            if (!Validate::isInt($datec)) {
                $errors[] = $this->l('Invalid date in date');
            }
            if (isset($errors) && count($errors) != '') {
                $output .= $this->displayError($errors);
            } else {

                Configuration::updateValue(
                    'WIPEI_API',
                    $api
                );

                Configuration::updateValue(
                    'WIPEI_KEY',
                    $key
                );
                Configuration::updateValue(
                    'WIPEI_PROFILE',
                    $profile
                );
                $languages = Language::getLanguages(false);
                $default_language = (int)(Configuration::get('PS_LANG_DEFAULT'));
                $result = array();
                $result2 = array();
                foreach ($languages as $language) {
                    $result[$language['id_lang']] = Tools::getValue('titlel_'.$language['id_lang']);
                    $result2[$language['id_lang']] = Tools::getValue('descl_'.$language['id_lang']);
                }
                Configuration::updateValue(
                    'WIPEI_TITLEL',
                    $result
                );
                Configuration::updateValue(
                    'WIPEI_DESCL',
                    $result2
                );
                ToolsCore::clearCache();
                $output .= $this->displayConfirmation($this->l('Settings updated.').'<br/>');
            }
        }
        return $output;
    }

    public function getConfigFieldsValues()
    {
        $fields_values = array(
            'api' => Tools::getValue(
                'api',
                Configuration::get('WIPEI_API')
            ),
            'key' => Tools::getValue(
                'key',
                Configuration::get('WIPEI_KEY')
            ),
            'profile' => Tools::getValue(
                'profile',
                Configuration::get('WIPEI_PROFILE')
            ),
            'profile2' => Tools::getValue(
                'profile2',
                Configuration::get('WIPEI_PROFILE2')
            ),
            'key2' => Tools::getValue(
                'key2',
                Configuration::get('WIPEI_KEY2')
            ),
            'datec' => Tools::getValue(
                'datec',
                Configuration::get('WIPEI_DATEC')
            ),
            'WIPEI_EVERYWHERE' => Tools::getValue(
                'WIPEI_EVERYWHERE',
                Configuration::get('WIPEI_EVERYWHERE')
            ),
            'WIPEI_BOX_HEIGHT' => Tools::getValue(
                'WIPEI_BOX_HEIGHT',
                Configuration::get('WIPEI_BOX_HEIGHT')
            ),
            'WIPEI_BOX_WIDTH' => Tools::getValue(
                'WIPEI_BOX_WIDTH',
                Configuration::get('WIPEI_BOX_WIDTH')
            ),
            'WIPEI_SHOW_TIME' => Tools::getValue(
                'WIPEI_SHOW_TIME',
                Configuration::get('WIPEI_SHOW_TIME')
            ),
            'WIPEI_BTN_COLOR' => Tools::getValue(
                'WIPEI_BTN_COLOR',
                Configuration::get('WIPEI_BTN_COLOR')
            ),
            'WIPEI_TITLE_COLOR' => Tools::getValue(
                'WIPEI_TITLE_COLOR',
                Configuration::get('WIPEI_TITLE_COLOR')
            ),
        );
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $fields_values['titlel'][$lang['id_lang']] = Tools::getValue(
                'titlel_',
                Configuration::get(
                    'WIPEI_TITLEL',
                    $lang['id_lang']
                )
            );
            $fields_values['descl'][$lang['id_lang']] = Tools::getValue(
                'descl_',
                Configuration::get(
                    'WIPEI_DESCL',
                    $lang['id_lang']
                )
            );
        }
        return $fields_values;
    }


    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Configuration'),
                    'description' => $this->l('Configure Wipei'),
                    'icon' => 'icon-cogs'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Client ID'),
                        'name' => 'api',
                        'size' => 20,
                        'desc' => $this->l('Wipei API client ID'),

                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret'),
                        'name' => 'profile',
                        'size' => 20,
                        'desc' => $this->l('Wipei API secret'),

                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Texto a mostrar en la pagina de pago'),
                        'name' => 'descl',
                        'lang' => true,
                    ),
                    array(
                        'type' => 'text',
                        'label' => $this->l('Texto a mostrar antes de redirigir a Wipei'),
                        'name' => 'titlel',
                        'lang' => true,
                    ),

                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                )
            ),
        );
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_F||M_LANG') ? Configuration::get(
            'PS_BO_ALLOW_EMPLOYEE_F||M_LANG'
        ) : 0;
        $this->fields_form = array();
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitCustomize';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        );
        return $helper->generateForm(array($fields_form));
    }

    public function callApi($endpoint, $data = [], $with_token = true, $method = 'POST')
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
    protected function fetchToken()
    {
        $data = [
            'client_id'     => Configuration::get('WIPEI_API'),
            'client_secret' => Configuration::get('WIPEI_PROFILE')
        ];

        $response = $this->callApi('token', $data, false);
        if ($response->status == 200) {
            $this->token = $response->data->access_token;

            return true;
        }

        return false;
    }

    /*
     * GETTERS
     * */

    public function getClientId()
    {
        return Configuration::get('WIPEI_API');
    }
    public function getClientSecret()
    {
        return Configuration::get('WIPEI_PROFILE');
    }
	public function getToken()
    {
		$this->fetchToken();
        return $this->token;
    }
    public function hookdisplayBeforeBodyClosingTag($params)
    {
        return false;
        $curlInit = curl_init('https://api.wipei.com.ar/token');

        $data = array("client_id" => Configuration::get('WIPEI_API'), "client_secret" => Configuration::get('WIPEI_PROFILE'));                                                                    
        $data_string = json_encode($data);   
        //curl_setopt($curlInit,CURLOPT_CONNECTTIMEOUT,10);
        //curl_setopt($curlInit, CURLOPT_CUSTOMREQUEST, "POST");
        //curl_setopt($curlInit, CURLOPT_POSTFIELDS, $data_string);                                                                  

        //curl_setopt($curlInit,CURLOPT_HEADER,true);
        //curl_setopt($curlInit,CURLOPT_NOBODY,true);
        //curl_setopt($curlInit,CURLOPT_RETURNTRANSFER,true);
        //curl_setopt($curlInit, CURLOPT_HTTPHEADER, array(                                                                          
            //'Content-Type: application/json; charset=utf-8',                                                                                
            //'Content-Length: ' . strlen($data_string))                                                                       
        //); 
        $result = file_get_contents('https://api.wipei.com.ar/token', null, stream_context_create(array(
            'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/json' . "\r\n"
            . 'Content-Length: ' . strlen($data_string) . "\r\n",
            'content' => $data_string,
            ),
            )));
        //get answer
        $response = json_decode($result);
        var_dump(($response->access_token));

        $result2 = file_get_contents('https://api.wipei.com.ar/order', null, stream_context_create(array(
            'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/json' . "\r\n"
            . 'authorization: ' . $response->access_token . "\r\n"
            . 'Content-Length: ' . strlen($response->access_token) . "\r\n",
         
            'body'=> 'total: 500' . "\r\n"
                . 'external_reference: 00025'. "\r\n"
                .'url_success: http://success.com'. "\r\n"
                .'url_failure: http://failure.com'. "\r\n"
                .'url_notify: Url para uso del server'
              ),
            )));

        if ($response) {
        } else {
            //echo 'Doppler service down. Try later';
            return false;
        }

        $this->smarty->assign(
            array(
                'titlel' => $titlel,
                'descl' => $descl,
                'dopplerpopup_img_link' => Configuration::get('dopplerpopup_img_link'),
                'default_lang' => (int)$this->context->language->id,
                'dopplerpopup_cover' => Configuration::get('DOPPLER_IMAGE_COVER'),
                'dopplerpopup_width' => Configuration::get('DOPPLER_BOX_WIDTH'),
                'dopplerpopup_BOX_HEIGHT' => Configuration::get('DOPPLER_BOX_HEIGHT'),
                'dopplerpopup_SHOW_TIME' => Configuration::get('DOPPLER_SHOW_TIME'),
                'BTN_COLOR' => Configuration::get('DOPPLER_BTN_COLOR'),
                'TITLE_COLOR' => Configuration::get('DOPPLER_TITLE_COLOR'),
                'id_lang' => $this->context->language->id,
                'dopplerpopup_img' => !Configuration::get('DOPPLER_IMAGE_DISABLE') && file_exists('modules/dopplermail/views/img/dopplerpopup_img_'.(int)$this->context->shop->id.'.jpg'),
                'module_path' => $this->_path,
            )
        );
        return $this->display(
            __FILE__,
            'views/templates/front/dopplerpopup17.tpl',
            $this->getCacheId()
        );
    }
    public function hookDisplayHeader()
    {
        return false;
    }

    public function _displayInfo()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") {
            $sec = "https";
        } else {
            $sec = "http";
        }
        $tok = $this->fetchToken();
    
        $msg = '';
        if ( $tok) {
            $msg = $this->l('Acceso Token OK');
        } else {
            $msg = $this->l('Error en la conexión. Verifique las credenciales');
        }
        $this->context->smarty->assign(
            array(
                'msg' => $msg,
                'idshop' => $this->context->shop->id,
                'iso_code' => $this->context->language->iso_code,
                'baseurl' => str_replace("http", $sec, _PS_BASE_URL_._MODULE_DIR_),
                'baseurl' => str_replace(
                    "http",
                    $sec,
                    _PS_BASE_URL_._MODULE_DIR_
                ),

            )
        );
        return $this->display(
            __FILE__,
            'views/templates/hook/infos.tpl'
        );
    }

    public function getContent()
    {
        $errors = '';
        return $this->postProcess().$this->_displayInfo().$this->renderForm();
    }
}
