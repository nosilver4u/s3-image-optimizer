<?php

/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace S3IO\Aws2\Aws\S3\Sync;

use FilesystemIterator as FI;
use S3IO\Aws2\Aws\Common\Model\MultipartUpload\AbstractTransfer;
use S3IO\Aws2\Aws\S3\Model\Acp;
use S3IO\Aws2\Guzzle\Common\HasDispatcherInterface;
use S3IO\Aws2\Guzzle\Common\Event;
use S3IO\Aws2\Guzzle\Service\Command\CommandInterface;
class UploadSyncBuilder extends \S3IO\Aws2\Aws\S3\Sync\AbstractSyncBuilder
{
    /** @var string|Acp Access control policy to set on each object */
    protected $acp = 'private';
    /** @var int */
    protected $multipartUploadSize;
    /**
     * Set the path that contains files to recursively upload to Amazon S3
     *
     * @param string $path Path that contains files to upload
     *
     * @return $this
     */
    public function uploadFromDirectory($path)
    {
        $this->baseDir = realpath($path);
        $this->sourceIterator = $this->filterIterator(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::FOLLOW_SYMLINKS)));
        return $this;
    }
    /**
     * Set a glob expression that will match files to upload to Amazon S3
     *
     * @param string $glob Glob expression
     *
     * @return $this
     * @link http://www.php.net/manual/en/function.glob.php
     */
    public function uploadFromGlob($glob)
    {
        $this->sourceIterator = $this->filterIterator(new \GlobIterator($glob, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS | \FilesystemIterator::FOLLOW_SYMLINKS));
        return $this;
    }
    /**
     * Set a canned ACL to apply to each uploaded object
     *
     * @param string $acl Canned ACL for each upload
     *
     * @return $this
     */
    public function setAcl($acl)
    {
        $this->acp = $acl;
        return $this;
    }
    /**
     * Set an Access Control Policy to apply to each uploaded object
     *
     * @param Acp $acp Access control policy
     *
     * @return $this
     */
    public function setAcp(\S3IO\Aws2\Aws\S3\Model\Acp $acp)
    {
        $this->acp = $acp;
        return $this;
    }
    /**
     * Set the multipart upload size threshold. When the size of a file exceeds this value, the file will be uploaded
     * using a multipart upload.
     *
     * @param int $size Size threshold
     *
     * @return $this
     */
    public function setMultipartUploadSize($size)
    {
        $this->multipartUploadSize = $size;
        return $this;
    }
    protected function specificBuild()
    {
        $sync = new \S3IO\Aws2\Aws\S3\Sync\UploadSync(array('client' => $this->client, 'bucket' => $this->bucket, 'iterator' => $this->sourceIterator, 'source_converter' => $this->sourceConverter, 'target_converter' => $this->targetConverter, 'concurrency' => $this->concurrency, 'multipart_upload_size' => $this->multipartUploadSize, 'acl' => $this->acp));
        return $sync;
    }
    protected function addCustomParamListener(\S3IO\Aws2\Guzzle\Common\HasDispatcherInterface $sync)
    {
        // Handle the special multi-part upload event
        parent::addCustomParamListener($sync);
        $params = $this->params;
        $sync->getEventDispatcher()->addListener(\S3IO\Aws2\Aws\S3\Sync\UploadSync::BEFORE_MULTIPART_BUILD, function (\S3IO\Aws2\Guzzle\Common\Event $e) use($params) {
            foreach ($params as $k => $v) {
                $e['builder']->setOption($k, $v);
            }
        });
    }
    protected function getTargetIterator()
    {
        return $this->createS3Iterator();
    }
    protected function getDefaultSourceConverter()
    {
        return new \S3IO\Aws2\Aws\S3\Sync\KeyConverter($this->baseDir, $this->keyPrefix . $this->delimiter, $this->delimiter);
    }
    protected function getDefaultTargetConverter()
    {
        return new \S3IO\Aws2\Aws\S3\Sync\KeyConverter('s3://' . $this->bucket . '/', '', DIRECTORY_SEPARATOR);
    }
    protected function addDebugListener(\S3IO\Aws2\Aws\S3\Sync\AbstractSync $sync, $resource)
    {
        $sync->getEventDispatcher()->addListener(\S3IO\Aws2\Aws\S3\Sync\UploadSync::BEFORE_TRANSFER, function (\S3IO\Aws2\Guzzle\Common\Event $e) use($resource) {
            $c = $e['command'];
            if ($c instanceof CommandInterface) {
                $uri = $c['Body']->getUri();
                $size = $c['Body']->getSize();
                fwrite($resource, "Uploading {$uri} -> {$c['Key']} ({$size} bytes)\n");
                return;
            }
            // Multipart upload
            $body = $c->getSource();
            $totalSize = $body->getSize();
            $progress = 0;
            fwrite($resource, "Beginning multipart upload: " . $body->getUri() . ' -> ');
            fwrite($resource, $c->getState()->getFromId('Key') . " ({$totalSize} bytes)\n");
            $c->getEventDispatcher()->addListener(\S3IO\Aws2\Aws\Common\Model\MultipartUpload\AbstractTransfer::BEFORE_PART_UPLOAD, function ($e) use(&$progress, $totalSize, $resource) {
                $command = $e['command'];
                $size = $command['Body']->getContentLength();
                $percentage = number_format($progress / $totalSize * 100, 2);
                fwrite($resource, "- Part {$command['PartNumber']} ({$size} bytes, {$percentage}%)\n");
                $progress += $size;
            });
        });
    }
}
