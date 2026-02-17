<?php

namespace S3IO\Aws3\Aws\S3\S3Transfer\Progress;

interface ProgressBarFactoryInterface
{
    public function __invoke(): ProgressBarInterface;
}
