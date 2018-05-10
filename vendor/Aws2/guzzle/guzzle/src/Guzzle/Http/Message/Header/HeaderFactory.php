<?php

namespace S3IO\Aws2\Guzzle\Http\Message\Header;

use S3IO\Aws2\Guzzle\Http\Message\Header;
/**
 * Default header factory implementation
 */
class HeaderFactory implements \S3IO\Aws2\Guzzle\Http\Message\Header\HeaderFactoryInterface
{
    /** @var array */
    protected $mapping = array('cache-control' => 'S3IO\\Aws2\\Guzzle\\Http\\Message\\Header\\CacheControl', 'link' => 'S3IO\\Aws2\\Guzzle\\Http\\Message\\Header\\Link');
    public function createHeader($header, $value = null)
    {
        $lowercase = strtolower($header);
        return isset($this->mapping[$lowercase]) ? new $this->mapping[$lowercase]($header, $value) : new \S3IO\Aws2\Guzzle\Http\Message\Header($header, $value);
    }
}
