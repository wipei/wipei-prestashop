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

class Resource extends Module
{
    private $_client;
    private $_functions;
    public function __construct($client, $config)
    {
        $this->_client = $client;
        $this->_functions = $config['functions'];
    }
    public function call($messageName, $args)
    {
        return  $this->_client->soapCall($this->_functions[$messageName]['methodApiName'], $args);
    }
}
