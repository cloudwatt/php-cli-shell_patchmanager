<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment implements \ArrayAccess, \IteratorAggregate, \Countable
	{
		protected static $_environments;

		/**
		  * @var Addon\Dcim\Api_Equipment
		  */
		protected $_equipmentApi = null;

		/**
		  * @var int
		  */
		protected $_equipmentId = null;

		/**
		  * @var Addon\Dcim\Equipment_Slot[]
		  */
		protected $_slots = array();

		/**
		  * @var Addon\Dcim\Equipment_Port[]
		  */
		protected $_ports = array();

		/**
		  * @var Addon\Dcim\Equipment_Interface[]
		  */
		protected $_ints = array();

		/**
		  * Slot datas
		  * @var array
		  */
		protected $_slotsDatas = null;

		/**
		  * Port datas
		  * @var array
		  */
		protected $_portsDatas = null;

		/**
		  * Interface datas
		  * @var array
		  */
		protected $_intsDatas = null;

		/**
		  * Datas
		  * @var array
		  */
		protected $_datas = array();


		public function __construct($equipmentId)
		{
			$this->_equipmentId = (int) $equipmentId;
			$this->_equipmentApi = new Api_Equipment($this->_equipmentId);		// /!\ Ne pas passer null
		}

		public static function getInstance($equipmentId)
		{
			$Api_Equipment = new Api_Equipment($equipmentId);
			$hostName = self::_getHostName($Api_Equipment);
			return (static::isEquipment($hostName)) ? (new static($equipmentId)) : (false);
		}

		public function declareSlot(Equipment_Slot $Equipment_Slot)
		{
			$slotKey = $Equipment_Slot->getSlotKey();

			if(array_key_exists($slotKey, $this->_slots)) {
				throw new Exception("Ce slot est déjà déclaré @ ".$slotKey, E_USER_ERROR);
			}

			$this->_slots[$slotKey] = $Equipment_Slot;
			return $this;
		}

		abstract public function declarePort(Equipment_Port $Equipment_Port);

		public function undeclarePort(Equipment_Port $Equipment_Port)
		{
			$portKey = $Equipment_Port->getPortKey();
			unset($this->{$portKey});

			$this->_datas = array();
			$this->_portsDatas = null;
			$this->_intsDatas = null;

			return $this;
		}

		public function declareInterface(Equipment_Interface $Equipment_Interface)
		{
			$intKey = $Equipment_Interface->getIntKey();
			$intIndex = $Equipment_Interface->getIntIndex();

			if($intIndex !== false) { $intKey .= '__'.$intIndex; }

			if(array_key_exists($intKey, $this->_ints)) {
				throw new Exception("Cette interface est déjà déclarée @ ".$intKey, E_USER_ERROR);
			}

			$this->_ints[$intKey] = $Equipment_Interface;
			return $this;
		}

		public function getSlot($slotKey)
		{
			return (array_key_exists($slotKey, $this->_slots)) ? ($this->_slots[$slotKey]) : (false);
		}

		public function getPort($portKey)
		{
			return (array_key_exists($portKey, $this->_ports)) ? ($this->_ports[$portKey]) : (false);
		}

		// /!\ On peut demander une interface physique comme virtuelle
		public function getInterface($intKey, $intIndex = false)
		{
			// /!\ On doit traiter les 2 cas
			$intKey = str_replace(Equipment_Interface_Physical::INT_SEPARATOR, '__', $intKey, $countPort);
			$intKey = str_replace(Equipment_Interface_Virtual::INT_SEPARATOR, '__', $intKey, $countInt);

			if($countPort === 0 && $countInt === 0 && $intIndex !== false) {
				$intKey .= '__'.$intIndex;
			}

			return (array_key_exists($intKey, $this->_ints)) ? ($this->_ints[$intKey]) : (false);
		}

		/*public function getPortKeys()
		{
			return array_keys($this->ports);
		}

		public function getIntKeys()
		{
			return array_keys($this->ints);
		}*/

		public function getEquipmentId()
		{
			return $this->_equipmentId;
		}
		
		public function getHostName()
		{
			if(!array_key_exists('hostName', $this->_datas)) {
				$this->_datas['hostName'] = self::_getHostName($this->_equipmentApi);
			}

			return $this->_datas['hostName'];
		}

		protected function _getSlots()
		{
			if($this->_slotsDatas === null)
			{
				$this->_slotsDatas = array();		// /!\ Important

				foreach($this->slots as $slot) {
					C\Tools::merge($this->_slotsDatas, $slot->getDatas());
				}
			}

			return $this->_slotsDatas;
		}
		
		protected function _getPorts()
		{
			if($this->_portsDatas === null)
			{
				$this->_portsDatas = array();		// /!\ Important

				foreach($this->ports as $port) {
					$datas = $port->getDatas();
					$nbDatas = $port->getNeighborDatas();
					$allDatas = array_merge_recursive($datas, $nbDatas);
					C\Tools::merge($this->_portsDatas, $allDatas);
				}
			}

			return $this->_portsDatas;
		}

		/**
		  * Retourne les interfaces suivantes:
		  * Port, LA, L3
		  **/
		protected function _getInts()
		{
			if($this->_intsDatas === null)
			{
				$this->_intsDatas = array();		// /!\ Important

				foreach($this->ints as $int) {
					$datas = $int->getDatas();
					$nbDatas = $int->getNeighborDatas();
					$allDatas = array_merge_recursive($datas, $nbDatas);
					C\Tools::merge($this->_intsDatas, $allDatas);
				}
			}

			return $this->_intsDatas;
		}

		public function getInterfaces()
		{
			if(!array_key_exists('interfaces', $this->_datas))
			{
				$this->_datas['interfaces'] = array();		// /!\ Important

				$slotDatas = $this->_getSlots();
				$portDatas = $this->_getPorts();
				$intDatas = $this->_getInts();

/*echo "\r\nDEBUG 0\r\n\t";var_dump($slotDatas);
echo "\r\n\t";var_dump($portDatas);
echo "\r\n\t";var_dump($intDatas);echo "\r\nEND 0\r\n";*/

				$slotKeys = array_keys($slotDatas);
				$portKeys = array_keys($portDatas);
				$dualSlotPort = array_intersect($slotKeys, $portKeys);

				/**
				  * /!\ Exemple port em0 d'un Juniper QFX5100
				  */
				foreach($dualSlotPort as $key)
				{
					$slotIsEmpty = $this->getSlot($key)->isEmpty();
					$portIsConnected = $this->getPort($key)->isConnected();

					if($slotIsEmpty && !$portIsConnected) {
						unset($portDatas[$key]);
					}
					elseif(!$slotIsEmpty && !$portIsConnected) {
						unset($portDatas[$key]);
					}
					elseif($slotIsEmpty && $portIsConnected) {
						unset($slotDatas[$key]);
					}
					else {
						throw new Exception("Un dual port slot ne peut avoir qu'un connecteur actif à la fois", E_USER_ERROR);
					}
				}

				$this->_datas['interfaces'] = $slotDatas;

				/**
				  * /!\ Les datas de interface sont prioritaires sur celles de port
				  * Example, les uplinks dont les vlans sont renseignés dans interface
				  *
				  * /!\ Un port possède obligatoirement son interface
				  * On se base donc sur les données des interfaces
				  *
				  * /!\ On garde les clés des interfaces donc attention au séparateur
				  */
				foreach($intDatas as $key => &$datas)
				{
					$key = str_replace(Equipment_Interface_Virtual::INT_SEPARATOR, Equipment_Interface_Physical::INT_SEPARATOR, $key);

					if(array_key_exists($key, $portDatas)) {
						$datas = array_merge($portDatas[$key], $datas);
					}
				}
				unset($datas);

				C\Tools::merge($this->_datas['interfaces'], $intDatas);
			}

			return $this->_datas['interfaces'];
		}
		
		public function getDatas()
		{
			$this->getHostname();
			$this->getInterfaces();

			return $this->_datas;
		}

		public function offsetSet($offset, $value)
		{
			if($value instanceof Equipment_Port) {
				$this->declarePort($value);
			}
		}

		public function offsetExists($offset)
		{
			return $this->issetPort($offset);
		}

		public function offsetUnset($offset)
		{
			$this->unsetPort($offset);
		}

		public function offsetGet($offset)
		{
			return ($this->issetPort($offset)) ? ($this->_ports[$offset]) : (null);
		}

		public function getIterator()
		{
			return new \ArrayIterator($this->_ports);
		}

		public function count()
		{
			return count($this->_ports);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'slots':
					return $this->_slots;
				case 'ports':
					return $this->_ports;
				case 'ints':
					return $this->_ints;
				default:
					return $this->getPort($name);
			}
		}

		public function __set($name, $value)
		{
			if((is_object($value)) && $value instanceof Equipment_Port) {
				$this->declarePort($value);
			}
		}

		public function __isset($name)
		{
			return $this->issetPort($name);
		}

		public function __unset($name)
		{
			$this->unsetPort($name);
		}

		public function issetSlot($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Slot) {
					$name = $name->getSlotKey();
				}
				else {
					throw new Exception("Slot name must be a string or an Equipment_Slot object", E_USER_ERROR);
				}
			}

			return (array_key_exists($name, $this->_slots));
		}

		public function issetPort($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Port) {
					$name = $name->getPortKey();
				}
				else {
					throw new Exception("Port name must be a string or an Equipment_Port object", E_USER_ERROR);
				}
			}

			return (array_key_exists($name, $this->_ports));
		}

		public function issetInt($name)
		{
			return $this->issetInterface($name);
		}

		public function issetInterface($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Interface) {
					$name = $name->getIntKey();
				}
				else {
					throw new Exception("Interface name must be a string or an Equipment_Interface object", E_USER_ERROR);
				}
			}

			return (array_key_exists($name, $this->_ints));
		}

		// @todo a utiliser ou a supprimer
		/*public function isset($object)
		{
			$ReflectionClass = new \ReflectionClass($object);
			$ReflectionClass = $ReflectionClass->getParentClass();

			switch($ReflectionClass->getShortName())
			{
				case 'Equipment_Slot':
					return $this->issetSlot($object);
				case 'Equipment_Port':
					return $this->issetPort($object);
				case 'Equipment_Interface':
					return $this->issetInterface($object);
				default:
					throw new Exception('Cet object "'.get_class($object).'" n\'est pas reconnu.', E_USER_ERROR);
			}
		}*/

		public function unsetSlot($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Slot) {
					$name = $name->getSlotKey();
				}
				else {
					throw new Exception("Slot name must be a string or an Equipment_Slot object", E_USER_ERROR);
				}
			}

			unset($this->_slots[$name]);
			return $this;
		}

		public function unsetPort($name)
		{		
			if(is_object($name))
			{
				if($name instanceof Equipment_Port) {
					$name = $name->getPortKey();
				}
				else {
					throw new Exception("Port name must be a string or an Equipment_Port object", E_USER_ERROR);
				}
			}

			unset($this->_ports[$name]);
			return $this;
		}

		public function unsetInt($name)
		{
			return $this->unsetInterface($name);
		}

		public function unsetInterface($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Interface) {
					$name = $name->getIntKey();
				}
				else {
					throw new Exception("Interface name must be a string or an Equipment_Interface object", E_USER_ERROR);
				}
			}

			unset($this->_ints[$name]);
			return $this;
		}

		protected static function _getHostName(Api_Equipment $Api_Equipment)
		{
			$hostName = $Api_Equipment->getEquipmentLabel();

			if($hostName === false) {
				$equipmentId = $Api_Equipment->getEquipmentId();
				throw new Exception("Impossible de résoudre le label pour l'équipement ID \"".$equipmentId."\"", E_USER_ERROR);
			}

			return current(explode('.', $hostName, 2));
		}

		abstract public static function isEquipment($label);

		public static function setEnvs(array $environments)
		{
			self::$_environments = $environments;
		}
	}