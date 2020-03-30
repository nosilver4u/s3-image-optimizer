<?php

namespace S3IO\Aws3\Aws\S3\UseArnRegion;

use Aws;
use S3IO\Aws3\Aws\S3\UseArnRegion\Exception\ConfigurationException;
class Configuration implements \S3IO\Aws3\Aws\S3\UseArnRegion\ConfigurationInterface
{
    private $useArnRegion;
    public function __construct($useArnRegion)
    {
        $this->useArnRegion = \S3IO\Aws3\Aws\boolean_value($useArnRegion);
        if (is_null($this->useArnRegion)) {
            throw new \S3IO\Aws3\Aws\S3\UseArnRegion\Exception\ConfigurationException("'use_arn_region' config option" . " must be a boolean value.");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function isUseArnRegion()
    {
        return $this->useArnRegion;
    }
    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return ['use_arn_region' => $this->isUseArnRegion()];
    }
}
