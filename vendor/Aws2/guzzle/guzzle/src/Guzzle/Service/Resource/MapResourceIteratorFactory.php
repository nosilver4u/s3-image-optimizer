<?php

namespace S3IO\Aws2\Guzzle\Service\Resource;

use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
/**
 * Resource iterator factory used when explicitly mapping strings to iterator classes
 */
class MapResourceIteratorFactory extends \S3IO\Aws2\Guzzle\Service\Resource\AbstractResourceIteratorFactory
{
    /** @var array Associative array mapping iterator names to class names */
    protected $map;
    /** @param array $map Associative array mapping iterator names to class names */
    public function __construct(array $map)
    {
        $this->map = $map;
    }
    public function getClassName(\S3IO\Aws2\Guzzle\Service\Command\CommandInterface $command)
    {
        $className = $command->getName();
        if (isset($this->map[$className])) {
            return $this->map[$className];
        } elseif (isset($this->map['*'])) {
            // If a wildcard was added, then always use that
            return $this->map['*'];
        }
        return null;
    }
}
