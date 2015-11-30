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
use Cake\Event\EventManager;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
trait EventManagerTrait
{
    /**
     * Instance of the Cake\Event\EventManager this object is using
     * to dispatch inner events.
     *
     * @var \Cake\Event\EventManager
     */
    protected $_eventManager = null;

    /**
     * Returns the Cake\Event\EventManager manager instance for this object.
     *
     * You can use this instance to register any new listeners or callbacks to the
     * object events, or create your own events and trigger them at will.
     *
     * @param \Cake\Event\EventManager|null $eventManager the eventManager to set
     *
     * @return \Cake\Event\EventManager
     */
    public function eventManager(EventManager $eventManager = null)
    {
        if ($eventManager !== null) {
            $this->_eventManager = $eventManager;
        } elseif (empty($this->_eventManager)) {
            $this->_eventManager = new EventManager();
        }

        return $this->_eventManager;
    }

    /**
     * Wrapper for creating and dispatching events.
     *
     * Returns a dispatched event.
     *
     * @param string      $name    Name of the event.
     * @param array|null  $data    Any value you wish to be transported with this event to
     *                             it can be read by listeners.
     * @param object|null $subject The object that this event applies to
     *                             ($this by default).
     *
     * @return \Cake\Event\Event
     */
    public function dispatch($name, $data = null, $subject = null)
    {
        if ($subject === null) {
            $subject = $this;
        }

        $event = new Event($name, $subject, $data);
        $this->eventManager()->dispatch($event);

        return $event;
    }

    protected function event($name, $params = [])
    {
        return new Event($name, $this, $params);
    }
}
