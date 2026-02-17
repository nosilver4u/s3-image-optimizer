<?php

namespace S3IO\Aws3\GuzzleHttp;

use S3IO\Aws3\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
