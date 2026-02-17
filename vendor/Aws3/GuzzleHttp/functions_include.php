<?php

namespace S3IO\Aws3;

// Don't redefine the functions if included multiple times.
if (!\function_exists('S3IO\Aws3\GuzzleHttp\describe_type')) {
    require __DIR__ . '/functions.php';
}
