<?php

namespace S3IO\Aws2\Guzzle\Http\QueryAggregator;

use S3IO\Aws2\Guzzle\Http\QueryString;
/**
 * Aggregates nested query string variables using commas
 */
class CommaAggregator implements \S3IO\Aws2\Guzzle\Http\QueryAggregator\QueryAggregatorInterface
{
    public function aggregate($key, $value, \S3IO\Aws2\Guzzle\Http\QueryString $query)
    {
        if ($query->isUrlEncoding()) {
            return array($query->encodeValue($key) => implode(',', array_map(array($query, 'encodeValue'), $value)));
        } else {
            return array($key => implode(',', $value));
        }
    }
}
