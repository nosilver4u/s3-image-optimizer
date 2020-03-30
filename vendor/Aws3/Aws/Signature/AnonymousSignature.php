<?php

namespace S3IO\Aws3\Aws\Signature;

use S3IO\Aws3\Aws\Credentials\CredentialsInterface;
use S3IO\Aws3\Psr\Http\Message\RequestInterface;
/**
 * Provides anonymous client access (does not sign requests).
 */
class AnonymousSignature implements \S3IO\Aws3\Aws\Signature\SignatureInterface
{
    public function signRequest(\S3IO\Aws3\Psr\Http\Message\RequestInterface $request, \S3IO\Aws3\Aws\Credentials\CredentialsInterface $credentials)
    {
        return $request;
    }
    public function presign(\S3IO\Aws3\Psr\Http\Message\RequestInterface $request, \S3IO\Aws3\Aws\Credentials\CredentialsInterface $credentials, $expires, array $options = [])
    {
        return $request;
    }
}
