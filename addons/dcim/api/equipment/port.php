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
					$result = $this->_adapter->getConnectedCableIdByPortId($this->getPortId());
					$this->_connectedCableId = ($this->_adapter->isValidReturn($result)) ? ((int) $result) : (false);
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
				$result = $this->_adapter->getConnectedPortIdByPortId($this->getPortId());
				$this->_connectedPortId = ($this->_adapter->isValidReturn($result)) ? ((int) $result) : (false);
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
					$otherSidePortId = $this->_adapter->getOtherSidePortIdByPortId($connectedPortId);

					if($this->_adapter->isValidReturn($otherSidePortId))
					{
						$conPortId = $this->_adapter->getConnectedPortIdByPortId($otherSidePortId);

						if($this->_adapter->isValidReturn($conPortId)) {
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
			if($this->portExists())
			{
				$result = $this->_adapter->getParentEquipmentIdByPortId($this->getPortId());
	
				if($this->_adapter->isValidReturn($result)) {
					return (int) $result;
				}
			}

			return false;
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
					$result = $this->_adapter->getTopEquipmentIdByPortId($this->getPortId());
					$this->_equipmentId = ($this->_adapter->isValidReturn($result)) ? ((int) $result) : (false);
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
				$result = $this->_adapter->resolvToLabel('equipment', $equipmentId);

				if($this->_adapter->isValidReturn($result)) {
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
					$this->_equipmentApi = Api_Equipment::factory($equipmentId);
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
					$this->_cableApi = Api_Cable::factory($cableId);
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