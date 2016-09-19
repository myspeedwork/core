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

use Speedwork\Container\BootableInterface;
use Speedwork\Container\Container;
use Speedwork\Container\EventListenerInterface;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Application extends Container
{
    /**
     * The Speedwork framework version.
     *
     * @var string
     */
    const VERSION = 'v1.0-dev';

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The base path for the installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * The environment file to load during bootstrapping.
     *
     * @var string
     */
    protected $environmentFile = '.env';

    /**
     * Application default paths.
     *
     * @var array
     */
    protected $paths = [];

    public function __construct($basePath = null)
    {
        parent::__construct();
        static::setInstance($this);

        if ($basePath) {
            $this->setBasePath($basePath);
        }
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
            if ($provider instanceof EventListenerInterface) {
                $provider->subscribe($this, $this['events']);
            }

            if ($provider instanceof BootableInterface) {
                $provider->boot($this);
            }
        }
    }

    /**
     * Set the base path for the application.
     *
     * @param string $basePath
     *
     * @return $this
     */
    public function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/').DS;

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @return string
     */
    public function basePath()
    {
        return $this->basePath;
    }

    /**
     * Bind all of the application paths in the container.
     */
    protected function bindPathsInContainer()
    {
        $paths = [
            'env'      => '.env',
            'base'     => '',
            'config'   => 'config'.DS,
            'public'   => 'public'.DS,
            'static'   => 'public'.DS.'static'.DS,
            'images'   => 'public'.DS.'uploads'.DS,
            'themes'   => 'public'.DS.'themes'.DS,
            'upload'   => 'public'.DS.'uploads'.DS,
            'media'    => 'public'.DS.'media'.DS,
            'email'    => 'public'.DS.'email'.DS,
            'pcache'   => 'public'.DS.'cache'.DS,
            'storage'  => 'storage'.DS,
            'tmp'      => 'storage'.DS.'tmp'.DS,
            'cache'    => 'storage'.DS.'cache'.DS,
            'logs'     => 'storage'.DS.'logs'.DS,
            'log'      => 'storage'.DS.'logs'.DS,
            'lang'     => 'storage'.DS.'lang'.DS,
            'views'    => 'storage'.DS.'views'.DS,
            'database' => 'storage'.DS.'database'.DS,
        ];

        $this->set('path', $this->basePath().'system'.DS);
        $this->paths['path'] = $this->basePath().'system'.DS;

        foreach ($paths as $name => $path) {
            $this->set('path.'.$name, $this->basePath().$path);
            $this->paths['path.'.$name] = $this->basePath().$path;
        }

        return $this;
    }

    /**
     * Get the path to application directories.
     *
     * @param string $name Name of the path
     *
     * @return string|array Complete path or all paths
     */
    public function getPath($name = null)
    {
        if ($name) {
            return $this->paths[$name];
        }

        return $this->paths;
    }

    /**
     * Get or check the current application environment.
     *
     * @param  mixed
     *
     * @return string|bool
     */
    public function environment($envs = [])
    {
        if (count($envs) > 0) {
            foreach ($envs as $env) {
                if (preg_match($env, $this['env'])) {
                    return true;
                }
            }

            return false;
        }

        return $this['env'];
    }

    /**
     * Terminate the application.
     */
    public function terminate()
    {
    }
}
