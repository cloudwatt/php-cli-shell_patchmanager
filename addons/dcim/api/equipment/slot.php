<?php
	namespace Addon\Dcim;

	class Api_Equipment_Slot extends Api_Equipment_Abstract
	{
		const OBJECT_KEY = 'SLOT';
		const OBJECT_TYPE = 'slot';
		const OBJECT_NAME = 'slot';

		const REPORT_NAMES = array();

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'name';

		protected $_equipmentId;
		protected $_equipmentApi;

		protected $_childEquipmentId;


		public function slotIdIsValid($slotId)
		{
			return $this->objectIdIsValid($slotId);
		}

		public function hasSlotId()
		{
			return $this->hasObjectId();
		}

		public function getSlotId()
		{
			return $this->getObjectId();
		}

		public function slotExists()
		{
			return $this->objectExists();
		}

		public function getSlotLabel()
		{
			return $this->getObjectLabel();
		}

		public function isEmpty()
		{
			return ($this->getChildEquipmentId() === false);
		}

		/**
		  * Return the parent equipment ID this slot is on
		  *
		  * @return false|int
		  */
		public function getParentEquipmentId()
		{
			if($this->slotExists())
			{
				$result = $this->_adapter->getParentEquipmentIdBySlotId($this->getSlotId());
	
				if($this->_adapter->isValidReturn($result)) {
					return (int) $result;
				}
			}

			return false;
		}

		/**
		  * Return the top equipment ID this slot is on
		  *
		  * @return false|int
		  */
		public function getTopEquipmentId()
		{
			if($this->slotExists())
			{
				if($this->_equipmentId === null) {
					$result = $this->_adapter->getTopEquipmentIdBySlotId($this->getSlotId());
					$this->_equipmentId = ($this->_adapter->isValidReturn($result)) ? ((int) $result) : (false);
				}

				return $this->_equipmentId;
			}
			else {
				return false;
			}
		}

		/**
		  * Return the top equipment label this slot is on
		  *
		  * @return false|int
		  */
		public function getTopEquipmentLabel()
		{
			$equipmentId = $this->getTopEquipmentId();

			if($equipmentId !== false)
			{
				$result = $this->_adapter->resolvToLabel('equipment', $equipmentId);

				if($this->_adapter->isValidReturn($result)) {
					return (string) $result;
				}
			}

			return false;
		}

		/**
		  * Return the top equipment API this slot is on
		  *
		  * @return false|Addon\Dcim\Api_Equipment
		  */
		public function getEquipmentApi()
		{
			if($this->_equipmentApi === null)
			{
				$equipmentId = $this->getTopEquipmentId();

				if($equipmentId !== false) {
					$this->_equipmentApi = Api_Equipment::factory($equipmentId);
				}
				else {
					$this->_equipmentApi = false;
				}
			}

			return $this->_equipmentApi;
		}

		/**
		  * Return the child equipment ID this slot is on
		  *
		  * @return false|int
		  */
		public function getChildEquipmentId()
		{
			if(!$this->hasSlotId() || !$this->slotExists()) {
				return false;
			}
			elseif($this->_childEquipmentId === null) {
				$result = $this->_adapter->getEquipmentIdBySlotId($this->getSlotId());
				$this->_childEquipmentId = ($this->_adapter->isValidReturn($result)) ? ((int) $result) : (false);
			}

			return $this->_childEquipmentId;
		}

		/**
		  * @param bool $resetObjectId
		  * @return void
		  */
		protected function _hardReset($resetObjectId = false)
		{
			$this->_softReset($resetObjectId);
			$this->_resetAttributes();
			$this->_resetEquipment();
		}
  
		protected function _resetAttributes()
		{
		}

		protected function _resetEquipment()
		{
			$this->_equipmentId = null;
			$this->_equipmentApi = null;
			$this->_childEquipmentId = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'equipmentApi': {
					return $this->getEquipmentApi();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}