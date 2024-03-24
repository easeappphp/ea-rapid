<?php
declare(strict_types=1);

namespace EaseAppPHP\EARapid\Foundation;

use \Illuminate\Container\Container;

if (interface_exists('\EaseAppPHP\EARapid\Foundation\Interfaces\BaseWebControllerInterface')) {
	
    class BaseWebController implements \EaseAppPHP\EARapid\Foundation\Interfaces\BaseWebControllerInterface
    {
		protected $container;
		protected $config;
		protected $matchedRouteDetails;
		protected $serverRequest;
		protected $queryParams;
		protected $response;
		protected const RESPONSEHEADER = 'X-Response-Time';

        public function __construct(Container $container)
		{
			$this->container = $container;
			$this->config = $this->container->get('config');
			$this->matchedRouteDetails = $this->container->get('MatchedRouteDetails');
			//$this->serverRequest = $this->container->get('\Laminas\Diactoros\ServerRequestFactory');
			$this->queryParams = $this->serverRequest->getQueryParams();
			$this->response = $this->container->get('\EaseAppPHP\EARapid\Foundation\BaseWebResponse');
		}
		
		/**
		 * Check if an action exists on the controller.
		 *
		 * @param  string  $method
		 * @return boolean
		 */
		public function checkIfActionExists($method)
		{
			$handler = array($this, $method);
			
			if (is_callable($handler)) { 
        
				return true;
				
            }
			
			return false;
			
		}

        /**
         * Handle calls to missing methods on the controller.
         *
         * @param  string  $method
         * @param  array  $parametersArray
         * @return mixed
         *
         * @throws \BadMethodCallException
         */
        public function __call($method, $parametersArray)
        {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
			
			/* $parametersImploded = implode(', ', $parametersArray);
            print "Call to method $method() with parameters '$parametersImploded' failed!\n";
			 */
        }
		
		/**
		 * Handle calls to missing static methods on the controller.
		 *
		 * @param  string  $method
		 * @param  array  $parametersArray
		 * @return mixed
		 *
		 * @throws \BadMethodCallException
		 */
		public static function __callStatic($method, $parametersArray)
		{
			throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
			
			/* // Note: value of $method is case sensitive.
			echo "Call to static method $method() with parameters "
				 . implode(', ', $parametersArray). "failed!\n"; */
		}
		
		/**
		 * Create View File Name With Path
		 * Note: View directory names should not contain the . character.
		 * @return string
		 */
		public function createViewFileNameWithPath($pageFileName)
		{
			$fileNameParts = preg_replace('/\./', '/', $pageFileName, (substr_count($pageFileName, '.') - 1));
			
			return $fileNameParts;			
		}
    }
	
}

