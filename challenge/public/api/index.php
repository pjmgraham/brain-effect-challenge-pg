<?php

use DI\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';

// DIC
$container = new Container();
$container->set('redisClient', function () {
    return new Predis\Client(['host' => 'redis']);
});

AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('/api');
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

/**
 * TODO
 * Here you can write your own API endpoints.
 * You can use Redis and/or cookies for data persistence.
 *
 * Find below an example of a GET endpoint that uses redis to temporarily store a name,
 * and cookies to keep track of an event date and time.
 */

$app->get('/startReading', function (Request $request, Response $response) {

    $redis = $this->get('redisClient');
    date_default_timezone_set('Asia/Singapore');
    $oldStartTime = $redis->get('startTime');
    if ($oldStartTime) {
        $response->getBody()->write('The challenge has already started at ' . $oldStartTime);
        return $response;
    } else {
      $startTime = date('Y-m-d H:i:s');
      $redis->set('startTime', $startTime);
      $response->getBody()->write('The challenge has started at ' . $startTime);
      return $response;
    }
});

$app->get('/stopReading', function (Request $request, Response $response) {

    $redis = $this->get('redisClient');
    date_default_timezone_set('Asia/Singapore');
    $startTime = $redis->get('startTime');
    if (!$startTime) {
        $response->getBody()->write('The challenge has not started yet');
        return $response;
    } else {
      $endTime = date('Y-m-d H:i:s');
      $redis->set('endTime', $endTime);
      $totalTime = (strtotime($endTime) - strtotime($startTime));
      $hours = floor($totalTime / 3600);
      $minutes = floor(($totalTime / 60) % 60);
      $seconds = $totalTime % 60;
      $response->getBody()->write('You began reading at ' . $startTime . ' and finished at at ' . $endTime . '. Your total time spent was ' . $hours . " hours, " . $minutes . " minutes, " . $seconds . " seconds.");
      $redis->del("startTime");
      return $response;
    }
});



$app->get('/hello/{name}', function (Request $request, Response $response, $args) {

    // Redis usage example:
    /** @var \Predis\Client $redisClient */
    $redisClient = $this->get('redisClient');
    $oldName = $redisClient->get('name');
    if (is_string($oldName)) {
        $name = $oldName;
    } else {
        $redisClient->set('name', $args['name'], 'EX', 10);
        $name = $args['name'];
    }

    // Setting a cookie example:
    $cookieValue = '';
    if (empty($_COOKIE["FirstSalutationTime"])) {
        $cookieName = "FirstSalutationTime";
        $cookieValue = (string)time();
        $expires = time() + 60 * 60 * 24 * 30; // 30 days.
        setcookie($cookieName, $cookieValue, $expires, '/');
    }

    // Response example:
    $response->getBody()->write(json_encode([
        'name' => $name,
        'salutation' => "Hello, $name!",
        'first_salutation_time' => $_COOKIE["FirstSalutationTime"] ?? $cookieValue,
    ], JSON_THROW_ON_ERROR));

    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
