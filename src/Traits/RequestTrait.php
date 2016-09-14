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

use Speedwork\Container\Container;

/**
 * Request variables handle.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
trait RequestTrait
{
    protected $post   = [];
    protected $get    = [];
    protected $data   = [];
    protected $server = [];
    protected $cookie = [];

    public function __construct(Container $container = null)
    {
        parent::__construct($container);
        $this->setRequestParams();
    }

    public function setRequestParams()
    {
        $this->post   = &$_POST;
        $this->get    = &$_GET;
        $this->data   = &$_REQUEST;
        $this->server = &$_SERVER;
        $this->cookie = &$_COOKIE;

        return $this;
    }

    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }
}
