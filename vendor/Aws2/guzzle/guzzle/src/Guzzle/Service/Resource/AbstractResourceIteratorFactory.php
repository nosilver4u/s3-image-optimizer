<?php

namespace S3IO\Aws2\Guzzle\Service\Resource;

use S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
/**
 * Abstract resource iterator factory implementation
 */
abstract class AbstractResourceIteratorFactory implements \S3IO\Aws2\Guzzle\Service\Resource\ResourceIteratorFactoryInterface
{
    public function build(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command, array $options = array())
    {
        if (!$this->canBuild($command)) {
            throw new \S3IO\Aws2\Guzzle\Common\Exception\InvalidArgumentException('Iterator was not found for ' . $command->getName());
        }
        $className = $this->getClassName($command);
        return new $className($command, $options);
    }
    public function canBuild(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command)
    {
        return (bool) $this->getClassName($command);
    }
    /**
     * Get the name of the class to instantiate for the command
     *
     * @param CommandInterface $command Command that is associated with the iterator
     *
     * @return string
     */
    protected abstract function getClassName(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command);
}
