<?php
	namespace Addon\Dcim;

	class Api_Equipment_Port extends Api_Equipment_Abstract
	{
		const OBJECT_KEY = 'PORT';
		const OBJECT_TYPE = 'port';
		const OBJECT_NAME = 'port';

		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - PortLabel',
				'location' => 'CW - TOOLS-CLI - PortEquipment',
		);

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'name';

		protected $_equipmentId;
		protected $_equipmentApi;

		protected $_cableId;
		protected $_cableApi;

		protected $_connectedCableId;
		protected $_connectedPortId;


		public function portIdIsValid($portId)
		{
			return $this->objectIdIsValid($portId);
		}

		public function hasPortId()
		{
			return $this->hasObjectId();
		}

		public function getPortId()
		{
			return $this->getObjectId();
		}

		public function portExists()
		{
			return $this->objectExists();
		}

		public function getPortLabel()
		{
			return $this->getObjectLabel();
		}

		/**
		  * @return bool Port is connected or not
		  */
		public function isConnected()
		{
			return ($this->getConnectedCableId() !== false);
		}

		/**
		  * Retourne l'ID du câble connecté à ce port
		  * /!\ Do not call isConnected otherwise risk of infinite loop
		  *
		  * @return false|int Cable ID
		  */
		public function getConnectedCableId()
		{
			if($this->portExists())
			{
				if($this->_connectedCableId === null) {
					$result = $this->_DCIM->getConnectedCableIdByPortId($this->getPortId());
					$this->_connectedCableId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
				}

				return $this->_connectedCableId;
			}
			else {
				return false;
			}
		}

		/**
		  * Retourne l'ID du port directement connecté à ce port
		  *
		  * @return false|int Port ID
		  */
		public function getConnectedPortId()
		{
			if(!$this->isConnected()) {
				return false;
			}
			elseif($this->_connectedPortId === null) {
				$result = $this->_DCIM->getConnectedPortIdByPortId($this->getPortId());
				$this->_connectedPortId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
			}

			return $this->_connectedPortId;
		}

		/**
		  * Retourne l'ID du port final connecté à ce port
		  *
		  * @return false|int Port ID
		  */
		public function getEndConnectedPortId()
		{
			$connectedPortId = $this->getConnectedPortId();

			if($connectedPortId !== false)
			{
				for(;;)
				{
					$otherSidePortId = $this->_DCIM->getOtherSidePortIdByPortId($connectedPortId);

					if($this->_DCIM->isValidReturn($otherSidePortId))
					{
						$conPortId = $this->_DCIM->getConnectedPortIdByPortId($otherSidePortId);

						if($this->_DCIM->isValidReturn($conPortId)) {
							$connectedPortId = (int) $conPortId;
							continue;
						}
					}

					break;
				}

				return $connectedPortId;
			}

			return false;
		}

		/**
		  * Return the parent equipment ID this port is on
		  *
		  * @return false|int
		  */
		public function getParentEquipmentId()
		{
			if(!$this->hasPortId() || !$this->portExists()) {
				return false;
			}
			else
			{
				$result = $this->_DCIM->getParentEquipmentIdByPortId($this->getPortId());
	
				if($this->_DCIM->isValidReturn($result)) {
					return (int) $result;
				}
			}

			return false;
		}

		/**
		  * Return the first top module equipment ID this port is on
		  *
		  * @return false|int
		  */
		public function getModuleEquipmentId()
		{
			$parentEquipmentId = $this->getParentEquipmentId();
			return ($parentEquipmentId !== false) ? ($this->_getModuleEquipmentId($parentEquipmentId)) : (false);
		}

		protected function _getModuleEquipmentId($equipmentId, $moduleId = false)
		{
			$parentEquipId = $this->_DCIM->getParentEquipmentIdByEquipmentId($equipmentId);
			return ($this->_DCIM->isValidReturn($parentEquipId)) ? ($this->_getModuleEquipmentId($parentEquipId, $equipmentId)) : ($moduleId);
		}

		/**
		  * Return the top equipment ID this port is on
		  *
		  * @return false|int
		  */
		public function getTopEquipmentId()
		{
			if($this->portExists())
			{
				if($this->_equipmentId === null) {
					$result = $this->_DCIM->getTopEquipmentIdByPortId($this->getPortId());
					$this->_equipmentId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
				}

				return $this->_equipmentId;
			}
			else {
				return false;
			}
		}

		/**
		  * Return the top equipment label this port is on
		  *
		  * @return false|int
		  */
		public function getTopEquipmentLabel()
		{
			$equipmentId = $this->getTopEquipmentId();

			if($equipmentId !== false)
			{
				$result = $this->_DCIM->resolvToLabel('equipment', $equipmentId);

				if($this->_DCIM->isValidReturn($result)) {
					return (string) $result;
				}
			}

			return false;
		}

		/**
		  * Return the top equipment API this port is on
		  *
		  * @return false|Addon\Dcim\Api_Equipment
		  */
		public function getEquipmentApi()
		{
			if($this->_equipmentApi === null)
			{
				$equipmentId = $this->getTopEquipmentId();

				if($equipmentId !== false) {
					$this->_equipmentApi = new Api_Equipment($equipmentId);
				}
				else {
					$this->_equipmentApi = false;
				}
			}

			return $this->_equipmentApi;
		}

		public function getCableId()
		{
			if($this->_cableId === null) {
				$this->_cableId = $this->getConnectedCableId();
			}

			return $this->_cableId;
		}

		public function getCableApi()
		{
			if($this->_cableApi === null)
			{
				$cableId = $this->getCableId();

				if($cableId !== false) {
					$this->_cableApi = new Api_Cable($cableId);
				}
				else {
					$this->_cableApi = false;
				}
			}

			return $this->_cableApi;
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
			$this->_resetCable();
			$this->_resetPort();
		}

		protected function _resetAttributes()
		{
		}

		protected function _resetEquipment()
		{
		$this->_equipmentId = null;
		$this->_equipmentApi = null;
		}

		protected function _resetCable()
		{
		$this->_cableId = null;
		$this->_cableApi = null;
		$this->_connectedCableId = null;
		}

		protected function _resetPort()
		{
			$this->_connectedPortId = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'equipmentApi': {
					return $this->getEquipmentApi();
				}
				case 'cableApi': {
					return $this->getCableApi();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}