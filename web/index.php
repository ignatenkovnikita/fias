<?php

use Bravicility\Failure\FailureHandler;
use Bravicility\Http\Request;
use Bravicility\Http\Response\Response;
use Bravicility\Http\Response\TextResponse;
use Bravicility\Router\RouteNotFoundException;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/Moscow');

$container = new Container();
$logger    = $container->getErrorLogger();
FailureHandler::setup(function ($error) use ($logger) {
    (new TextResponse(500, 'Произошла ошибка сервера'))->send();
    $logger->error($error['message'], $error);
    exit;
});

try {
    $request = Request::createFromGlobals();
    $route   = $container->getRouter()->route($request->getMethod(), $request->getUrlPath());
    $request->setOptions($route->vars);

    /** @var Response $response */
    $response = (new $route->class($container))->{$route->method}($request);
} catch (RouteNotFoundException $e) {
    $response = new Response(404);
} catch (BadRequestException $e) {
    $response = new Response(400, $e->getMessage());
}

$response->addHeader('Access-Control-Allow-Origin: *');
$response->send();
