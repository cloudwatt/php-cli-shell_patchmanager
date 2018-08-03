<?php
	include_once(__DIR__ . '/main.php');
	include_once(__DIR__ . '/connector/abstract.php');
	include_once(__DIR__ . '/connector/soap.php');

	abstract class DCIM_Abstract extends DCIM_Main
	{
		protected $_aDCIM = array();
		protected $_oDCIM = null;


		public function __construct(array $servers, $printInfoMessages = true)
		{
			$config = CONFIG::getInstance()->DCIM;

			foreach($servers as $server)
			{
				$server = mb_strtoupper($server);

				if(!$config->servers->key_exists($server)) {
					throw new Exception("Unable to retreive DCIM server @ ".$server, E_USER_ERROR);
				}
				else
				{
					$login = getenv('DCIM_'.$server.'_LOGIN');
					$password = getenv('DCIM_'.$server.'_PASSWORD');

					if($login === false || $password === false) {
						throw new Exception("Unable to retreive DCIM credentials for [".$server."] from env", E_USER_ERROR);
					}

					$this->_aDCIM[$server] = new DCIM_Connector_Soap($config->servers[$server], $login, $password, $printInfoMessages);
				}
			}

			if(count($this->_aDCIM) === 1) {
				$this->_oDCIM = current($this->_aDCIM);
			}
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
			return forward_static_call_array(array('DCIM_Connector_Soap', $name), $arguments);
		}
	}
