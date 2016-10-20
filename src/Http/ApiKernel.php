<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Core\Http;

use Speedwork\Core\RestApi;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class ApiKernel extends Kernel
{
    /**
     * Api Cache.
     *
     * @var bool
     */
    protected $cache = false;

    /**
     * Public api methods.
     *
     * @var array
     */
    protected $public = [];

    /**
     * RestApi object.
     *
     * @var \Speedwork\Core\RestApi
     */
    protected $api;

    /**
     * Start to initialize the restapi.
     *
     * @return \Speedwork\Core\RestApi
     */
    public function start()
    {
        if (!isset($this->api)) {
            $app       = $this->getApplication();
            $this->api = new RestApi();
            $this->api->setContainer($app);
        }

        return $this->api;
    }

    /**
     * Access the api obejct.
     *
     * @return \Spedwork\Core\RestApi
     */
    public function getApi()
    {
        $this->start();

        return $this->api;
    }

    /**
     * Enable or disable the api cache.
     *
     * @param string $duration Duration to enable cache
     */
    public function setCache($duration = '+10 MINUTES')
    {
        if (is_string($duration)) {
            $this->cache = $duration;
        }

        return $this;
    }

    /**
     * Set the api public methods which does not need api key to access.
     *
     * @param array $public Public methods
     */
    public function setPublicMethods($public = [])
    {
        if ($public === false) {
            $this->public = false;

            return $this;
        }

        if (is_array($public)) {
            $this->public = array_merge($this->public, $public);
        } else {
            $this->public[] = $public;
        }

        return $this;
    }

    /**
     * Render the request.
     *
     * @param Request $request
     *
     * @return string|array
     */
    protected function renderRequest(Request $request)
    {
        return $this->getApi()
                ->handle($request)
                ->setPublicMethods($this->public)
                ->setCache($this->cache)
                ->processMethod();
    }
}
