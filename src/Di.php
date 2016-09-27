<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core;

use Speedwork\Core\Traits\CapsuleManagerTrait;
use Speedwork\Core\Traits\EventDispatcherTrait;
use Speedwork\Core\Traits\FilterTrait;
use Speedwork\Util\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @author sankar <sankar.suda@gmail>
 */
class Di
{
    use EventDispatcherTrait;
    use CapsuleManagerTrait;
    use FilterTrait;

    /**
     * Helper fuction for configuration read and write.
     *
     * @param mixed $key     [description]
     * @param mixed $default [description]
     */
    public function config($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->get('config');
        }

        if (is_array($key)) {
            return $this->get('config')->set($key);
        }

        return $this->get('config')->get($key, $default);
    }

    public function assign($key, $value)
    {
        $this->get('engine')->assign($key, $value);

        return $this;
    }

    public function release($key)
    {
        return $this->get('engine')->release($key);
    }

    /**
     * sets is same as set but it set in registry and theme.
     *
     * @param string $key   name of the variable
     * @param mixed  $value value to store
     */
    public function sets($key, $value)
    {
        $this->set($key, $value);
        $this->assign($key, $value);

        return $this;
    }

    /**
     * Redirect the url.
     *
     * @param [type] $url  [description]
     * @param int    $time [description]
     * @param bool   $html [description]
     *
     * @return [type] [description]
     */
    public function redirect($url, $status = 302, $rewrite = true)
    {
        if (empty($url)) {
            $url = 'index.php';
        }

        if ($rewrite) {
            $url = $this->link($url);
        }

        $ajax = $this->get('is_ajax_request');
        if ($ajax) {
            $status = $this->release('status');

            $status['redirect'] = $url;
            $this->assign('status', $status);
            $this->assign('redirect', $url);
            $this->set('redirect', $url);

            return true;
        }

        return new RedirectResponse($url, $status);
    }

    public function link($url)
    {
        return Router::link($url);
    }

    public function toTime($time, $date = false, $format = 'Y-m-d')
    {
        return Utility::strtotime($time, $date, $format);
    }

    /**
     * Short method to get resolver.
     *
     * @return \Speedwork\Core\Resolver Resolver object
     */
    public function resolver()
    {
        return $this->get('resolver');
    }
}
