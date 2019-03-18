<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Interface extends Equipment_Interface_Virtual implements \ArrayAccess, \IteratorAggregate, \Countable
	{
		const INT_PHYSICAL = 0;
		const INT_VIRTUAL = 1;

		/**
		  * @var Addon\Dcim\Equipment_Port
		  */
		protected $_equipmentPort;

		/**
		  * Physical or virtual interface
		  * @var int
		  */
		protected $_mode;

		/**
		  * @var string
		  */
		protected $_intKey;

		/**
		  * @var int
		  */
		protected $_intIndex;

		/**
		  * @var string
		  */
		protected $_description = null;


		// index passé lors de l'instanciation permet de garantir que celui-ci ne peut être changé plus tard
		public function __construct($port, $index = null)
		{
			if($port instanceof Equipment_Port)
			{
				$this->_mode = self::INT_PHYSICAL;
				$this->_equipmentPort = $port;

				$portKey = $port->getPortKey();
				$this->_setIntKey($portKey);		// /!\ Si un index existe, il sera traité par _setIntKey

				$this->setHostName($port->getHostName());
				$this->setIntName($port->getPortName());
			}
			elseif(C\Tools::is('string&&!empty', $port))
			{
				$this->_mode = self::INT_VIRTUAL;

				$this->_setIntKey($port);			// /!\ Si un index existe, il sera traité par _setIntKey
				$this->setIntName($port);
			}
			else {
				throw new Exception("Impossible d'instancier une interface", E_USER_ERROR);
			}

			$this->_setup();
		}

		protected function _setup()
		{
		}

		protected function _isPhysical()
		{
			return ($this->_mode === self::INT_PHYSICAL);
		}

		protected function _setIntKey($intKey)
		{
			if($this->hasPort()) {
				$Equipment_Port = get_class($this->getPort());
				$separator = $Equipment_Port::INT_SEPARATOR;
			}
			else {
				$separator = Equipment_Interface_Physical::INT_SEPARATOR;
			}

			// /!\ Si la clé possède un index; Séparateur physique si elle vient du port
			$intKey = str_replace($separator, self::INT_SEPARATOR, $intKey);
			$intKeyParts = explode(self::INT_SEPARATOR, $intKey, 2);

			$this->_intKey = mb_strtolower($intKeyParts[0]);

			if(count($intKeyParts) > 1) {
				$this->_setIntIndex($intKeyParts[1]);
			}

			return $this;
		}

		public function getIntKey()
		{
			return $this->_intKey;
		}

		protected function _setIntIndex($intIndex)
		{
			if($this->_indexIsValid($intIndex)) {
				$this->_intIndex = $intIndex;
			}
			return $this;
		}

		public function getIntIndex()
		{
			return ($this->_intIndex !== null) ? ($this->_intIndex) : (false);
		}

		public function getIntId()
		{
			$intKey = $this->getIntKey();
			$intIndex = $this->getIntIndex();

			return ($intIndex !== false) ? ($intKey.self::INT_SEPARATOR.$intIndex) : ($intKey);
		}

		protected function _getKey()
		{
			return $this->getIntId();
		}

		public function hasPort()
		{
			return isset($this->_equipmentPort);
		}

		public function getPort()
		{
			// @todo a corriger
			if($this->hasPort() !== $this->_isPhysical()) {
				throw new Exception("", E_USER_ERROR);
			}
			elseif($this->hasPort() && $this->_isPhysical()) {
				return $this->_equipmentPort;
			}
			else {
				return false;
			}
		}

		public function getPortId()
		{
			$intKey = $this->getIntKey();
			$intIndex = $this->getIntIndex();

			return ($intIndex !== false) ? ($intKey.Equipment_Port::INT_SEPARATOR.$intIndex) : ($intKey);
		}

		public function setHostName($hostName)
		{
			if(C\Tools::is('string&&!empty', $hostName)) {
				$this->_datas['hostName'] = $hostName;
			}
			return $this;
		}

		public function getHostName()
		{
			return (array_key_exists('hostName', $this->_datas)) ? ($this->_datas['hostName']) : (false);
		}

		public function setIntName($intName)
		{
			if(C\Tools::is('string&&!empty', $intName)) {
				$this->_datas['intName'] = $intName;
				$this->_datas['portName'] = $intName;	// /!\ compatibilité
			}
			return $this;
		}

		public function getIntName()
		{
			/**
			  * /!\ Depuis le constructor on renseigne le nom de l'interface
			  * /!\ Le nom du port ne doit pas pouvoir être changé depuis le port
			  */
			return $this->_datas['intName'];
		}

		/**
		  * Permet d'indiquer la description de l'interface voisine
		  */
		public function setDescription($desc)
		{
			if(C\Tools::is('string&&!empty', $desc)) {
				$this->_datas['description'] = $desc;
			}
			return $this;
		}

		/**
		  * /!\ Ne surtout pas retourner la description de datas
		  * La description retournée doit être celle de cette interface
		  * Dans datas, la description est potentiellement celle du voisin
		  */
		public function getDescription()
		{
			if($this->_description === null) {
				$this->_description = $this->getHostName()." ".$this->getIntName();
			}

			return $this->_description;
		}

		// /!\ Doit retourner un tableau
		public function getDatas()
		{
			$datas = array();

			$this->getHostName();
			$this->getIntName();
			$this->getAttributes();
			$this->getDescription();

			$datas[$this->getIntId()] = $this->_datas;

			return $datas;
		}

		// /!\ Doit retourner un tableau
		// @todo faire comme port sauvegarder dans array
		public function getNeighborDatas()
		{
			if(($port = $this->getPort()) !== false)
			{
				$nbPort = $port->getNeighborPort();

				if($nbPort !== false)
				{
					$nbInt = $nbPort->getInterface();

					if($nbInt !== false)
					{
						$datas = array();

						$leftIntKey = $this->getIntId();
						$rightIntKey = $nbInt->getIntId();
						$nbDatas = $nbInt->getDatas();

//echo "\r\nDEBUG 0\r\n\t";var_dump($nbDatas);echo "\r\nEND 0\r\n";

						$nbDesc = $nbInt->getDescription();
						$datas[$leftIntKey]['description'] = $nbDesc;

						foreach(array_keys(current($nbDatas)) as $key)
						{
							switch($key)
							{
								case 'hostName':
								case 'intName':
								case 'intId':
								case 'intIndex':
								case 'intIndex2':
								case 'intType': {
									$value = $nbDatas[$rightIntKey][$key];
									// /!\ Important la clé doit être la clé actuelle côté gauche
									$datas[$leftIntKey]['conTo'.ucfirst($key)] = $value;
									break;
								}
							}
						}

//echo "\r\nDEBUG 1\r\n\t";var_dump($datas);echo "\r\nEND 1\r\n";
						
						return $datas;
					}
				}
			}

			return array();
		}

		protected function _indexIsValid($index)
		{
			return C\Tools::is('int&&>=0', $index);
		}

		public function offsetSet($offset, $value)
		{
		}

		public function offsetExists($offset)
		{
			return isset($this->{$offset});
		}

		public function offsetUnset($offset)
		{
		}

		public function offsetGet($offset)
		{
			$data = $this->{$offset};
			return ($data !== false) ? ($data) : (null);
		}

		public function getIterator()
		{
			$datas = $this->getDatas();
			return new \ArrayIterator($datas);
		}

		public function count()
		{
			$datas = $this->getDatas();
			return count($datas);
		}

		public function __get($name)
		{
			$datas = $this->getDatas();

			if(array_key_exists($name, $datas)) {
				return $datas[$key];
			}

			return false;
		}

		public function __isset($name)
		{
			$datas = $this->getDatas();
			return array_key_exists($name, $datas);
		}
	}