<?php

namespace S3IO\Aws3;

// Don't redefine the functions if included multiple times.
if (!\function_exists('S3IO\\Aws3\\GuzzleHttp\\Psr7\\str')) {
    require __DIR__ . '/functions.php';
}
