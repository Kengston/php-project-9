<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use Slim\Flash\Messages;
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

   return $this->get('renderer')->render($response, 'singleUrlPage.phtml', ['url' => $url]);
})->setName('singleUrlPage');

$app->post('/analyze', function ($request, $response) use ($app) {
    $urlName = $request->getParsedBody()['url'];

    $existingUrl = Url::where('name', $urlName)->first();

    if ($existingUrl === null) {
        $url = new Url(['name' => $urlName]);
        $url->save();
        $id = $url->id;
    } else {
        $id = $existingUrl->id;
    }

    $redirectedUrl = $app->getRouteCollector()->getRouteParser()->urlFor('singleUrlPage', ['id' => $id]);

    return $response->withRedirect($redirectedUrl);
});

$app->run();
