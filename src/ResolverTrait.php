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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
trait ResolverTrait
{
    public function controller($component, $options = [])
    {
        return $this->get('resolver')->loadController($component, '', $options, 2);
    }

    public function model($component)
    {
        return $this->get('resolver')->loadModel($component);
    }

    public function action($option, &$options = [])
    {
        list($option, $view) = explode('.', $option);
        echo $this->get('resolver')->component($option, $view, $options);
    }

    public function component($option, &$options = [])
    {
        list($option, $view) = explode('.', $option);

        return $this->get('resolver')->component($option, $view, $options);
    }

    public function module($option, &$options = [])
    {
        list($option, $view) = explode('.', $option);

        return $this->get('resolver')->module($option, $view, $options);
    }

    public function helper($name)
    {
        return $this->get('resolver')->helper($name);
    }

    public function widget($name, $options = [], $include = false)
    {
        $this->get('resolver')->widget($name, $options, $include);
    }

    public function conditions(&$data = [], $alias = null)
    {
        return $this->get('resolver')->conditions($data, $alias);
    }

    public function ordering(&$data = [], $ordering = [])
    {
        return $this->get('resolver')->ordering($data, $ordering);
    }

    public function ajax($url = null, $total = 0, $params = [])
    {
        $is_ajax = $this->get('is_ajax_request');

        $ajax           = [];
        $ajax['enable'] = ($is_ajax == false) ? true : false;

        if ($is_ajax == false) {
            $url = (empty($url)) ? Utility::currentUrl() : $this->link($url);

            $method = strtoupper($params['method']);
            $method = ($method && in_array($method, ['POST', 'GET'])) ? $method : 'POST';

            unset($params['method']);

            $start = '<form id="ajax_form" method="'.$method.'" action="'.$url.'">';
            $start .= '<input type="hidden" id="page" name="page" value="2" />';
            $start .= '<input type="hidden" id="total" name="total" value="'.$total.'" />';

            foreach ($params as $k => $v) {
                $start .= '<input type="hidden" name="'.$k.'" value="'.$v.'" />';
            }

            $end    = '</form>';
            $return = $start.$end;

            $this->assign('autoloadajaxquery', $return);

            $ajax['form']  = $return;
            $ajax['start'] = $start;
            $ajax['end']   = $end;
        }

        $params['total'] = $total;
        $params['url']   = $url;
        $ajax['params']  = $params;
        $this->assign('ajax', $ajax);
    }
}
