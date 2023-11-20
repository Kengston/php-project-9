<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use DI\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;

$container = new Container();
AppFactory::setContainer($container);
$app = AppFactory::create();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . "/../templates");
});

$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'pgsql',
    'host' => 'localhost',
    'database' => 'database',
    'username' => 'danillysikov',
    'password' => '',
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

class Url extends Model
{
    protected $table = 'urls';

    protected $fillable = ['name'];
}

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'mainPage.phtml');
});

$app->get('/urls', function ($request, $response) {


    return $this->get('renderer')->render($response, 'allUrlsPage.phtml');
});

$app->post('/analyze', function ($request, $response) {
    $urlName = $request->getParsedBody()['url'];

    $url = new Url(['name' => $urlName]);
    $url->save();

    return $response->withHeader('Location', '/');
});

$app->run();
