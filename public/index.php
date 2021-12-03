<?php
declare(strict_types=1);
require_once(dirname(dirname(($p = realpath(__FILE__)) === false ? __FILE__ : $p)) . '/bootstrap.php');

/* Hack to allow PHP development server to serve static files */
if (php_sapi_name() === 'cli-server') {
	$fileName = dirname(__FILE__) . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
	if (file_exists($fileName) && !is_dir($fileName)) return false;
}

/* Load configuration */
\PKAwake\Configuration::load();

/* Run application */
(new \ix\Application\Application())->create_app()->run();
