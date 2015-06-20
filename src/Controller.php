<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Speedwork\Core;

use Speedwork\Util\Router;
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail>
 */
abstract class Controller
{
    public $post   = [];
    public $get    = [];
    public $data   = [];
    public $server = [];

    public function __construct()
    {
        $this->post   = &$_POST;
        $this->get    = &$_GET;
        $this->data   = &$_REQUEST;
        $this->server = &$_SERVER;
    }

    /**
     * magic method to get property.
     *
     * @param string $key value to get
     *
     * @return bool
     */
    public function __get($key)
    {
        return Registry::get($key);
    }

    /**
     * store the values in registry.
     *
     * @param string $key   key to store
     * @param mixed  $value valye to store
     */
    public function __set($key, $value)
    {
        Registry::set($key, $value);
    }

    /**
     * get the key from stored data value.
     *
     * @param string $key The name of the variable to access
     *
     * @return mixed returns your stored value
     */
    public function get($key)
    {
        return Registry::get($key);
    }

    /**
     * store key value pair in registry.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store in registry
     */
    public function set($key, $value)
    {
        Registry::set($key, $value);
    }

    /**
     * sets is same as set but it set in registry and theme.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store
     */
    public function sets($key, $value)
    {
        Registry::set($key, $value);
        $this->assign($key, $value);
    }

    public function assign($key, $value)
    {
        $this->tengine->assignByRef($key, $value);
    }

    public function release($key)
    {
        return $this->tengine->getTemplateVars($key);
    }

    public function ajaxRequest($url, $total = 0, $params = [])
    {
        if ($this->is_api_request) {
            return true;
        }

        if ($this->is_ajax_request == false) {
            $url = (empty($url)) ? Utility::currentUrl() : Router::link($url);

            $method = strtoupper($params['method']);
            $method = ($method && in_array($method, ['POST','GET'])) ? $method : 'GET';

            unset($params['method']);
            $return = '<form name="ajax_form" id="ajax_load_component_form" method="'.$method.'" action="'.$url.'">
            <input type="hidden" id="page_number" name="page" value="2" />
            <input type="hidden" id="total_results" name="total" value="'.$total.'" />';

            foreach ($params as $k => $v) {
                $return .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
            }

            $return .= '</form/>';
            $this->assign('autoloadajaxquery', $return);
        }

        $this->assign('params', $params);
    }
}
