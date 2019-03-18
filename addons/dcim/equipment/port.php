<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Port extends Equipment_Interface_Physical implements \ArrayAccess, \IteratorAggregate, \Countable
	{
		/**
		  * @var Addon\Dcim\Api_Equipment_Port
		  */
		protected $_equipmentPortApi;

		/**
		  * @var Addon\Dcim\Equipment_Interface
		  */
		protected $_equipmentInterface;

		protected $_portId = null;
		protected $_portKey = null;
		protected $_neighborPort = null;

		protected $_description = array();
		protected $_nbDatas = array();
		

		public function __construct($portId)
		{
			$this->_portId = (int) $portId;
			$this->_equipmentPortApi = new Api_Equipment_Port($this->_portId);		// /!\ Ne pas passer null
		}

		public function skipPort()
		{
			return $this->_skipInt();
		}

		public function isConnected()
		{
			return $this->_equipmentPortApi->isConnected();
		}

		public function setInterface(Equipment_Interface $Equipment_Interface)
		{
			if($this->_equipmentInterface === null) {
				$this->_equipmentInterface = $Equipment_Interface;
			}

			return $this;
		}

		public function getInterface()
		{
			if($this->_equipmentInterface === null) {
				throw new Exception("L'interface de ce port n'est pas déclarée", E_USER_ERROR);
			}

			return $this->_equipmentInterface;
		}
		
		public function getPortId()
		{
			return $this->_portId;
		}

		protected function _getKey()
		{
			return $this->getPortKey();
		}

		public function getPortKey()
		{
			if($this->_portKey === null) {
				$this->_portKey = $this->_nameToKey();
			}

			return $this->_portKey;
		}

		protected function _nameToKey($portName = null)
		{
			if($portName === null) {
				$portName = $this->getPortName();
			}

			return mb_strtolower($portName);
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
			if(!array_key_exists('hostName', $this->_datas))
			{
				$hostName = $this->_equipmentPortApi->getTopEquipmentLabel();

				if($hostName === false) {
					$equipmentId = $this->_equipmentPortApi->getTopEquipmentId();
					throw new Exception("Impossible de résoudre le label pour l'équipement ID \"".$equipmentId."\"", E_USER_ERROR);
				}

				$this->_datas['hostName'] = current(explode('.', $hostName, 2));
			}

			$datas = $this->_getPortDatasByKey($portKey);

			if(!array_key_exists('hostName', $datas)) {
				$datas['hostName'] = $this->_datas['hostName'];
			}

			return $datas['hostName'];
		}

		public function getPortName($portKey = null)
		{
			if(!array_key_exists('portName', $this->_datas))
			{
				$portName = $this->_equipmentPortApi->getPortLabel();

				if($portName === false) {
					$portId = $this->_equipmentPortApi->getPortId();
					throw new Exception("Impossible de résoudre le label du port ID \"".$portId."\"", E_USER_ERROR);
				}

				$this->_datas['portName'] = $portName;
			}

			$datas = $this->_getPortDatasByKey($portKey);

			if(!array_key_exists('portName', $datas)) {
				$datas['portName'] = $this->_datas['portName'];
			}

			return $datas['portName'];
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

		// /!\ Doit retourner un tableau
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
				throw new Exception("Ce port ne doit pas être traité", E_USER_ERROR);
			}
		}

		public function getKeys()
		{
			return array_keys($this->getDatas());
		}

		public function getEquipmentId($portId = null)
		{
			if($portId === null || (int) $portId === $this->getPortId()) {
				return $this->_equipmentPortApi->getTopEquipmentId();
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($portId);
				return $Api_Equipment_Port->getTopEquipmentId();
			}
		}

		public function getModuleId($portId = null)
		{
			if($portId === null || (int) $portId === $this->getPortId()) {
				return $this->_equipmentPortApi->getModuleEquipmentId();
			}
			else {
				$Api_Equipment_Port = new Api_Equipment_Port($portId);
				return $Api_Equipment_Port->getModuleEquipmentId();
			}
		}

		public function getNeighborEquipId($portId = null)
		{
			if($portId === null) {
				$portId = $this->getNeighborPortId();
			}

			return $this->getEquipmentId($portId);
		}

		public function getNeighborPortId($portId = null)
		{
			if($portId === null || (int) $portId === $this->getPortId()) {
				$Api_Equipment_Port = $this->_equipmentPortApi;
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
				$this->_nbDatas = $this->_getNeighborInfos();
			}

			return $this;
		}

		protected function _getNeighborInfos()
		{
			$port = $this->getNeighborPort();

			if($port !== false)
			{
				if($this->getPortId() === $port->getNeighborPortId())
				{
					$datas = array();

					$leftKeys = $this->getKeys();
					$rightKeys = $port->getKeys();
					$rightDatas = $port->getDatas();

					foreach($rightKeys as $index => $rightKey)
					{
						$leftKey = $leftKeys[$index];

						$rightDesc = $port->getDescription($rightKey);
						$datas[$leftKey]['description'] = $rightDesc;

						foreach(array_keys($rightDatas[$rightKey]) as $key)
						{
							switch($key)
							{
								case 'hostName':
								case 'portName': 
								case 'intId':
								case 'intIndex':
								case 'intIndex2':
								case 'intType': {
									$value = $rightDatas[$rightKey][$key];
									// /!\ Important la clé doit être la clé actuelle côté gauche
									$datas[$leftKey]['conTo'.ucfirst($key)] = $value;
									break;
								}
							}
						}
					}

					return $datas;
				}
				else {
					throw new Exception("L'ID du port voisin ne correspond pas à l'ID du port déclaré", E_USER_ERROR);
				}
			}
			else {
				$portName = $this->getPortName();
				throw new Exception("Le port voisin du port '".$portName."' n'est pas déclaré", E_USER_ERROR);
			}
		}

		// /!\ Doit retourner un tableau
		public function getNeighborDatas()
		{
			return ($this->hasNeighborPort()) ? ($this->_nbDatas) : (array());
		}

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
				throw new Exception("L'index du port est invalide", E_USER_ERROR);
			}
		}

		protected function &_getPortDatasByKey($key = null)
		{
			if($key === null || $key === $this->getPortKey()) {
				return $this->_datas;
			}
			else {
				throw new Exception("La clé du port est invalide", E_USER_ERROR);
			}
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
			$key = $this->_nameToKey($name);

			if(array_key_exists($key, $datas)) {
				return $datas[$key];
			}

			return false;
		}

		public function __isset($name)
		{
			$keys = $this->getKeys();
			$key = $this->_nameToKey($name);
			return in_array($key, $keys, true);
		}
	}