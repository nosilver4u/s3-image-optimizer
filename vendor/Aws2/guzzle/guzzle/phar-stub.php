<?php

\Phar::mapPhar('guzzle.phar');
require_once 'phar://guzzle.phar/vendor/symfony/class-loader/Symfony/Component/ClassLoader/UniversalClassLoader.php';
$classLoader = new \S3IO\Aws2\Symfony\Component\ClassLoader\UniversalClassLoader();
$classLoader->registerNamespaces(array('Guzzle' => 'phar://guzzle.phar/src', 'S3IO\\Aws2\\Symfony\\Component\\EventDispatcher' => 'phar://guzzle.phar/vendor/symfony/event-dispatcher', 'Doctrine' => 'phar://guzzle.phar/vendor/doctrine/common/lib', 'Monolog' => 'phar://guzzle.phar/vendor/monolog/monolog/src'));
$classLoader->register();
__halt_compiler();

