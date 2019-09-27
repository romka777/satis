<?php

use Aws\S3\S3Client;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

if (!array_key_exists('APP_ENV', $_SERVER)) {
    $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? null;
}

if ('prod' !== $_SERVER['APP_ENV']) {
    if (!class_exists(Dotenv::class)) {
        throw new RuntimeException('The "APP_ENV" environment variable is not set to "prod". Please run "composer require symfony/dotenv" to load the ".env" files configuring the application.');
    }

    (new Dotenv())->loadEnv(dirname(__DIR__).'/.env');
}

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] ?: $_ENV['APP_ENV'] ?: 'dev';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? 'prod' !== $_SERVER['APP_ENV'];
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = (int) $_SERVER['APP_DEBUG'] || filter_var($_SERVER['APP_DEBUG'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';

$app = AppFactory::create();
$app->addRoutingMiddleware();

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents(__DIR__.'/index.html'));
    return $response;
});

$app->get('/packages.json', function (Request $request, Response $response, $args) {
    $response->getBody()->write(file_get_contents(__DIR__.'/packages.json'));
    return $response;
});

$app->get('/dist/{path:.*}', function (Request $request, Response $response, $args) {
    $s3Config = [
        'credentials' => [
            'key' => getenv('S3_KEY'),
            'secret' => getenv('S3_SECRET')
        ],
        'region' => getenv('S3_REGION') ?: 'ru-central1',
        'endpoint' => getenv('S3_ENDPOINT') ?: 'http://storage.yandexcloud.net',
        'version' => '2006-03-01',
    ];

    $s3Client = new S3Client($s3Config);

    $s3Cmd = $s3Client->getCommand('GetObject', [
        'Bucket' => 'satis',
        'Key' => $args['path']
    ]);

    $s3Request = $s3Client->createPresignedRequest($s3Cmd, '+15 minutes');
    $presignedUrl = (string)$s3Request->getUri();

    return $response
        ->withHeader('Location', $presignedUrl)
        ->withStatus(302);
});

$app->run();