<?php
declare(strict_types=1);
define('IX_ENVBASE', 'SITE');
define('IX_BASE', dirname(__FILE__));
require_once(IX_BASE . '/vendor/autoload.php');

use ix\HookMachine;
use ix\Container\Container;
use ix\Controller\Controller;
use ix\Application\Application;

/* Language initialization */
(new \i18n(IX_BASE . '/lang/{LANGUAGE}.ini', IX_BASE . '/cache/lang', 'en'))->init();

/* Container hooks */
HookMachine::add([Container::class, 'construct'], '\ix\Container\ContainerHooksHtmlRenderer::hookContainerHtmlRenderer');

/* Application routes */
HookMachine::add([Application::class, 'create_app', 'routeRegister'], (function ($key, $app) {
	$app->get('/[{system}]', \PKAwake\DisplayController::class)->setName('index');
	return $app;
}));
