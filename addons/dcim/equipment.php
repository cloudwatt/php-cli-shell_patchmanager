<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment
	{
		const PORT_INTERFACE_SEPARATOR = Equipment_Port::INTERFACE_SEPARATOR;
		const INT_INTERFACE_SEPARATOR = Equipment_Interface::INTERFACE_SEPARATOR;

		/**
		  * @var int
		  */
		protected $_equipmentId = null;

		/**
		  * @var Addon\Dcim\Api_Equipment
		  */
		protected $_equipmentApi = null;

		/**
		  * @var Addon\Dcim\Equipment_Module[]
		  */
		protected $_modules = array();

		/**
		  * @var Addon\Dcim\Equipment_Interface[]
		  */
		protected $_interfaces = array();

		/**
		  * Slot datas
		  * @var array
		  */
		protected $_dataSlots = null;

		/**
		  * Port datas
		  * @var array
		  */
		protected $_dataPorts = null;

		/**
		  * Interface datas
		  * @var array
		  */
		protected $_dataInterfaces = null;

		/**
		  * Datas
		  * @var array
		  */
		protected $_datas = array();

		/**
		  * @var int
		  */
		protected $_debug = 3;		// 0 = disable, 1 = info, 2 = info+, 3 = info++


		public function __construct($equipmentId, Api_Equipment $apiEquipment = null)
		{
			$this->_equipmentId = (int) $equipmentId;	// Test equipmentId or cast to INT

			if($apiEquipment !== null && $apiEquipment->id === $this->_equipmentId) {
				$this->_equipmentApi = $apiEquipment;
			}
			else {
				$this->_equipmentApi = new Api_Equipment($this->_equipmentId);
			}

			$this->_init();
		}

		protected function _init()
		{
		}

		public function getEquipmentId()
		{
			return $this->_equipmentId;
		}

		public function getEquipmentApi()
		{
			return $this->_equipmentApi;
		}

		/**
		  * @param mixed $attribute
		  * @return int Module ID
		  */
		protected function _retrieveModuleId($attribute)
		{
			if($attribute instanceof Equipment_Slot) {
				$moduleId = $attribute->getModuleId();
			}
			elseif($attribute instanceof Equipment_Port) {
				$moduleId = $attribute->getModuleId();
			}
			elseif($attribute instanceof Equipment_Interface)
			{
				if($attribute->isPhysical()) {
					$Equipment_Port = $attribute->retrievePort();
					$moduleId = $Equipment_Port->getModuleId();
				}
				else {
					throw new Exception("It is not possible to retrieve module from virtual interface '".$attribute->interfaceName."'", E_USER_ERROR);
				}
			}
			elseif($attribute instanceof Equipment_Module) {
				$moduleId = $attribute->getModuleId();
			}
			elseif(C\Tools::is('int&&>0', $attribute)) {
				$moduleId = (int) $attribute;
			}
			elseif($attribute === null) {
				$moduleId = false;
			}
			else {
				throw new Exception("Can not retrieve module, attribute '".gettype($attribute)."' is not compatible", E_USER_ERROR);
			}

			return ($moduleId !== false) ? ($moduleId) : ($this->equipmentId);
		}

		/**
		  * @param mixed $attribute
		  * @return Addon\Dcim\Equipment_Module
		  */
		protected function _retrieveModule($attribute)
		{
			$moduleId = $this->_retrieveModuleId($attribute);

			if(!array_key_exists($moduleId, $this->_modules)) {
				$this->_modules[$moduleId] = new Equipment_Module($this, $moduleId);
			}

			return $this->_modules[$moduleId];
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Slot $name Slot object or key
		  * @return bool
		  */
		public function slotExists($name)
		{
			return ($this->retrieveSlot($name) !== false);
		}

		/**
		  * @param Addon\Dcim\Equipment_Slot $slot
		  * @return $this
		  */
		public function declareSlot(Equipment_Slot $slot)
		{
			$Equipment_Module = $this->_retrieveModule($slot);
			$Equipment_Module->declareSlot($slot);
			$this->_slotCleaner();
			return $this;
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Slot $name Slot object or key
		  * @return false|Addon\Dcim\Equipment_Slot
		  */
		public function retrieveSlot($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Slot) {
					$Equipment_Module = $this->_retrieveModule($name);
					return $Equipment_Module->retrieveSlot($name);
				}
				else {
					throw new Exception("Slot name must be a string or an Equipment_Slot object, '".gettype($name)."' given", E_USER_ERROR);
				}
			}
			else
			{
				foreach($this->_modules as $Equipment_Module)
				{
					$result = $Equipment_Module->retrieveSlot($name);

					if($result !== false) {
						return $result;
					}
				}
			}

			return false;
		}

		/**
		  * @return Addon\Dcim\Equipment_Slot[] Slot objects
		  */
		public function getSlots()
		{
			$slots = array();

			foreach($this->_modules as $Equipment_Module) {
				$results = $Equipment_Module->getSlots();
				$results = array_values($results);
				$slots = array_merge($slots, $results);
			}

			return $slots;
		}

		/**
		  * @param Addon\Dcim\Equipment_Slot $slot
		  * @return $this
		  */
		public function undeclareSlot(Equipment_Slot $slot)
		{
			$Equipment_Module = $this->_retrieveModule($slot);
			$Equipment_Module->undeclareSlot($slot);
			$this->_slotCleaner();
			return $this;
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Port $name Port object or key
		  * @return bool
		  */
		public function portExists($name)
		{
			return ($this->retrievePort($name) !== false);
		}

		/**
		  * @param Addon\Dcim\Equipment_Port $port
		  * @return $this
		  */
		public function declarePort(Equipment_Port $port)
		{
			/**
			  * Un port est une interface physique!
			  *
			  * Un port est obligatoirement lié à une interface virtuelle car
			  * il permet d'interconnecter directement deux équipements
			  *
			  * Par conséquent, un port est l'interface entre deux équipements
			  */
			$this->_newInterface($port);

			$Equipment_Module = $this->_retrieveModule($port);
			$Equipment_Module->declarePort($port);
			$this->_portCleaner();
			return $this;
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Port $name Port object or key
		  * @return false|Addon\Dcim\Equipment_Port
		  */
		public function retrievePort($name)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Port) {
					$Equipment_Module = $this->_retrieveModule($name);
					return $Equipment_Module->retrievePort($name);
				}
				else {
					throw new Exception("Port name must be a string or an Equipment_Port object, '".gettype($name)."' given", E_USER_ERROR);
				}
			}
			else
			{
				foreach($this->_modules as $Equipment_Module)
				{
					$result = $Equipment_Module->retrievePort($name);

					if($result !== false) {
						return $result;
					}
				}
			}

			return false;
		}

		/**
		  * @return Addon\Dcim\Equipment_Port[] Port objects
		  */
		public function getPorts()
		{
			$ports = array();

			foreach($this->_modules as $Equipment_Module) {
				$results = $Equipment_Module->getPorts();
				$results = array_values($results);
				$ports = array_merge($ports, $results);
			}

			return $ports;
		}

		/**
		  * @param Addon\Dcim\Equipment_Port $port
		  * @return $this
		  */
		public function undeclarePort(Equipment_Port $port)
		{
			$Equipment_Module = $this->_retrieveModule($port);
			$Equipment_Module->undeclarePort($port);
			$this->_portCleaner();
			return $this;
		}

		/**
		  * @param Addon\Dcim\Equipment_Port $equipmentPort
		  * @return void
		  */
		protected function _newInterface(Equipment_Port $equipmentPort)
		{
			$Equipment_Interface = new Equipment_Interface($equipmentPort);
			$equipmentPort->setInterface($Equipment_Interface);
			$this->declareInterface($Equipment_Interface);
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Interface $name Interface object or ID
		  * @param null|int $index Interface index
		  * @return bool
		  */
		public function interfaceExists($name, $index = null)
		{
			return ($this->retrieveInterface($name, $index) !== false);
		}

		/**
		  * @param Addon\Dcim\Equipment_Interface $interface
		  * @return $this
		  */
		public function declareInterface(Equipment_Interface $interface)
		{		
			if($interface->isPhysical())
			{
				$Equipment_Module = $this->_retrieveModule($interface);
				$Equipment_Module->declareInterface($interface);

				/**
				  * On peut rajouter des interfaces virtuelles sans avoir à nettoyer
				  * car certaines interfaces virtuelles peuvent dépendre des interfaces physiques
				  */
				$this->_interfaceCleaner();
			}
			else
			{
				$interfaceId = $interface->getInterfaceId();

				if(array_key_exists($interfaceId, $this->_interfaces)) {
					throw new Exception("L'interface '".$interface->getInterfaceName()."' est déjà déclarée sous l'ID '".$interfaceId."'", E_USER_ERROR);
				}

				$this->_interfaces[$interfaceId] = $interface;
			}

			return $this;
		}

		/**
		  * @param string|Addon\Dcim\Equipment_Interface $name Interface object or ID
		  * @param null|int $index Interface index
		  * @return false|Addon\Dcim\Equipment_Interface
		  */
		public function retrieveInterface($name, $index = null)
		{
			if(is_object($name))
			{
				if($name instanceof Equipment_Interface)
				{
					if($name->isPhysical()) {
						$Equipment_Module = $this->_retrieveModule($name);
						return $Equipment_Module->retrieveInterface($name);
					}
					else {
						$name = $name->getInterfaceId();
					}
				}
				else {
					throw new Exception("Interface name must be a string or an Equipment_Interface object, '".gettype($name)."' given", E_USER_ERROR);
				}
			}
			else
			{
				foreach($this->_modules as $Equipment_Module)
				{
					$result = $Equipment_Module->retrieveInterface($name, $index);

					if($result !== false) {
						return $result;
					}
				}

				if($index !== null) {
					$name .= static::INT_INTERFACE_SEPARATOR.$index;
				}
			}

			return (array_key_exists($name, $this->_interfaces)) ? ($this->_interfaces[$name]) : (false);
		}

		/**
		  * @return Addon\Dcim\Equipment_Interface[]
		  */
		public function getInterfaces()
		{
			$interfaces = array();

			foreach($this->_modules as $Equipment_Module) {
				$results = $Equipment_Module->getInterfaces();
				$results = array_values($results);
				$interfaces = array_merge($interfaces, $results);
			}

			// Ajouter à la fin les interfaces virtuelles
			$thisInterfaces = array_values($this->_interfaces);
			$interfaces = array_merge($interfaces, $thisInterfaces);

			return $interfaces;
		}

		/**
		  * @param Addon\Dcim\Equipment_Interface $interface
		  * @return $this
		  */
		public function undeclareInterface(Equipment_Interface $interface)
		{
			if($interface->isPhysical())
			{
				$Equipment_Module = $this->_retrieveModule($interface);
				$Equipment_Module->undeclareInterface($interface);

				/**
				  * On peut retirer des interfaces virtuelles sans avoir à nettoyer
				  * car certaines interfaces virtuelles peuvent dépendre
				  * des interfaces physiques mais pas l'inverse
				  */
				$this->_interfaceCleaner();
			}
			else
			{
				$interface = $this->retrieveInterface($interface);

				if($interface !== false) {
					unset($this->_interfaces[$interface->getInterfaceId()]);
				}
			}

			return $this;
		}
		
		public function getHostName()
		{
			if(!array_key_exists('hostName', $this->_datas)) {
				$this->_datas['hostName'] = self::_formatEquipmentLabel($this->_equipmentApi);
			}

			return $this->_datas['hostName'];
		}

		protected function _getSlots()
		{
			if($this->_dataSlots === null)
			{
				$this->_dataSlots = array();		// /!\ Important

				/**
				  * /!\ Si deux slots possèdent le même nom
				  * alors les données de tous ces slots seront mergées
				  *
				  * @todo a corriger, trouver une solution
				  * Si doublon alors créer section modules puis
				  * ajouter les datas par module (name) dans cette section
				  */
				foreach($this->slots as $slot) {
					C\Tools::merge($this->_dataSlots, $slot->getDatas());
				}
			}

			return $this->_dataSlots;
		}

		protected function _getPorts()
		{
			if($this->_dataPorts === null)
			{
				$this->_dataPorts = array();		// /!\ Important

				/**
				  * /!\ Si deux ports possèdent le même nom
				  * alors les données de tous ces ports seront mergées
				  *
				  * @todo a corriger, trouver une solution
				  * Si doublon alors créer section modules puis
				  * ajouter les datas par module (name) dans cette section
				  */
				foreach($this->ports as $port) {
					$datas = $port->getDatas();
					$nbDatas = $port->getNeighborDatas();
					$allDatas = array_merge_recursive($datas, $nbDatas);
					C\Tools::merge($this->_dataPorts, $allDatas);
				}
			}

			return $this->_dataPorts;
		}

		/**
		  * Retourne les interfaces suivantes:
		  * Port, LA, L3
		  */
		protected function _getInterfaces()
		{
			if($this->_dataInterfaces === null)
			{
				$this->_dataInterfaces = array();		// /!\ Important

				/**
				  * /!\ Si deux interfaces possèdent le même nom
				  * alors les données de toutes ces interfaces seront mergées
				  *
				  * @todo a corriger, trouver une solution
				  * Si doublon alors créer section modules puis
				  * ajouter les datas par module (name) dans cette section
				  */
				foreach($this->interfaces as $interface) {
					$datas = $interface->getDatas();
					$nbDatas = $interface->getNeighborDatas();
					$allDatas = array_merge_recursive($datas, $nbDatas);
					C\Tools::merge($this->_dataInterfaces, $allDatas);
				}
			}

			return $this->_dataInterfaces;
		}

		public function getConfiguration()
		{
			if(!array_key_exists('interfaces', $this->_datas))
			{
				$this->_datas['interfaces'] = array();		// /!\ Important

				$slotDatas = $this->_getSlots();
				$portDatas = $this->_getPorts();

				$slotKeys = array_keys($slotDatas);
				$portKeys = array_keys($portDatas);
				$dualSlotPort = array_intersect($slotKeys, $portKeys);

				/**
				  * /!\ Exemple port em0 d'un Juniper QFX5100
				  */
				foreach($dualSlotPort as $key)
				{
					$slotIsEmpty = $this->retrieveSlot($key)->isEmpty();
					$portIsConnected = $this->retrievePort($key)->isConnected();

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
						throw new Exception("Un dual slot/port ne peut avoir qu'un connecteur actif à la fois", E_USER_ERROR);
					}
				}

				$this->_datas['interfaces'] = $slotDatas;
				$interfaceDatas = $this->_getInterfaces();

				/**
				  * /!\ Les datas de interface sont prioritaires sur celles de port
				  * Example, les uplinks dont les vlans sont renseignés dans interface
				  *
				  * /!\ Un port possède obligatoirement son interface
				  * On se base donc sur les données des interfaces
				  *
				  * /!\ On garde les clés des interfaces donc attention au séparateur
				  */
				foreach($interfaceDatas as $interfaceId => &$datas)
				{
					$Equipment_Interface = $this->retrieveInterface($interfaceId);
					$portKey = $Equipment_Interface->getPortKey();

					if(array_key_exists($portKey, $portDatas)) {
						$datas = array_merge($portDatas[$portKey], $datas);
					}
				}
				unset($datas);

				/**
				  * Les slots n'ont pas d'interfaces donc une interface
				  * ne peut pas écraser les données provenant d'un slot
				  */
				C\Tools::merge($this->_datas['interfaces'], $interfaceDatas);
			}

			/**
			  * /!\ Du moins prioritaire au plus
			  *
			  * 1. Slot datas			+
			  * 2. Port datas			++
			  * 3. Interface datas		+++
			  */
			return $this->_datas['interfaces'];
		}
		
		public function getDatas()
		{
			$this->getHostName();
			$this->getConfiguration();

			return $this->_datas;
		}

		public function printDebugDatas()
		{
			$slotDatas = $this->_getSlots();
			$portDatas = $this->_getPorts();
			$interfaceDatas = $this->_getInterfaces();

			$header = "-------------------- ".$this->equipmentApi->label." --------------------";
			C\Tools::e(PHP_EOL.$header, 'orange');
			C\Tools::e(PHP_EOL."\tSlot datas:", 'orange');
			C\Tools::e(PHP_EOL.print_r($slotDatas, true).PHP_EOL, 'orange');
			C\Tools::e(PHP_EOL."\tPort datas:", 'orange');
			C\Tools::e(PHP_EOL.print_r($portDatas, true).PHP_EOL, 'orange');
			C\Tools::e(PHP_EOL."\tInterface datas:", 'orange');
			C\Tools::e(PHP_EOL.print_r($interfaceDatas, true).PHP_EOL, 'orange');
			C\Tools::e(PHP_EOL.str_repeat('-', mb_strlen($header)), 'orange');
		}

		public function reset()
		{
			$this->_modules = array();
			$this->_interfaces = array();
			$this->resetDatas();
			return $this;
		}

		public function resetDatas()
		{
			$this->_datas = array();
			$this->_dataSlots = null;
			$this->_dataPorts = null;
			$this->_dataInterfaces = null;
			return $this;
		}

		protected function _slotCleaner()
		{
			$this->_dataSlots = null;
			$this->_portCleaner();
		}

		protected function _portCleaner()
		{
			$this->_dataPorts = null;
			$this->_interfaceCleaner();
		}

		protected function _interfaceCleaner()
		{
			$this->_dataInterfaces = null;
			$this->_datas = array();
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'id':
				case 'equipmentId':
					return $this->getEquipmentId();
				case 'api':
				case 'equipmentApi':
					return $this->getEquipmentApi();
				case 'name':
				case 'equipmentName':
					return $this->getHostName();
				case 'slots':
					return $this->getSlots();
				case 'ports':
					return $this->getPorts();
				case 'interfaces':
					return $this->getInterfaces();
				default:
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
			}
		}

		protected static function _formatEquipmentLabel(Api_Equipment $Api_Equipment)
		{
			$equipmentLabel = $Api_Equipment->getEquipmentLabel();

			if($equipmentLabel === false) {
				$equipmentId = $Api_Equipment->getEquipmentId();
				throw new Exception("Impossible de résoudre le label pour l'équipement ID '".$equipmentId."'", E_USER_ERROR);
			}

			return current(explode('.', $equipmentLabel, 2));
		}

		public function debug($debug)
		{
			if($debug === true) {
				$this->_debug = 3;
			}
			elseif($debug === false) {
				$this->_debug = 0;
			}
			elseif(C\Tools::is('int&&>0', $debug)) {
				$this->_debug = $debug;
			}

			return $this->_debug;
		}
	}