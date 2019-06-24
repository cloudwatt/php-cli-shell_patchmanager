<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Interface extends Equipment_Interface_Virtual
	{
		const INT_PHYSICAL = 0;
		const INT_VIRTUAL = 1;

		/**
		  * Physical or virtual interface
		  * @var int
		  */
		protected $_mode = null;

		/**
		  * @var string
		  */
		protected $_interfaceKey = null;

		/**
		  * @var int
		  */
		protected $_interfaceIndex = null;

		/**
		  * @var array
		  */
		protected $_interfaceLabels = array();

		/**
		  * @var string
		  */
		protected $_description = null;

		/**
		  * @var array
		  */
		protected $_neighborDatas = null;


		/**
		  * $index passé lors de l'instanciation permet de garantir que celui-ci ne peut être changé plus tard
		  *
		  * @param Addon\Dcim\Equipment $equipment
		  * @param string|Addon\Dcim\Equipment_Port $port
		  * @param null|int $index
		  * @param null|array $labels
		  * @return $this
		  */
		public function __construct(Equipment $equipment, $port, $index = null, array $labels = null)
		{
			parent::__construct($equipment);

			if($port instanceof Equipment_Port)
			{
				$this->_port = $port;
				$this->_mode = static::INT_PHYSICAL;

				$portKey = $port->getPortKey();
				$this->_setInterfaceKey($portKey);		// /!\ Si un index ou part existent, ils seront traités par _setInterfaceKey

				$this->setHostName($port->getHostName());
				$this->setInterfaceName($port->getPortName());
			}
			elseif(C\Tools::is('string&&!empty', $port))
			{
				$this->_mode = static::INT_VIRTUAL;

				$this->_setInterfaceKey($port);			// /!\ Si un index ou part existent, ils seront traités par _setInterfaceKey

				if($index !== null && $this->getInterfaceIndex() === false) {
					$this->_setInterfaceIndex($index);
				}

				if($labels !== null && count($this->getInterfaceLabels()) === 0) {
					$this->_setInterfaceLabels($labels);
				}

				$this->setInterfaceName($port);
			}
			else {
				throw new Exception("Impossible d'instancier une interface, le port n'est pas du bon type '".gettype($port)."'", E_USER_ERROR);
			}

			$this->_setup();
		}

		protected function _setup()
		{
		}

		/**
		  * @return string
		  */
		public function getInterfaceId()
		{
			return $this->_formatInterfaceId(true, true, true);
		}

		/**
		  * @param bool $key
		  * @param bool $index
		  * @param bool $labels
		  * @return string
		  */
		protected function _formatInterfaceId($key = true, $index = true, $labels = true)
		{
			$intId = '';

			if($key)
			{
				$intId .= $this->getInterfaceKey();

				if($index)
				{
					$intIndex = $this->getInterfaceIndex();

					if($intIndex !== false)
					{
						if($this->hasPort()) {
							$pSeparator = $this->_port::INTERFACE_SEPARATOR;
						}
						else {
							$pSeparator = $this->_equipment::PORT_INTERFACE_SEPARATOR;
						}

						$intId .= $pSeparator.$intIndex;
					}

					if($labels)
					{
						$intLabels = $this->getInterfaceLabels();

						if(count($intLabels) > 0) {
							$vSeparator = static::INTERFACE_SEPARATOR;
							$intLabel = implode($vSeparator, $intLabels);
							$intId .= $vSeparator.$intLabel;
						}
					}
				}
			}

			return $intId;
		}

		/**
		  * @param string $intKey
		  * @return $this
		  */
		protected function _setInterfaceKey($intKey)
		{
			$hasPort = $this->hasPort();

			if($hasPort) {
				$pSeparator = $this->_port::INTERFACE_SEPARATOR;
			}
			else {
				$pSeparator = $this->_equipment::PORT_INTERFACE_SEPARATOR;
			}

			/**
			  * /!\ Si la clé possède un index alors il est indispensable que le séparateur utilisé soit de type physique
			  * En général, la clé de l'interface provient du port donc séparateur physique, et les labels sont virtuelles
			  *
			  * xe-0/0/69:1.508.123 : key:index.part.part ...
			  * Un index seul peut exister
			  * Un label seul peut exister
			  * Compatible multi-labels
			  */
			$pSeparator = preg_quote($pSeparator, '#');
			$vSeparator = preg_quote(static::INTERFACE_SEPARATOR, '#');

			/**
			  * La 3ème parenthèse capturante doit capturer l'ensemble des labels c'est à dire les chiffres et le séparateur (508.123...)
			  */
			$keyRegex = '([^'.$pSeparator.$vSeparator.']+)';
			$indexRegex = '(?:'.$pSeparator.'([0-9]+))?';
			$labelRegex = '(?:'.$vSeparator.'([0-9'.$vSeparator.']+))?';

			if(preg_match('#'.$keyRegex.$indexRegex.$labelRegex.'#i', $intKey, $intParts))
			{
				// interface key
				$this->_interfaceKey = mb_strtolower($intParts[1]);

				// interface index
				if(isset($intParts[2])) {
					$this->_setInterfaceIndex($intParts[2]);
				}

				// interface labels
				if(isset($intParts[3]))
				{
					if(!$hasPort) {
						$labels = explode(static::INTERFACE_SEPARATOR, $intParts[3]);
						$this->_setInterfaceLabels($labels);
					}
					else
					{
						/**
						  * Une interface physique donc lié à un port ne peut pas contenir de labels
						  * Les labels sont des tags réservés aux interfaces virtuelles
						  *
						  * Example les vlans pour les interface réseau
						  */
						throw new Exception("Impossible d'instancier l'interface physique '".$intKey."', le nom possède des labels '".$intParts[3]."'", E_USER_ERROR);
					}
				}
			}
			else {
				throw new Exception("Impossible d'instancier l'interface '".$intKey."', le nom n'est pas valide", E_USER_ERROR);
			}

			return $this;
		}

		/**
		  * @return string
		  */
		public function getInterfaceKey()
		{
			return $this->_interfaceKey;
		}

		protected function _setInterfaceIndex($intIndex)
		{
			if($this->_indexIsValid($intIndex)) {
				$this->_interfaceIndex = $intIndex;
			}

			return $this;
		}

		/**
		  * @return false|int
		  */
		public function getInterfaceIndex()
		{
			return ($this->_interfaceIndex !== null) ? ($this->_interfaceIndex) : (false);
		}

		/**
		  * @param array $intLabels
		  * @return $this
		  */
		protected function _setInterfaceLabels(array $intLabels)
		{
			$this->_interfaceLabels = $intLabels;
			return $this;
		}

		/**
		  * @return array
		  */
		public function getInterfaceLabels()
		{
			return $this->_interfaceLabels;
		}

		/**
		  * @return bool
		  */
		public function isPhysical()
		{
			return ($this->_mode === static::INT_PHYSICAL);
		}

		/**
		  * @return bool
		  */
		public function isVirtual()
		{
			return ($this->_mode === static::INT_VIRTUAL);
		}

		public function setInterfaceName($intName)
		{
			if(C\Tools::is('string&&!empty', $intName)) {
				$this->_datas['intName'] = $intName;
			}
			return $this;
		}

		public function getInterfaceName()
		{
			/**
			  * /!\ Depuis le constructor on renseigne le nom de l'interface
			  * /!\ Le nom du port ne doit pas pouvoir être changé depuis le port
			  */
			return $this->_datas['intName'];
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceId()
		{
			return $this->getInterfaceId();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceKey()
		{
			return $this->getInterfaceKey();
		}

		/**
		  * @return false|int
		  */
		protected function _getInterfaceIndex()
		{
			return $this->getInterfaceIndex();
		}

		/**
		  * @return array
		  */
		protected function _getInterfaceLabels()
		{
			return $this->getInterfaceLabels();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceName()
		{
			return $this->getInterfaceName();
		}

		/**
		  * @return false|Addon\Dcim\Equipment_Port
		  */
		public function retrievePort()
		{
			if($this->hasPort() && $this->isPhysical()) {
				return $this->_port;
			}
			elseif($this->hasPort() !== $this->isPhysical()) {
				throw new Exception("L'interface '".$this->getInterfaceName()."' n'est pas valide", E_USER_ERROR);
			}
			else {
				return false;
			}
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

		/**
		  * Permet d'indiquer la description de l'interface voisine
		  *
		  * @param string $description
		  * @return $this
		  */
		public function setDescription($description)
		{
			if(C\Tools::is('string&&!empty', $description)) {
				$this->_datas['description'] = $description;
			}

			return $this;
		}

		/**
		  * /!\ Ne surtout pas retourner la description de datas
		  * La description retournée doit être celle de cette interface
		  * Dans datas, la description est potentiellement celle du voisin
		  *
		  * @return string Description
		  */
		public function getDescription()
		{
			if($this->_description === null) {
				$this->_description = $this->getHostName()." ".$this->getInterfaceName();
			}

			return $this->_description;
		}

		/**
		  * @return array Return array indexed with interface IDs
		  */
		public function getDatas()
		{
			$datas = array();

			$this->getHostName();
			$this->getInterfaceName();
			$this->getAttributes();
			$this->getDescription();

			$datas[$this->getInterfaceId()] = $this->_datas;

			return $datas;
		}

		/**
		  * @return bool
		  */
		public function hasNeighborInterface()
		{
			return ($this->getNeighborInterface() !== false);
		}

		/**
		  * @return false|Addon\Dcim\Equipment_Interface
		  */
		public function getNeighborInterface()
		{
			$port = $this->retrievePort();

			if($port !== false)
			{
				$neighborPort = $port->getNeighborPort();

				if($neighborPort !== false)
				{
					$neighborInterface = $neighborPort->getInterface();

					if($neighborInterface !== false) {
						return $neighborInterface;
					}
				}
			}

			return false;
		}

		/**
		  * @param bool $forceToRefresh
		  * @return array
		  */
		public function getNeighborDatas($forceToRefresh = false)
		{
			if(($neighborInterface = $this->getNeighborInterface()) !== false)
			{
				if($this->_neighborDatas === null || $forceToRefresh) {
					$this->_neighborDatas = $this->_getNeighborDatas($neighborInterface);
				}

				return $this->_neighborDatas;
			}
			else {
				return array();
			}
		}

		/**
		  * @param Addon\Dcim\Equipment_Interface $neighborInterface
		  * @return array
		  */
		protected function _getNeighborDatas(Equipment_Interface $neighborInterface)
		{
			$datas = array();

			$leftId = $this->getInterfaceId();
			$rightId = $neighborInterface->getInterfaceId();
			$rightDatas = $neighborInterface->getDatas();

			$neighborDescription = $neighborInterface->getDescription();
			$datas[$leftId]['description'] = $neighborDescription;

			foreach($rightDatas[$rightId] as $key => $value)
			{
				switch($key)
				{
					case 'hostName':
					case 'intName':
					case 'intId':
					case 'intIndex':
					case 'intIndex2':
					case 'intType': {
						// /!\ Important la clé doit être la clé actuelle côté gauche
						$datas[$leftId]['conTo'.ucfirst($key)] = $value;
						break;
					}
				}
			}
			
			return $datas;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'interfaceId': {
					return $this->getInterfaceId();
				}
				case 'interfaceKey': {
					return $this->getInterfaceKey();
				}
				case 'interfaceIndex': {
					return $this->getInterfaceIndex();
				}
				case 'interfaceLabels': {
					return $this->getInterfaceLabels();
				}
				case 'interfaceName': {
					return $this->getInterfaceName();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}