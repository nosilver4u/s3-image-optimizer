<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace S3IO\Aws2\Symfony\Component\EventDispatcher\Debug;

use S3IO\Aws2\Symfony\Component\EventDispatcher\Event;
use S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcherInterface;
use S3IO\Aws2\Symfony\Component\Stopwatch\Stopwatch;
/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class WrappedListener
{
    private $listener;
    private $name;
    private $called;
    private $stoppedPropagation;
    private $stopwatch;
    private $dispatcher;
    public function __construct($listener, $name, \S3IO\Aws2\Symfony\Component\Stopwatch\Stopwatch $stopwatch, \S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher = null)
    {
        $this->listener = $listener;
        $this->name = $name;
        $this->stopwatch = $stopwatch;
        $this->dispatcher = $dispatcher;
        $this->called = false;
        $this->stoppedPropagation = false;
    }
    public function getWrappedListener()
    {
        return $this->listener;
    }
    public function wasCalled()
    {
        return $this->called;
    }
    public function stoppedPropagation()
    {
        return $this->stoppedPropagation;
    }
    public function __invoke(\S3IO\Aws2\Symfony\Component\EventDispatcher\Event $event, $eventName, \S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher)
    {
        $this->called = true;
        $e = $this->stopwatch->start($this->name, 'event_listener');
        \call_user_func($this->listener, $event, $eventName, $this->dispatcher ?: $dispatcher);
        if ($e->isStarted()) {
            $e->stop();
        }
        if ($event->isPropagationStopped()) {
            $this->stoppedPropagation = true;
        }
    }
}
