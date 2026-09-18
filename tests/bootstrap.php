<?php

declare(strict_types=1);

use OCP\App\IAppManager;
use OCP\Server;

if (!defined('PHPUNIT_RUN')) {
    define('PHPUNIT_RUN', 1);
}

require_once __DIR__.'/../vendor/autoload.php';

require_once __DIR__.'/../../../lib/base.php';

// Release images omit Nextcloud's own test suite.
$coreTestAutoload = __DIR__.'/../../../tests/autoload.php';
if (is_file($coreTestAutoload)) {
    require_once $coreTestAutoload;
}

Server::get(IAppManager::class)->loadApp('memories');
