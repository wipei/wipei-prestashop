<?php
include_once(dirname(__FILE__).'/../../config/config.inc.php');
/*require_once(dirname(__FILE__).'/../../init.php');*/

if (_PS_VERSION_ > '1.5.0.0') {
    /*if(_PS_VERSION_ > "1.5.0.0" && _PS_VERSION_ < "1.5.4.0"){@include_once(dirname(__FILE__).'/../../header.php');}*/
    include_once(dirname(__FILE__).'/wipei.php');
    if (_PS_VERSION_ > '1.5.0.0') {
        $context = Context::getContext();
    }
    $errors = array();
    $array = array(
        'id' => Tools::getValue('id')
    );
    $controller = new FrontController();
    $productinfo = new Wipei();
    $controller->init();
    Tools::redirect(
        Context::getContext()->link->getModuleLink(
            'wipei',
            'notify',
            $array,
            null
        )
    );
}
