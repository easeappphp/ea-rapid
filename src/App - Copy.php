<?php
declare(strict_types=1);

namespace EaseAppPHP\EARapid;

use \Amp\ByteStream;
use \Amp\Http\HttpStatus;
use \Amp\Http\Server\DefaultErrorHandler;
use \Amp\Http\Server\Request;
use \Amp\Http\Server\RequestHandler;
use \Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use \Amp\Http\Server\Response;
use \Amp\Http\Server\Router;
use \Amp\Http\Server\SocketHttpServer;
use \Amp\Log\ConsoleFormatter;
use \Amp\Log\StreamHandler;
use \Monolog\Logger;
use \Monolog\Processor\PsrLogMessageProcessor;
use \Illuminate\Container\Container;
use \SebastianBergmann\Timer\Timer;

use \EaseAppPHP\EARapid\Core\EAConfig;
use \EaseAppPHP\EARapid\Core\EAIsConsole;
use \EaseAppPHP\EARapid\Foundation\BaseApplication;

/**
 * Use the fully-qualified AllowDynamicProperties, otherwise the #[AllowDynamicProperties] attribute on "MyClass" WILL NOT WORK.
 */
use \AllowDynamicProperties;

/* use \Illuminate\Container\Container;
use \EaseAppPHP\Core\EAConfig;
use \EaseAppPHP\Core\EAIsConsole;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Server\MiddlewareInterface as Middleware;
use \Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use \Laminas\Diactoros\Response\TextResponse;
use \Laminas\Stratigility\MiddlewarePipe;
use \Psr\Log\LoggerInterface;
use \Laminas\Diactoros\Response\EmptyResponse;
use \EaseAppPHP\Foundation\BaseApplication; */

/**
 * App Class
 *
 */
