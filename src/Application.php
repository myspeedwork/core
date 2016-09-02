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
    const VERSION = 'v1.0-dev';

    public function __construct()
    {
        parent::__construct();
        static::setInstance($this);
    }

    public function run()
    {
        $this->get('template')->render();
    }
}
