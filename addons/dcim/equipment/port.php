<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Port extends Equipment_Interface_Physical
	{
		/**
		  * @var int
		  */
		protected $_portId = null;

		/**
		  * @var Addon\Dcim\Api_Equipment_Port
		  */
		protected $_portApi = null;

		/**
		  * @var Addon\Dcim\Equipment_Interface
		  */
		protected $_interface = null;

		/**
		  * @var string
		  */
		protected $_portKey = null;

		/**
		  * @var string[]
		  */
		protected $_description = array();

		/**
		  * @var Addon\Dcim\Equipment_Port
		  */
		protected $_neighborPort = null;

		/**
		  * @var array
		  */
		protected $_neighborDatas = array();
		

		/**
		  * @param Addon\Dcim\Equipment $equipment
		  * @param int $portId
		  * @return $this
		  */
		public function __construct(Equipment $equipment, $portId)
		{
			parent::__construct($equipment);

			$this->_portId = (int) $portId;		// Test portId or cast to INT
			$this->_portApi = new Api_Equipment_Port($this->_portId);
		}

		public function getPortId()
		{
			return $this->_portId;
		}

		public function getPortKey()
		{
			if($this->_portKey === null) {
				$this->_portKey = $this->_nameToKey();
			}

			return $this->_portKey;
		}

		public function getPortIndex()
		{
			$portKey = $this->getPortKey();
			$portParts = explode(static::INTERFACE_SEPARATOR, $portKey, 2);
			return (count($portParts) === 2) ? ($portParts[1]) : (false);
		}

		public function getPortApi()
		{
			return $this->_portApi;
		}

		public function skipPort()
		{
			return $this->_skipInterface();
		}

		public function isConnected()
		{
			return $this->_portApi->isConnected();
		}

		public function hasInterface()
		{
			return ($this->_interface !== null);
		}

		public function setInterface(Equipment_Interface $interface)
		{
			if(($port = $interface->retrievePort()) !== false && $port === $this)
			{
				if(!$this->hasInterface()) {
					$this->_interface = $interface;
				}
				else {
					throw new Exception("L'interface '".$interface->interfaceName."' ne peut être déclarée pour le port '".$this->portApi->label."', une interface est déjà présente", E_USER_ERROR);
				}
			}
			else {
				throw new Exception("L'interface '".$interface->interfaceName."' ne peut être déclarée car son port '".$port->portApi->label."' ne correspond pas au port '".$this->portApi->label."'", E_USER_ERROR);
			}

			return $this;
		}

		public function getInterface()
		{
			if(!$this->hasInterface()) {
				throw new Exception("L'interface du port '".$this->portApi->label."' n'est pas déclarée", E_USER_ERROR);
			}

			return $this->_interface;
		}

		public function getPortName($portKey = null)
		{
			if(!array_key_exists('portName', $this->_datas))
			{
				$portName = $this->_portApi->getPortLabel();

				if($portName === false) {
					$portId = $this->_portApi->getPortId();
					$hostName = $this->_portApi->getTopEquipmentLabel();
					throw new Exception("Impossible de résoudre le label du port ID '".$portId."' pour l'équipement '".$hostName."'", E_USER_ERROR);
				}

				$this->_datas['portName'] = $portName;
			}

			$datas = $this->_getPortDatasByKey($portKey);

			if(!array_key_exists('portName', $datas)) {
				$datas['portName'] = $this->_datas['portName'];
			}

			return $datas['portName'];
		}

		/**
		  * @return int
		  */
		protected function _getInterfaceId()
		{
			return $this->getPortId();
		}

		/**
		  * @return Addon\Dcim\Api_Equipment_Abstract
		  */
		protected function _getInterfaceApi()
		{
			return $this->getPortApi();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceKey()
		{
			return $this->getPortKey();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceIndex()
		{
			return $this->getPortIndex();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceName()
		{
			return $this->getPortName();
		}

		public function getStatus()
		{
			if(!array_key_exists('status', $this->_datas)) {
				$this->_datas['status'] = $this->isConnected();
			}

			return $this->_datas['status'];
		}

		public function getHostName($portKey = null)
		{
			parent::getHostName();

			$datas = $this->_getPortDatasByKey($portKey);

			if(!array_key_exists('hostName', $datas)) {
				$datas['hostName'] = $this->_datas['hostName'];
			}

			return $datas['hostName'];
		}

		public function getDescription($portKey = null)
		{
			if($portKey === null) {
				$portKey = $this->getPortKey();
			}

			if(!array_key_exists($portKey, $this->_description)) {
				$this->_description[$portKey] = $this->getHostName($portKey)." ".$this->getPortName($portKey);
			}

			return $this->_description[$portKey];
		}

		/**
		  * @return array Return array indexed with port keys
		  */
		public function getDatas()
		{
			if(!$this->skipPort())
			{
				$datas = array();

				$this->getStatus();
				$this->getHostname();
				$this->getPortName();
				$this->getAttributes();
				$this->getDescription();

				$datas[$this->getPortKey()] = $this->_datas;

				return $datas;
			}
			else {
				throw new Exception("Ce port '".$this->portName."' ne doit pas être traité", E_USER_ERROR);
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getEquipmentId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getPortId()) {
				//return $this->_equipment->equipmentId;
				return $this->_portApi->getTopEquipmentId();
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($connectorId);
				return $Api_Equipment_Port->getTopEquipmentId();
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getTopModuleId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getPortId()) {
				return $this->_portApi->getModuleEquipmentId();
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($connectorId);
				return $Api_Equipment_Port->getModuleEquipmentId();
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getModuleId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getPortId()) {
				$moduleId = $this->_portApi->getParentEquipmentId();
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($connectorId);
				$moduleId = $Api_Equipment_Port->getParentEquipmentId();
			}

			return ($moduleId !== $this->equipment->id) ? ($moduleId) : (false);
		}

		public function getNeighborEquipmentId($portId = null)
		{
			if($portId === null  || (int) $portId === $this->getPortId())
			{
				$portId = $this->getPortId();

				if($this->hasNeighborPort()) {
					return $this->_neighborPort->getEquipmentId();
				}
			}

			$portId = $this->getNeighborPortId($portId);
			return $this->getEquipmentId($portId);
		}

		public function getNeighborPortId($portId = null)
		{
			if($portId === null || (int) $portId === $this->getPortId())
			{
				if($this->hasNeighborPort()) {
					return $this->_neighborPort->getPortId();
				}
				else {
					$Api_Equipment_Port = $this->_portApi;
				}
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($portId);
			}

			return $Api_Equipment_Port->getEndConnectedPortId();
		}

		public function hasNeighborPort()
		{
			return ($this->_neighborPort !== null);
		}

		public function getNeighborPort()
		{
			return ($this->hasNeighborPort()) ? ($this->_neighborPort) : (false);
		}

		public function setNeighborPort(Equipment_Port $portEquipment)
		{
			if(!$this->hasNeighborPort()) {
				$this->_neighborPort = $portEquipment;
				$this->_neighborDatas = $this->_getNeighborDatas();
			}

			return $this;
		}

		protected function _getNeighborDatas()
		{
			$neighborPort = $this->getNeighborPort();

			if($neighborPort !== false)
			{
				if($this->getPortId() === $neighborPort->getNeighborPortId())
				{
					$datas = array();

					$leftKey = $this->getPortKey();
					$rightKey = $neighborPort->getPortKey();
					$rightDatas = $neighborPort->getDatas();
					$rightDatas = $rightDatas[$rightKey];

					$rightDescription = $neighborPort->getDescription($rightKey);
					$datas[$leftKey]['description'] = $rightDescription;

					foreach($rightDatas as $index => $value)
					{
						switch($index)
						{
							case 'hostName':
							case 'portName': 
							case 'intId':
							case 'intIndex':
							case 'intIndex2':
							case 'intType': {
								// /!\ Important la clé doit être la clé actuelle côté gauche
								$datas[$leftKey]['conTo'.ucfirst($index)] = $value;
								break;
							}
						}
					}

					return $datas;
				}
				else {
					$eMessage = "('".$this->portName."', '".$neighborPort->portName."')";
					throw new Exception("L'ID du port voisin ne correspond pas à l'ID du port voisin déclaré ".$eMessage, E_USER_ERROR);
				}
			}
			else {
				throw new Exception("Le port voisin pour le port '".$this->portName."' n'est pas déclaré", E_USER_ERROR);
			}
		}

		/**
		  * @return array
		  */
		public function getNeighborDatas()
		{
			return ($this->hasNeighborPort()) ? ($this->_neighborDatas) : (array());
		}

		/**
		  * @param array $datas
		  * @return $this
		  */
		public function setNeighborDatas(array $datas)
		{
			throw new Exception("Il est interdit de changer les données du port voisin", E_USER_ERROR);
			//return $this;
		}

		protected function &_getPortDatasByIndex($index = null)
		{
			if($index === null || $index === -1) {
				return $this->_datas;
			}
			else {
				throw new Exception("L'index '".$index."' du port '".$this->portApi->label."' est invalide", E_USER_ERROR);
			}
		}

		protected function &_getPortDatasByKey($key = null)
		{
			if($key === null || $key === $this->getPortKey()) {
				return $this->_datas;
			}
			else {
				throw new Exception("La clé '".$key."' du port '".$this->portApi->label."' est invalide", E_USER_ERROR);
			}
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'portId': {
					return $this->getPortId();
				}
				case 'portApi': {
					return $this->getPortApi();
				}
				case 'portKey': {
					return $this->getPortKey();
				}
				case 'portIndex': {
					return $this->getPortIndex();
				}
				case 'portName': {
					return $this->getPortName();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}