<?php

namespace S3IO\Aws3\Aws\Api;

/**
 * Base class representing a modeled shape.
 */
class Shape extends \S3IO\Aws3\Aws\Api\AbstractModel
{
    /**
     * Get a concrete shape for the given definition.
     *
     * @param array    $definition
     * @param ShapeMap $shapeMap
     *
     * @return mixed
     * @throws \RuntimeException if the type is invalid
     */
    public static function create(array $definition, \S3IO\Aws3\Aws\Api\ShapeMap $shapeMap)
    {
        static $map = ['structure' => 'S3IO\\Aws3\\Aws\\Api\\StructureShape', 'map' => 'S3IO\\Aws3\\Aws\\Api\\MapShape', 'list' => 'S3IO\\Aws3\\Aws\\Api\\ListShape', 'timestamp' => 'S3IO\\Aws3\\Aws\\Api\\TimestampShape', 'integer' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'double' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'float' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'long' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'string' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'byte' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'character' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'blob' => 'S3IO\\Aws3\\Aws\\Api\\Shape', 'boolean' => 'S3IO\\Aws3\\Aws\\Api\\Shape'];
        if (isset($definition['shape'])) {
            return $shapeMap->resolve($definition);
        }
        if (!isset($map[$definition['type']])) {
            throw new \RuntimeException('Invalid type: ' . print_r($definition, true));
        }
        $type = $map[$definition['type']];
        return new $type($definition, $shapeMap);
    }
    /**
     * Get the type of the shape
     *
     * @return string
     */
    public function getType()
    {
        return $this->definition['type'];
    }
    /**
     * Get the name of the shape
     *
     * @return string
     */
    public function getName()
    {
        return $this->definition['name'];
    }
}
