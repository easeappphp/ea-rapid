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

require __DIR__.'/../vendor/autoload.php';

class Controller implements RequestHandler {
  public function handleRequest(Request $request) : Response
    {
        return new Response(
            status: HttpStatus::OK,
            headers: ['Content-Type' => 'text/plain'],
            body: 'Hello, world! this is default page that is handled by Requesthandler, through Controller class',
        );
    }
}