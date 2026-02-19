<?php

namespace S3IO\Aws3\Aws\Handler\GuzzleV5;

use S3IO\Aws3\GuzzleHttp\Stream\StreamDecoratorTrait;
use S3IO\Aws3\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface as Psr7StreamInterface;
/**
 * Adapts a PSR-7 Stream to a Guzzle 5 Stream.
 *
 * @codeCoverageIgnore
 */
class GuzzleStream implements GuzzleStreamInterface
{
    use StreamDecoratorTrait;
    /** @var Psr7StreamInterface */
    private $stream;
    public function __construct(Psr7StreamInterface $stream)
    {
        $this->stream = $stream;
    }
}
