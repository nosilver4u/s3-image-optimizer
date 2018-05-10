<?php

namespace S3IO\Aws2\Guzzle\Service\Resource;

use S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
/**
 * Factory that utilizes multiple factories for creating iterators
 */
class CompositeResourceIteratorFactory implements \S3IO\Aws2\Guzzle\Service\Resource\ResourceIteratorFactoryInterface
{
    /** @var array Array of factories */
    protected $factories;
    /** @param array $factories Array of factories used to instantiate iterators */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }
    public function build(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, array $options = array())
    {
        if (!($factory = $this->getFactory($command))) {
            throw new \S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException('Iterator was not found for ' . $command->getName());
        }
        return $factory->build($command, $options);
    }
    public function canBuild(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command)
    {
        return $this->getFactory($command) !== false;
    }
    /**
     * Add a factory to the composite factory
     *
     * @param ResourceIteratorFactoryInterface $factory Factory to add
     *
     * @return self
     */
    public function addFactory(\S3IO\Aws2\Guzzle\Service\Resource\ResourceIteratorFactoryInterface $factory)
    {
        $this->factories[] = $factory;
        return $this;
    }
    /**
     * Get the factory that matches the command object
     *
     * @param CommandInterface $command Command retrieving the iterator for
     *
     * @return ResourceIteratorFactoryInterface|bool
     */
    protected function getFactory(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command)
    {
        foreach ($this->factories as $factory) {
            if ($factory->canBuild($command)) {
                return $factory;
            }
        }
        return false;
    }
}
