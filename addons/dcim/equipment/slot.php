<?php
	namespace Addon\Dcim;

	abstract class Equipment_Slot extends Equipment_Interface_Physical
	{	
		/**
		  * @var int
		  */
		protected $_slotId = null;

		/**
		  * @var Addon\Dcim\Api_Equipment_Slot
		  */
		protected $_slotApi = null;

		/**
		  * @var string
		  */
		protected $_slotKey = null;

		/**
		  * @var string
		  */
		protected $_description = null;

		
		public function __construct(Equipment $equipment, $slotId)
		{
			parent::__construct($equipment);

			$this->_slotId = (int) $slotId;		// Test slotId or cast to INT
			$this->_slotApi = new Api_Equipment_Slot($this->_slotId);
		}

		public function getSlotId()
		{
			return $this->_slotId;
		}

		public function getSlotKey()
		{
			if($this->_slotKey === null) {
				$this->_slotKey = $this->_nameToKey();
			}

			return $this->_slotKey;
		}

		public function getSlotIndex()
		{
			$slotKey = $this->getSlotKey();
			$slotParts = explode(static::INTERFACE_SEPARATOR, $slotKey, 2);
			return (count($slotParts) === 2) ? ($slotParts[1]) : (false);
		}

		public function getSlotApi()
		{
			return $this->_slotApi;
		}

		public function skipSlot()
		{
			return $this->_skipInterface();
		}

		public function isEmpty()
		{
			return $this->_slotApi->isEmpty();
		}

		public function getSlotName()
		{
			if(!array_key_exists('slotName', $this->_datas))
			{
				$slotName = $this->_slotApi->getSlotLabel();

				if($slotName === false) {
					$slotId = $this->_slotApi->getSlotId();
					$hostName = $this->_portApi->getTopEquipmentLabel();
					throw new Exception("Impossible de résoudre le label du slot ID '".$slotId."' pour l'équipement '".$hostName."'", E_USER_ERROR);
				}

				$this->_datas['slotName'] = $slotName;
			}

			return $this->_datas['slotName'];
		}

		/**
		  * @return int
		  */
		protected function _getInterfaceId()
		{
			return $this->getSlotId();
		}

		/**
		  * @return Addon\Dcim\Api_Equipment_Abstract
		  */
		protected function _getInterfaceApi()
		{
			return $this->getSlotApi();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceKey()
		{
			return $this->getSlotKey();
		}

		/**
		  * @return int
		  */
		protected function _getInterfaceIndex()
		{
			return $this->getSlotIndex();
		}

		/**
		  * @return string
		  */
		protected function _getInterfaceName()
		{
			return $this->getSlotName();
		}

		public function getStatus()
		{
			if(!array_key_exists('status', $this->_datas)) {
				$this->_datas['status'] = !$this->isEmpty();
			}

			return $this->_datas['status'];
		}

		public function getDescription()
		{
			if($this->_description === null) {
				$this->_description = $this->getHostName()." ".$this->getSlotName();
			}

			return $this->_description;
		}

		/**
		  * @return array Return array indexed with slot keys
		  */
		public function getDatas()
		{
			if(!$this->skipSlot())
			{
				$datas = array();

				$this->getStatus();
				$this->getHostname();
				$this->getSlotName();
				$this->getAttributes();
				$this->getDescription();

				$datas[$this->getSlotKey()] = $this->_datas;

				return $datas;
			}
			else {
				throw new Exception("Ce slot '".$this->slotName."' ne doit pas être traité", E_USER_ERROR);
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getEquipmentId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getSlotId()) {
				return $this->_slotApi->getTopEquipmentId();
			}
			else {
				$Api_Equipment_Slot = new Api_Equipment_Slot($connectorId);
				return $Api_Equipment_Slot->getTopEquipmentId();
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getTopModuleId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getSlotId()) {
				return $this->_slotApi->getModuleEquipmentId();
			}
			else {
				$Api_Equipment_Slot = new Api_Equipment_Slot($connectorId);
				return $Api_Equipment_Slot->getModuleEquipmentId();
			}
		}

		/**
		  * @param null|int $connectorId
		  * @return false|int
		  */
		public function getModuleId($connectorId = null)
		{
			if($connectorId === null || (int) $connectorId === $this->getSlotId()) {
				$moduleId = $this->_slotApi->getParentEquipmentId();
			}
			else {
				$Api_Equipment_Slot = new Api_Equipment_Slot($connectorId);
				$moduleId = $Api_Equipment_Slot->getParentEquipmentId();
			}

			return ($moduleId !== $this->equipment->id) ? ($moduleId) : (false);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'slotId': {
					return $this->getSlotId();
				}
				case 'slotApi': {
					return $this->getSlotApi();
				}
				case 'slotKey': {
					return $this->getSlotKey();
				}
				case 'slotIndex': {
					return $this->getSlotIndex();
				}
				case 'slotName': {
					return $this->getSlotName();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}