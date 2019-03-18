<?php
	namespace Addon\Dcim;

	use Core as C;

	class Connector extends Connector_Abstract
	{
		protected static $_defaultConnector = __NAMESPACE__ .'\Connector_Soap';
		
		protected $_currentConnector;

		/**
		  * DCIM server configuration
		  * @var Core\Config
		  */
		protected $_config = null;

		protected $_aDCIM = array();
		protected $_oDCIM = null;


		public function __construct(array $servers, $printInfoMessages = true, $connector = null, $debug = false)
		{
			$config = C\Config::getInstance()->DCIM;
			$this->_setCurrentConnector($connector);

			foreach($servers as $server)
			{
				$server = mb_strtoupper($server);

				if(!$config->servers->key_exists($server)) {
					throw new Exception("Unable to retrieve DCIM server '".$server."' configuration", E_USER_ERROR);
				}
				else
				{
					$this->_config = $config->servers[$server];

					if($this->_config->key_exists('serverLocation')) {
						list($loginCredential, $passwordCredential) = $this->_getCredentials($this->_config, $server);
						$this->_aDCIM[$server] = new $this->_currentConnector($server, $this->_config->serverLocation, $loginCredential, $passwordCredential, $printInfoMessages, $debug);
					}
					else {
						throw new Exception("Unable to retrieve 'serverLocation' configuration for DCIM server '".$server."'", E_USER_ERROR);
					}
				}
			}

			if(count($this->_aDCIM) === 1) {
				$this->_oDCIM = current($this->_aDCIM);
			}
		}

		protected function _setCurrentConnector($connector)
		{
			if($connector !== null && static::_isValidConnector($connector)) {
				$this->_currentConnector = $connector;
			}
			else {
				$this->_currentConnector = static::$_defaultConnector;
			}
		}

		protected function _getCredentials(C\MyArrayObject $serverConfig, $server)
		{
			if($serverConfig->key_exists('loginCredential') && C\Tools::is('string&&!empty', $serverConfig->loginCredential)) {
				$loginCredential = $serverConfig->loginCredential;
			}
			elseif($serverConfig->key_exists('loginEnvVarName') && C\Tools::is('string&&!empty', $serverConfig->loginEnvVarName))
			{
				$loginEnvVarName = $serverConfig->loginEnvVarName;
				$loginCredential = getenv($loginEnvVarName);

				if($loginCredential === false) {
					throw new Exception("Unable to retrieve login credential for DCIM server '".$server."' from environment with variable name '".$loginEnvVarName."'", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Unable to retrieve 'loginCredential' or 'loginEnvVarName' configuration for DCIM server '".$server."'", E_USER_ERROR);
			}

			if($serverConfig->key_exists('passwordCredential') && C\Tools::is('string&&!empty', $serverConfig->passwordCredential)) {
				$passwordCredential = $serverConfig->passwordCredential;
			}
			elseif($serverConfig->key_exists('passwordEnvVarName') && C\Tools::is('string&&!empty', $serverConfig->passwordEnvVarName))
			{
				$passwordEnvVarName = $serverConfig->passwordEnvVarName;
				$passwordCredential = getenv($passwordEnvVarName);

				if($passwordCredential === false) {
					throw new Exception("Unable to retrieve password credential for DCIM server '".$server."' from environment with variable name '".$passwordEnvVarName."'", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Unable to retrieve 'passwordCredential' or 'passwordEnvVarName' configuration for DCIM server '".$server."'", E_USER_ERROR);
			}

			return array($loginCredential, $passwordCredential);
		}

		public function getJnlpUrl($server = false, $version = 64)
		{
			if($server === false && $this->_oDCIM !== null) {
				return $this->_oDCIM->getJnlpUrl($version);
			}
			elseif(array_key_exists($server, $this->_aDCIM)) {
				return $this->_aDCIM[$server]->getJnlpUrl($version);
			}

			return false;
		}

		public function getDcim($equipLabel = null)
		{
			if($this->_oDCIM !== null) {
				return $this->_oDCIM;
			}
			elseif($equipLabel !== null)
			{
				if(preg_match('#^(.*-[ps]-.*)$#i', $equipLabel)) {
					return $this->_aDCIM['SEC'];
				}
				elseif(preg_match('#^(.*-[il]-.*)$#i', $equipLabel)) {
					return $this->_aDCIM['CORP'];
				}
				elseif(preg_match('#^(.*-[d]-.*)$#i', $equipLabel)) {
					return $this->_aDCIM['DEV'];
				}
			}

			throw new Exception('Impossible de retourner le service DCIM adapté', E_USER_ERROR);
		}

		public function getAllDcim()
		{
			return $this->_aDCIM;
		}

		public function getConfig()
		{
			return $this->_config;
		}

		public function __get($name)
		{
			if($this->_oDCIM !== null) {
				return $this->_oDCIM;
			}
			else
			{
				switch(mb_strtolower($name))
				{
					case 'sec':
						return $this->_aDCIM['SEC'];
					case 'corp':
						return $this->_aDCIM['CORP'];
					case 'dev':
						return $this->_aDCIM['DEV'];
				}
			}

			throw new Exception("Le DCIM ".$name." n'existe pas", E_USER_ERROR);
		}

		public function __call($name, array $arguments)
		{
			if($this->_oDCIM !== null) {
				return call_user_func_array(array($this->_oDCIM, $name), $arguments);
			}
			else
			{
				$results = array();

				foreach($_aDCIM as $dcim) {
					$result[] = call_user_func_array(array($dcim, $name), $arguments);
				}

				return $results;
			}
		}

		public static function __callStatic($name, array $arguments)
		{
			$callable = array(static::$_defaultConnector, $name);
			$Closure = \Closure::fromCallable($callable);
			return forward_static_call_array($Closure, $arguments);
		}

		public static function setDefaultConnector($connector)
		{
			if(strpos($connector, '\\') === false) {
				$connector = __NAMESPACE__ .'\\'.$connector;
			}

			if(static::_isValidConnector($connector)) {
				static::$_defaultConnector = $connector;
			}
		}

		protected static function _isValidConnector($connector)
		{
			$ReflectionClass = new \ReflectionClass($connector);
			return $ReflectionClass->isSubclassOf(__NAMESPACE__ .'\Connector_Abstract');
		}
	}
