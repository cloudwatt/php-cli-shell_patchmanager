<?php
	namespace Addon\Dcim;

	abstract class Equipment_Slot extends Equipment_Interface_Physical implements \ArrayAccess, \IteratorAggregate, \Countable
	{
		/**
		  * @var Addon\Dcim\Api_Equipment_Slot
		  */
		protected $_equipmentSlotApi;

		/**
		  * @var int
		  */
		protected $_slotId = null;

		/**
		  * @var string
		  */
		protected $_slotKey = null;

		/**
		  * @var string
		  */
		protected $_description = null;

		
		public function __construct($slotId)
		{
			$this->_slotId = (int) $slotId;
			$this->_equipmentSlotApi = new Api_Equipment_Slot($this->_slotId);		// /!\ Ne pas passer null
		}

		public function skipSlot()
		{
			return $this->_skipInt();
		}

		public function isEmpty()
		{
			return $this->_equipmentSlotApi->isEmpty();
		}
		
		public function getSlotId()
		{
			return $this->_slotId;
		}

		protected function _getKey()
		{
			return $this->getSlotKey();
		}

		public function getSlotKey()
		{
			if($this->_slotKey === null) {
				$this->_slotKey = $this->_nameToKey();
			}

			return $this->_slotKey;
		}

		protected function _nameToKey($slotName = null)
		{
			if($slotName === null) {
				$slotName = $this->getSlotName();
			}

			return mb_strtolower($slotName);
		}

		public function getEquipmentId()
		{
			return $this->_equipmentSlotApi->getTopEquipmentId();
		}

		public function getStatus()
		{
			if(!array_key_exists('status', $this->_datas)) {
				$this->_datas['status'] = !$this->isEmpty();
			}

			return $this->_datas['status'];
		}

		public function getHostName()
		{
			if(!array_key_exists('hostName', $this->_datas))
			{
				$hostName = $this->_equipmentSlotApi->getTopEquipmentLabel();

				if($hostName === false) {
					$equipmentId = $this->_Api_Equipment_Port->getTopEquipmentId();
					throw new Exception("Impossible de résoudre le label pour l'équipement ID \"".$equipmentId."\"", E_USER_ERROR);
				}

				$this->_datas['hostName'] = current(explode('.', $hostName, 2));
			}

			return $this->_datas['hostName'];
		}

		public function getSlotName()
		{
			if(!array_key_exists('slotName', $this->_datas))
			{
				$slotName = $this->_equipmentSlotApi->getSlotLabel();

				if($slotName === false) {
					$slotId = $this->_equipmentSlotApi->getSlotId();
					throw new Exception("Impossible de résoudre le label du slot ID \"".$slotId."\"", E_USER_ERROR);
				}

				$this->_datas['slotName'] = $slotName;
			}

			return $this->_datas['slotName'];
		}

		public function getDescription()
		{
			if($this->_description === null) {
				$this->_description = $this->getHostName()." ".$this->getSlotName();
			}

			return $this->_description;
		}

		// /!\ Doit retourner un tableau
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
				throw new Exception("Ce slot ne doit pas être traité", E_USER_ERROR);
			}
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
			return new ArrayIterator($datas);
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