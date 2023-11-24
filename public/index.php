<?php

namespace App;
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use DI\Container;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use DiDom\Document;


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

class UrlCheck extends Model
{
    protected $table = 'url_checks';

    protected $fillable = [
        'url_id',
        'status_code',
        'h1',
        'title',
        'description',
        'created_at',
        'updated_at'
    ];

    public function url() {
        return $this->belongsTo(Url::class);
    }
}

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'mainPage.phtml');
});

$app->get('/urls', function ($request, $response) {
    $urls = Url::orderBy('created_at', 'desc')->get();

    foreach ($urls as $url) {
        $lastCheck = UrlCheck::where('url_id', $url->id)->orderBy('created_at', 'desc')->first();
        $url->lastCheckDate = $lastCheck ? $lastCheck->created_at : null;
    }

    foreach ($urls as $url) {
        $lastCheck = UrlCheck::where('url_id', $url->id)->orderBy('status_code', 'desc')->first();
        $url->status_code = $lastCheck ? $lastCheck->status_code : null;
    }

    foreach ($urls as $url) {
        $lastCheck = UrlCheck::where('url_id', $url->id)->orderBy('h1', 'desc')->first();
        $url->h1 = $lastCheck ? $lastCheck->h1 : null;
    }

    foreach ($urls as $url) {
        $lastCheck = UrlCheck::where('url_id', $url->id)->orderBy('title', 'desc')->first();
        $url->title = $lastCheck ? $lastCheck->title : null;
    }

    foreach ($urls as $url) {
        $lastCheck = UrlCheck::where('url_id', $url->id)->orderBy('description', 'desc')->first();
        $url->description = $lastCheck ? $lastCheck->description : null;
    }

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

   $urlChecks = UrlCheck::where('url_id', $urlId)->orderBy('created_at', 'desc')->get();

    return $this->get('renderer')->render($response, 'singleUrlPage.phtml', [
        'url' => $url,
        'urlChecks' => $urlChecks,
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

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) {
    $urlId = $args['url_id'];

    $url = Url::find($urlId);

    if (!$url) {
        return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
    }

    $client = new Client();

    try {
        $res = $client->request('GET', $url->name);
        $statusCode = $res->getStatusCode();

        $html = (string) $res->getBody();

        $document = new Document($html);

        $h1 = optional($document->first('h1'))->text();
        $title = optional($document->first('title'))->text();
        $description = optional($document->first('meta[name="description"]'))->getAttribute('content');

        $urlCheck = new UrlCheck();
        $urlCheck->url_id = $urlId;
        $urlCheck->status_code = $statusCode;
        $urlCheck->created_at = date("Y-m-d H:i:s");
        $urlCheck->h1 = $h1;
        $urlCheck->title = $title;
        $urlCheck->description = $description;

        $urlCheck->save();

        return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
    } catch (RequestException $e) {

        /** TODO: catch errors */
        return $response->withHeader('Location', '/urls/' . $urlId)->withStatus(302);
    }
});


$app->run();
