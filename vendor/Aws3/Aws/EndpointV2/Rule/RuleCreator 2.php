<?php

namespace S3IO\Aws3\Aws\EndpointV2\Rule;

use S3IO\Aws3\Aws\Exception\UnresolvedEndpointException;
class RuleCreator
{
    public static function create($type, $definition)
    {
        switch ($type) {
            case 'endpoint':
                return new EndpointRule($definition);
            case 'error':
                return new ErrorRule($definition);
            case 'tree':
                return new TreeRule($definition);
            default:
                throw new UnresolvedEndpointException('Unknown rule type ' . $type . ' must be of type `endpoint`, `tree` or `error`');
        }
    }
}
