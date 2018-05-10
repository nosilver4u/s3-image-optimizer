<?php

namespace S3IO\Aws2\Guzzle\Common;

use S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcher;
use S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcherInterface;
use S3IO\Aws2\Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * Class that holds an event dispatcher
 */
class AbstractHasDispatcher implements \S3IO\Aws2\Guzzle\Common\HasDispatcherInterface
{
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;
    public static function getAllEvents()
    {
        return array();
    }
    public function setEventDispatcher(\S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        return $this;
    }
    public function getEventDispatcher()
    {
        if (!$this->eventDispatcher) {
            $this->eventDispatcher = new \S3IO\Aws2\Symfony\Component\EventDispatcher\EventDispatcher();
        }
        return $this->eventDispatcher;
    }
    public function dispatch($eventName, array $context = array())
    {
        return $this->getEventDispatcher()->dispatch($eventName, new \S3IO\Aws2\Guzzle\Common\Event($context));
    }
    public function addSubscriber(\S3IO\Aws2\Symfony\Component\EventDispatcher\EventSubscriberInterface $subscriber)
    {
        $this->getEventDispatcher()->addSubscriber($subscriber);
        return $this;
    }
}
