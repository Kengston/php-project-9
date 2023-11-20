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
session_start();

$container->set('renderer', function () {
    return new PhpRenderer(__DIR__ . "/../templates");
});

$container->set('flash', function () {
   return new \Slim\Flash\Messages();
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
    $urls = Url::orderBy('created_at', 'desc')->get();

    return $this->get('renderer')->render($response, 'allUrlsPage.phtml', [
        'urls' => $urls
    ]);
});

$app->get('/urls/{id}', function ($request, $response, $args) {
   $urlId = $args['id'];

   $url = Url::find($urlId);

   if (!$url) {
       return $this->get('renderer')->render($response, 'urlNotFound.phtml');
   }

   $flash = $this->get('flash');

   $successMessages = $flash->getMessage('success');
   $errorMessages = $flash->getMessage('error');

    return $this->get('renderer')->render($response, 'singleUrlPage.phtml', [
        'url' => $url,
        'successMessages' => $successMessages,
        'errorMessages' => $errorMessages
    ]);
})->setName('singleUrlPage');

$app->post('/analyze', function ($request, $response) use ($app) {
    $urlName = $request->getParsedBody()['url'];

    $existingUrl = Url::where('name', $urlName)->first();

    if ($existingUrl === null) {
        $url = new Url(['name' => $urlName]);
        $url->save();

        $flash = $this->get('flash');
        $flash->addMessage('success', 'Страница успешно добавлена');

        $id = $url->id;
    } else {
        $id = $existingUrl->id;

        $flash = $this->get('flash');
        $flash->addMessage('error', 'Страница уже существует');
    }

    $redirectedUrl = $app->getRouteCollector()->getRouteParser()->urlFor('singleUrlPage', ['id' => $id]);

    return $response->withRedirect($redirectedUrl);
});

$app->run();
