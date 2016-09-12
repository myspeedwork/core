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

use Speedwork\Container\Container;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Application extends Container
{
    const VERSION     = 'v1.0-dev';
    protected $booted = false;

    public function __construct()
    {
        parent::__construct();
        static::setInstance($this);
    }

    /**
     * Get the version number of the application.
     *
     * @return string
     */
    public function version()
    {
        return static::VERSION;
    }

    /**
     * Determine if we are running in the console.
     *
     * @return bool
     */
    public function isConsole()
    {
        return php_sapi_name() == 'cli';
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Boots all service providers.
     *
     * This method is automatically called by handle(), but you can use it
     * to boot all service providers when not handling a request.
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        foreach ($this->providers as $provider) {
            if ($provider instanceof EventListenerProviderInterface) {
                $provider->subscribe($this, $this['dispatcher']);
            }

            if ($provider instanceof BootableProviderInterface) {
                $provider->boot($this);
            }
        }
    }

    public function handle()
    {
        if (!$this->booted) {
            $this->boot();
        }

        $this->get('template')->render();
    }

    /**
     * Terminate the application.
     */
    public function terminate()
    {
    }
}
