<?php
use Amp\ByteStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Illuminate\Container\Container;
use SebastianBergmann\Timer\Timer;

require 'controller.php';

require __DIR__.'/../vendor/autoload.php';

//create Illuminate Container, outside Laravel Framework.
$container = Container::getInstance();

//standalone timer class of PHPUnit
$timer = new Timer;
$container->instance('\SebastianBergmann\Timer\Timer', $timer);
$requestTimer = $container->get('\SebastianBergmann\Timer\Timer');
$requestTimer->start();


// Note any PSR-3 logger may be used, Monolog is only an example.
$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());

$logger = new Logger('server');
$logger->pushHandler($logHandler);

$container->instance('\Amp\Log', $logger);
$logger = $container->get('\Amp\Log');

//Define HTTP Server for direct access
$server = SocketHttpServer::createForDirectAccess($logger);

$container->instance('\Amp\Http\Server\SocketHttpServer', $server);
$server = $container->get('\Amp\Http\Server\SocketHttpServer');

//Define error handler
$errorHandler = new DefaultErrorHandler();

$container->instance('\Amp\Http\Server\DefaultErrorHandler', $errorHandler);
$errorHandler = $container->get('\Amp\Http\Server\DefaultErrorHandler');

//Define router
$router = new Router($server, $logger, $errorHandler);

$container->instance('\Amp\Http\Server\Router', $router);
$router = $container->get('\Amp\Http\Server\Router');

 $fallback = new ClosureRequestHandler(function () {
            return new Response(HttpStatus::NO_CONTENT);
        });

//$router->addMiddleware(new Amp\Http\Server\Middleware\CompressionMiddleware());


$router->addRoute('GET', '/', new ClosureRequestHandler(
    function () {
        return new Response(
            status: HttpStatus::OK,
            headers: ['content-type' => 'text/plain'],
            body: 'Hello, world! this is default page based on router',
        );
    },
));

$router->addRoute('GET', '/{name}', new Controller());

//Define fallback
$router->setFallback($fallback);

//$server->expose('127.0.0.1:1337');
$server->expose('0.0.0.0:1337');

/* $server->expose(new Socket\InternetAddress("0.0.0.0", 1337));
$server->expose(new Socket\InternetAddress("[::]", 1337));
 */

//$server->start($requestHandler, $errorHandler);
$server->start($router, $errorHandler);

// Serve requests until SIGINT or SIGTERM is received by the process.
Amp\trapSignal([SIGINT, SIGTERM]);

$server->stop();