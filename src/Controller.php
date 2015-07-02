<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
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
class Controller extends Di
{
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
