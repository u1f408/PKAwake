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

/* Application middleware */
HookMachine::add([Application::class, 'create_app', 'preMiddleware'], '\ix\Application\ApplicationHooksTwig::hookApplicationMiddlewareTwig');

/* Container hooks */
HookMachine::add([Container::class, 'construct'], '\ix\Container\ContainerHooksHtmlRenderer::hookContainerHtmlRenderer');
HookMachine::add([Container::class, 'construct'], '\PKAwake\ContainerHooksTwig::hookContainerTwig');

/* Application routes */
HookMachine::add([Application::class, 'create_app', 'routeRegister'], (function ($key, $app) {
	$app->get('/[{system:[a-z]{5}}]', \PKAwake\DisplayController::class)->setName('index');
	$app->get('/api[/{system:[a-z]{5}}]', \PKAwake\ApiController::class)->setName('api');
	return $app;
}));
