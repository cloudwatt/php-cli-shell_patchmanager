<?php
	class Dcim_Api_Equipment_Port extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'port';
		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - PortLabel',
				'location' => 'CW - TOOLS-CLI - PortEquipment',
		);

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

		public function isConnected()
		{
			return ($this->getConnectedCableId() !== false);
		}

		public function getConnectedCableId()
		{
			if(!$this->hasPortId() || !$this->portExists()) {
				return false;
			}
			elseif($this->_connectedCableId === null) {
				$result = $this->_DCIM->getConnectedCableIdByPortId($this->getPortId());
				$this->_connectedCableId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
			}

			return $this->_connectedCableId;
		}

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

		public function getEndConnectedPortId()
		{
			$connectedPortId = $this->getConnectedPortId();

			if($connectedPortId !== false)
			{
				for(;;)
				{
					$otherSidePortId = $this->_DCIM->getOtherSidePortIdByPortId($connectedPortId);

					if($this->_DCIM->isValidReturn($otherSidePortId)) {
						$connectedPortId = $this->_DCIM->getConnectedPortIdByPortId($otherSidePortId);
						continue;
					}

					break;
				}

				if($this->_DCIM->isValidReturn($connectedPortId)) {
					return (int) $connectedPortId;
				}
			}

			return false;
		}

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

		public function getEquipmentApi()
		{
			if($this->_equipmentApi === null)
			{
				$equipmentId = $this->getTopEquipmentId();

				if($equipmentId !== false) {
					$this->_equipmentApi = new Dcim_Api_Equipment($equipmentId);
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
					$this->_cableApi = new Dcim_Api_Cable($cableId);
				}
				else {
					$this->_cableApi = false;
				}
			}

			return $this->_cableApi;
		}

		public function getUserAttr($category, $attrLabel)
		{
			$attrName = $this->_DCIM->getUserAttrName($category, $attrLabel);
			$result = $this->_DCIM->getUserAttrByPortId($this->getPortId(), $attrName);

			return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
		}

		protected function _reset($resetPortId = false)
		{
			if($resetPortId) {
				$this->_portId = null;
			}

			$this->_portExists = null;
			$this->_portLabel = null;
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