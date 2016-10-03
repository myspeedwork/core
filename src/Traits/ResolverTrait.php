<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Traits;

use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
trait ResolverTrait
{
    public function getController($name, $options = [])
    {
        return $this->get('resolver')->getController($name, $options);
    }

    public function getModel($name)
    {
        return $this->get('resolver')->getModel($name);
    }

    public function action($name, &$options = [])
    {
        echo $this->get('resolver')->component($name, $options);
    }

    public function getComponent($name, &$options = [])
    {
        return $this->get('resolver')->component($name, $options);
    }

    public function getModule($name, &$options = [])
    {
        return $this->get('resolver')->module($name, $options);
    }

    public function getHelper($name)
    {
        return $this->get('resolver')->helper($name);
    }

    public function getWidget($name, $options = [], $include = false)
    {
        $this->get('resolver')->widget($name, $options, $include);
    }

    public function ajax($url = null, $total = 0, $params = [])
    {
        $is_api = $this->get('is_api_request');

        if ($is_api) {
            return false;
        }

        if (is_array($total)) {
            $params = $total;
            $total  = 0;
        }

        $is_ajax = $this->get('is_ajax_request');

        $ajax            = [];
        $ajax['enable']  = ($is_ajax === true) ? false : true;
        $ajax['disable'] = $ajax['enable'];

        $form = [];

        $url = (empty($url)) ? Utility::currentUrl() : $this->link($url);

        $method = strtoupper($params['method']);
        $method = ($method && in_array($method, ['POST', 'GET'])) ? $method : 'POST';

        unset($params['method']);

        $start = '<form id="ajax_form" method="'.$method.'" action="'.$url.'">';
        $end   = '</form>';

        $start .= '<input type="hidden" id="page" name="page" value="1" />';
        $start .= '<input type="hidden" id="total" name="total" value="'.$total.'" />';

        foreach ($params as $k => $v) {
            $mid .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
        }

        $ajax['form']  = $start.$mid.$end;
        $ajax['start'] = $start.$mid;
        $ajax['end']   = $end;

        $class           = 'render-'.uniqid();
        $params['class'] = '.'.$class;

        $start          = '<form role="render" class="'.$class.'" method="'.$method.'" action="'.$url.'">';
        $form['start']  = $start.$mid;
        $form['params'] = $mid;
        $form['end']    = $end;
        $form['class']  = $params['class'];

        $ajax['fm'] = $form;

        $params['total'] = $total;
        $params['url']   = $url;
        $ajax['params']  = $params;
        $this->assign('ajax', $ajax);
    }
}
