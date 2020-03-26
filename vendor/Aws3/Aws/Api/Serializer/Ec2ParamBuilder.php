<?php

namespace S3IO\Aws3\Aws\Api\Serializer;

use S3IO\Aws3\Aws\Api\Shape;
use S3IO\Aws3\Aws\Api\ListShape;
/**
 * @internal
 */
class Ec2ParamBuilder extends \S3IO\Aws3\Aws\Api\Serializer\QueryParamBuilder
{
    protected function queryName(\S3IO\Aws3\Aws\Api\Shape $shape, $default = null)
    {
        return $shape['queryName'] ?: ucfirst($shape['locationName']) ?: $default;
    }
    protected function isFlat(\S3IO\Aws3\Aws\Api\Shape $shape)
    {
        return false;
    }
    protected function format_list(\S3IO\Aws3\Aws\Api\ListShape $shape, array $value, $prefix, &$query)
    {
        // Handle empty list serialization
        if (!$value) {
            $query[$prefix] = false;
        } else {
            $items = $shape->getMember();
            foreach ($value as $k => $v) {
                $this->format($items, $v, $prefix . '.' . ($k + 1), $query);
            }
        }
    }
}
