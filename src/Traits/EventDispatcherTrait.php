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

use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
trait EventDispatcherTrait
{
    /**
     * @param string $eventName
     * @param Event  $event
     */
    protected function dispatch($name, $event = null)
    {
        return $this->get('events')->dispatch($name, $event);
    }

    protected function fire($name, $params = null)
    {
        $event = $this->event($name, $params);
        $this->get('events')->dispatch($name, $event);

        return $event;
    }

    protected function event($name, $params = [])
    {
        return new GenericEvent($name, $params);
    }
}