Class App extends BaseApplication
{
	protected $app;
	protected $container;
	protected $middlewareQueue = [];
	protected $eaIsConsoleinstance;
    protected $eaRequestConsoleStatusResult;
	protected $kernelInstance;
    protected $collectedConfigData = [];
	protected $config = [];        
	protected $eaTimerInstance;
	protected $serverRequestInstance;
	protected $serverRequest;
	protected $initResult;
	protected $eaRouterinstance;
	protected $middlewarePipeQueue;
    protected $middlewarePipeQueueEntries;
	protected $middlewareProcessedResponse;
	protected $serverRequestProcessedResponse;
	protected $serverRequestFinalProcessedResponse;
	protected $emitterStackInstance;
	protected $sapiStreamInstance;
	protected $requestHandlerRunnerServerInstance;
	protected $middlewareProcessor;
	protected $routesList;
	protected $matchedRouteResponse;
	protected $matchedController;
	protected $matchedRouteKey;
	protected $matchedRouteDetails;
	protected $eaConfig;
	protected $argv;
	protected $argc;
	
	/**
	* All of the registered service providers.
	*	
	*/
	protected $serviceProviders = [];
	protected $eaServiceProvidersList;

	/**
	* The names of the loaded service providers.
	*
	* @var array
	*/
	protected $loadedProviders = [];
	protected $eaLoadedServiceProvidersList;	
	protected $response;
		
	
	/**
	 * Accepts .env file path and loads the values into $ENV Superglobals. Also, Creates a Container.
     * Extract Config Values into an array, Create ServerRequest.
	 *
	 * @param object $container
     * @return object
	 */
	public function __construct(Container $container)
    {	
		$this->container = $container;
		
		$this->config = $this->container->get('config');   
		
		/*
		*--------------------------------------------------------------------------
		* Define Default timezone
		*--------------------------------------------------------------------------
		*
		*/
		if (function_exists("date_default_timezone_set")) {
				
			date_default_timezone_set($_ENV['TIMEZONE']);

		}
		
		//Check if the request is based upon Console or Web
		$eaIsConsole = new EAIsConsole();
		$this->container->instance('EAIsConsole', $eaIsConsole);
		//$this->eaIsConsoleInstance = $this->container->get('EAIsConsole')->checkSTDIN();
		
		//Save EA REQUEST Console Status Result to Container
		//$this->container->instance('EARequestConsoleStatusResult', $this->eaIsConsoleInstance);
		$this->container->instance('EARequestConsoleStatusResult', $this->container->get('EAIsConsole')->checkSTDIN());
		$this->eaRequestConsoleStatusResult = $this->container->get('EARequestConsoleStatusResult');   
		
		if ($this->container->get('EARequestConsoleStatusResult') == "Web") {
			
			//Web
			//$serverRequestInstance = \Laminas\Diactoros\ServerRequestFactory::fromGlobals();
			//$container->instance('\Laminas\Diactoros\ServerRequestFactory', $serverRequestInstance);
			//$this->serverRequest = $this->container->get('\Laminas\Diactoros\ServerRequestFactory');
		
			$this->response = $this->container->get('\EaseAppPHP\EARapid\Foundation\BaseWebResponse');
			
		} 

		if ($this->container->get('EARequestConsoleStatusResult') == "Console") {
			
			//Console
			$this->argc = trim(filter_var($GLOBALS['argc'], FILTER_SANITIZE_NUMBER_INT));
			
			$this->argv = $GLOBALS['argv'];
			
			for ($i=0; $i < $this->argc; $i++) {
				
				if (is_numeric($GLOBALS['argv'][$i])) {
					
					$this->argv[$i] = trim(filter_var($GLOBALS['argv'][$i], FILTER_SANITIZE_NUMBER_INT));
					
				} else {
					
					//$this->argv[$i] = trim(filter_var($GLOBALS['argv'][$i], FILTER_SANITIZE_STRING));
					$this->argv[$i] = trim(escapeshellarg($GLOBALS['argv'][$i]));
					
				}
				
			}
			
			$this->container->instance('argc', $this->argc);
			
			$this->container->instance('argv', $this->argv);
			
			
			/* // Note any PSR-3 logger may be used, Monolog is only an example.
			$logHandler = new StreamHandler(ByteStream\getStdout());
			$logHandler->pushProcessor(new PsrLogMessageProcessor());
			$logHandler->setFormatter(new ConsoleFormatter());

			$loggerServer = new Logger('server');
			$loggerServer->pushHandler($logHandler);

			$this->container->instance('\Amp\Log', $loggerServer); */
			$logger = $this->container->get('\Amp\Log');
			
			//Define HTTP Server for direct access
			$server = SocketHttpServer::createForDirectAccess($logger);

			$this->container->instance('\Amp\Http\Server\SocketHttpServer', $server);
			$server = $this->container->get('\Amp\Http\Server\SocketHttpServer');

			//Define error handler
			$errorHandler = new DefaultErrorHandler();

			$this->container->instance('\Amp\Http\Server\DefaultErrorHandler', $errorHandler);
			$errorHandler = $this->container->get('\Amp\Http\Server\DefaultErrorHandler');

			//Define router
			$router = new Router($server, $logger, $errorHandler);

			$this->container->instance('\Amp\Http\Server\Router', $router);
			$router = $this->container->get('\Amp\Http\Server\Router');

			$fallback = new ClosureRequestHandler(function () {
						return new Response(HttpStatus::NO_CONTENT);
					});
			/* $fallbackResponse = new ClosureRequestHandler(function () {
						return new Response(HttpStatus::NO_CONTENT);
					});
					
            $this->container->instance('\Amp\Http\Server\Response', $fallbackResponse);
			$fallback = $this->container->get('\Amp\Http\Server\Response'); */
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

			//$router->addRoute('GET', '/{name}', new Controller());

			//Define fallback
			$router->setFallback($fallback);

			//$server->expose('127.0.0.1:1337');
			$server->expose('0.0.0.0:1337');

			//$server->expose(new Socket\InternetAddress("0.0.0.0", 1337));
			//$server->expose(new Socket\InternetAddress("[::]", 1337));
			 

			//$server->start($requestHandler, $errorHandler);
			$server->start($router, $errorHandler);

			// Serve requests until SIGINT or SIGTERM is received by the process.
			\Amp\trapSignal([SIGINT, SIGTERM]);

			$server->stop(); 
		}
		
		
		
    }
	
	/**
	 * Register Service Providers with the container.
	 *
	 * @return object
	 */
	public function init()
	{
		if ($this->container->get('EARequestConsoleStatusResult') == "Console") {
			
			//Console
			if ($_ENV['APP_DEBUG'] == "true") {	
				
				$whoopsHandler = $this->container->get('\Whoops\Run');
				$whoopsHandler->pushHandler(new \Whoops\Handler\PlainTextHandler());
				$whoopsHandler->register();
				
			}
			
		}
		
		//Loop through and Register Service Providers First
		foreach ($this->getConfig()["mainconfig"]["providers"] as $serviceProvidersArrayRowKey => $serviceProvidersArrayRowValue) {
			$registeredServiceProviders[$serviceProvidersArrayRowKey] = new $serviceProvidersArrayRowValue($this->container);
			$registeredServiceProviders[$serviceProvidersArrayRowKey]->register();
			
			$this->serviceProviders[] = $serviceProvidersArrayRowValue; // NOT WORKING STILL
			
			//Save available Serviceproviders to Container
			$this->container->instance('EAServiceProviders', $this->serviceProviders);
			$this->eaServiceProvidersList = $this->container->get('EAServiceProviders'); 
		}
		
		foreach ($this->eaServiceProvidersList as $serviceProvidersArrayRowKey => $serviceProvidersArrayRowValue) {
			$registeredServiceProviders[$serviceProvidersArrayRowKey]->boot();
			
			$this->loadedProviders[] = $serviceProvidersArrayRowValue; // NOT WORKING STILL
			
			//Save available Serviceproviders to Container
			$this->container->instance('EALoadedServiceProviders', $this->loadedProviders);
			$this->eaLoadedServiceProvidersList = $this->container->get('EALoadedServiceProviders'); 
		}
	}
	
	/**
	 * Run Application
	 *
	 * @return object
	 */
	public function run()
	{
        if ($this->container->get('EARequestConsoleStatusResult') == "Console") {
			
			//Console
			
			/* $logger = $this->container->get('\Amp\Log');
			
			$server = $this->container->get('\Amp\Http\Server\SocketHttpServer');

			$errorHandler = $this->container->get('\Amp\Http\Server\DefaultErrorHandler');
			
			//Define router
			$router = new Router($server, $logger, $errorHandler);

			$this->container->instance('\Amp\Http\Server\Router', $router);
			$router = $this->container->get('\Amp\Http\Server\Router');

			$fallbackResponse = new ClosureRequestHandler(function () {
						return new Response(HttpStatus::NO_CONTENT);
					});
					
            $this->container->instance('\Amp\Http\Server\Response', $fallbackResponse);
			$fallback = $this->container->get('\Amp\Http\Server\Response');
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

			//$router->addRoute('GET', '/{name}', new Controller());

			//Define fallback
			$router->setFallback($fallback);

			//$server->expose('127.0.0.1:1337');
			$server->expose('0.0.0.0:1337');

			//$server->expose(new Socket\InternetAddress("0.0.0.0", 1337));
			//$server->expose(new Socket\InternetAddress("[::]", 1337));
			 

			//$server->start($requestHandler, $errorHandler);
			$server->start($router, $errorHandler);

			// Serve requests until SIGINT or SIGTERM is received by the process.
			\Amp\trapSignal([SIGINT, SIGTERM]);

			$server->stop(); */
			/* 
			$matchedRouteResponse = $this->container->get('matchedRouteResponse');
			
			$this->matchedRouteKey = $this->container->get('MatchedRouteKey'); 
			
			$this->matchedRouteDetails = $this->container->get('MatchedRouteDetails'); 
			
			$requiredRouteType = "";
			$requiredRouteType = $this->matchedRouteDetails["route_type"];
			
			$pageStatus = $this->matchedRouteDetails["status"];
			$pageNumberOfRecords = $this->matchedRouteDetails["number_of_records"];
			$pageNumberOfLoopsCount = $this->matchedRouteDetails["number_of_loops_count"];
			$pageSleepTimeMinimumSeconds = $this->matchedRouteDetails["sleep_time_minimum_seconds"];
			$pageSleepTimeMaximumSeconds = $this->matchedRouteDetails["sleep_time_maximum_seconds"];
			$pageSleepIntervalDefinition = $this->matchedRouteDetails["sleep_interval_definition"];
			$pageFilename = $this->matchedRouteDetails["page_filename"];
			$pageRouteType = $this->matchedRouteDetails["route_type"];
			$pageControllerType = $this->matchedRouteDetails["controller_type"];
			$pageControllerClassName = $this->matchedRouteDetails["controller_class_name"];
			$pageMethodName = $this->matchedRouteDetails["method_name"];
			
			
			if ((isset($this->matchedRouteKey)) && ($this->matchedRouteKey != "not-found")) {
				
				if ($pageStatus == "ON") {
					
					if (($pageRouteType == "cron-job") || ($pageRouteType == "message-queue-worker")) {
						
						if ((isset($pageControllerType)) && (($pageControllerType == "procedural") || ($pageControllerType == "oop-mapped"))) {
					
							if (class_exists($pageControllerClassName)) {
								
								$matchedController = new $pageControllerClassName($this->container);
							
								$this->container->instance('MatchedControllerName', $matchedController);
								$this->matchedController = $this->container->get('MatchedControllerName');
								
								if ($this->matchedController->checkIfActionExists($pageMethodName)) {
																		
									$this->response = $this->matchedController->$pageMethodName();
									
									if ((isset($this->response)) && ($this->response == 0)) {
										//Success
										return $this->response;
										
									} elseif ((isset($this->response)) && ($this->response == 1)) {
										//FAILURE
										//Log Failure scenario content
										return $this->response;
										
									} else {
										//INVALID (>=2)
										//Log Invalid scenario reasons
										return $this->response;
										
									}
									
									
								} else {
								
									//throw new \Exception($pageMethodName . " action does not exist!");
									echo html_escaped_output($pageMethodName) . " action does not exist!";
								}
								
							} else {
								
								//throw new \Exception($pageControllerClassName . " controller does not exist!");
								echo html_escaped_output($pageControllerClassName) . " controller does not exist!";
							}
							
						}
						
					} else {
						
						echo "Cron jobs & Message queue workers are supported at this moment.\n";
						
					}
					
					
				} else {
					
					//throw new \Exception("Status of cli route is not ON. This trigger will be left to subside!\n");
					echo "Status of cli route is not ON. This trigger will be left to subside!\n";
				}
				
					
				
				
			} else {
				
				//throw new \Exception("cli route does not exist!\n");
				echo "cli route does not exist!\n";
				
			} */
			
		} else {
		
			//Web
			/*$this->middlewarePipeQueueEntries = $this->container->get('middlewarePipeQueueEntries');
			
			//https://docs.laminas.dev/laminas-httphandlerrunner/emitters/
			//Define Max Buffer Length for Files
			$maxBufferLength = (int) "8192";

			$sapiStreamEmitter = new \Laminas\HttpHandlerRunner\Emitter\SapiStreamEmitter($maxBufferLength); 
			$conditionalEmitter = new class ($sapiStreamEmitter) implements \Laminas\HttpHandlerRunner\Emitter\EmitterInterface {

				private $emitter;

				public function __construct(\Laminas\HttpHandlerRunner\Emitter\EmitterInterface $emitter)
				{
						$this->emitter = $emitter;
				}

				public function emit(\Psr\Http\Message\ResponseInterface $response) : bool
				{
						if (! $response->hasHeader('Content-Disposition')
								&& ! $response->hasHeader('Content-Range')
						) {
								return false;
						}
						return $this->emitter->emit($response);
				}
				
			};


			$emitterStack = new \Laminas\HttpHandlerRunner\Emitter\EmitterStack();
			$this->container->instance('\Laminas\HttpHandlerRunner\Emitter\EmitterStack', $emitterStack);

			$this->emitterStackInstance = $this->container->get('\Laminas\HttpHandlerRunner\Emitter\EmitterStack');

			$this->emitterStackInstance->push(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter());
			$this->emitterStackInstance->push($conditionalEmitter);
			
			$serverRequest = $this->container->get('\Laminas\Diactoros\ServerRequestFactory');
			
			// RUN MIDDLEWARE using HTTPHandlerRunner Laminas Library
			//https://docs.laminas.dev/laminas-stratigility/v3/middleware/#middleware
			$requestHandlerRunnerServer = new \Laminas\HttpHandlerRunner\RequestHandlerRunner(
				$this->middlewarePipeQueueEntries,
				$this->emitterStackInstance,
				static function () use ($serverRequest) {
					return $serverRequest;
				},
				static function (\Throwable $e) {
						$response = (new \Laminas\Diactoros\ResponseFactory())->createResponse(500);
						$response->getBody()->write(sprintf(
								'An error occurred: %s',
								$e->getMessage
						));

						return $response;
				}
			);
			
			$this->container->instance('\Laminas\HttpHandlerRunner\RequestHandlerRunner', $requestHandlerRunnerServer);
			$this->requestHandlerRunnerServerInstance = $this->container->get('\Laminas\HttpHandlerRunner\RequestHandlerRunner');
			$this->requestHandlerRunnerServerInstance->run();
			*/
		}
	}
        
	/**
	* Get the config array of the application.
	*
	* @return string
	*/
	public function getConfig()
	{
		return $this->config;
	}
	
	/**
	* Get the Routing Engine User Rules/Routes array of the application.
	*
	* @return string
	*/
	public function getRoutes()
	{

	}
}