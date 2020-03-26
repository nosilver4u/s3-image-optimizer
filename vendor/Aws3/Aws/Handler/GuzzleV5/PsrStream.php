<?php

namespace S3IO\Aws3\Aws\Handler\GuzzleV5;

use S3IO\Aws3\GuzzleHttp\Stream\StreamDecoratorTrait;
use S3IO\Aws3\GuzzleHttp\Stream\StreamInterface as GuzzleStreamInterface;
use S3IO\Aws3\Psr\Http\Message\StreamInterface as Psr7StreamInterface;
/**
 * Adapts a Guzzle 5 Stream to a PSR-7 Stream.
 *
 * @codeCoverageIgnore
 */
class PsrStream implements \S3IO\Aws3\Psr\Http\Message\StreamInterface
{
    use StreamDecoratorTrait;
    /** @var GuzzleStreamInterface */
    private $stream;
    public function __construct(\S3IO\Aws3\GuzzleHttp\Stream\StreamInterface $stream)
    {
        $this->stream = $stream;
    }
    public function rewind()
    {
        $this->stream->seek(0);
    }
    public function getContents()
    {
        return $this->stream->getContents();
    }
}
