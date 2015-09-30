<?php

/**
 * This file is part of the Speedwork framework.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

use Speedwork\Container\Container;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Application extends Container
{
    public function run()
    {
        $this->get('template')->render();
    }
}
