<?php

namespace S3IO\Aws3\Aws\Api\Serializer;

use S3IO\Aws3\Aws\Api\StructureShape;
use S3IO\Aws3\Aws\Api\Service;
/**
 * @internal
 */
class RestXmlSerializer extends \S3IO\Aws3\Aws\Api\Serializer\RestSerializer
{
    /** @var XmlBody */
    private $xmlBody;
    /**
     * @param Service $api      Service API description
     * @param string  $endpoint Endpoint to connect to
     * @param XmlBody $xmlBody  Optional XML formatter to use
     */
    public function __construct(\S3IO\Aws3\Aws\Api\Service $api, $endpoint, \S3IO\Aws3\Aws\Api\Serializer\XmlBody $xmlBody = null)
    {
        parent::__construct($api, $endpoint);
        $this->xmlBody = $xmlBody ?: new \S3IO\Aws3\Aws\Api\Serializer\XmlBody($api);
    }
    protected function payload(\S3IO\Aws3\Aws\Api\StructureShape $member, array $value, array &$opts)
    {
        $opts['headers']['Content-Type'] = 'application/xml';
        $opts['body'] = (string) $this->xmlBody->build($member, $value);
    }
}
