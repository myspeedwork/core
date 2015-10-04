<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Core;

use Cake\Event\Event;
use Cake\Event\EventManagerTrait as BaseEventManagerTrait;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
trait EventManagerTrait
{
    use BaseEventManagerTrait;
    protected function event($name, $params = [])
    {
        return new Event($name, $this, $params);
    }
}
