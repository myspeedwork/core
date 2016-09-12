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
 * @author Sankar <sankar.suda@gmail.com>
 */
trait CapsuleManagerTrait
{
    /**
     * The current globally used instance.
     *
     * @var object
     */
    protected static $instance;

    /**
     * The container instance.
     *
     * @var \Speedwork\Container\Container
     */
    protected $container;

    public function __construct(Container $container = null)
    {
        if ($container) {
            $this->setContainer($container);
        }
    }

    /**
     * Make this capsule instance available globally.
     */
    public function setAsGlobal()
    {
        static::$instance = $this;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \Speedwork\Container\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param \Speedwork\Container\Container $container
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }
}
