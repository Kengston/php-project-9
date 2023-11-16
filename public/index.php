<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use DI\Container;

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . "/../templates");
});

$app->get('/', function ($request, $response) {

    return $this->get('renderer')->render($response, 'mainPage.phtml');
});

$app->run();
