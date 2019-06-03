<?php
	namespace Addon\Dcim;

	class Api_Cable extends Api_Abstract_Location
	{
		const OBJECT_KEY = 'CABLE';
		const OBJECT_TYPE = 'cable';
		const OBJECT_NAME = 'cable';

		const REPORT_NAMES = array(
				'self' => 'CW - TOOLS-CLI - Cable0',
				'label' => 'CW - TOOLS-CLI - Cable1',
				'equipment' => 'CW - TOOLS-CLI - Cable2',
		);

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'label';
		const FIELD_DESC = 'description';


		public function cableIdIsValid($cableId)
		{
			return $this->objectIdIsValid($cableId);
		}

		public function hasCableId()
		{
			return $this->hasObjectId();
		}

		public function getCableId()
		{
			return $this->getObjectId();
		}

		public function cableExists()
		{
			return $this->objectExists();
		}

		public function setCableLabel($cableLabel)
		{
			return $this->_setObjectLabel($cableLabel);
		}

		public function hasCableLabel()
		{
			return $this->hasObjectLabel();
		}

		public function getCableLabel()
		{
			return $this->getObjectLabel();
		}

		protected function _getObject()
		{
			if($this->_objectExists === null || $this->objectExists())
			{
				if($this->_objectDatas === null)
				{
					$args = array('cableid' => $this->getCableId());
					$results = $this->_adapter->getReportResults(self::REPORT_NAMES['self'], $args);

					if(count($results) === 1) {
						$this->_objectDatas = $results[0];
					}
					else {
						$this->_objectDatas = false;
					}
				}

				return $this->_objectDatas;
			}
			else {
				return false;
			}
		}

		/**
		  * Do not call hasLocationId otherwise risk of infinite loop
		  */
		public function getLocationId()
		{
			if($this->cableExists())
			{
				if($this->_locationId === null)
				{
					$args = array('cableid' => $this->getCableId());
					$results = $this->_adapter->getReportResults(self::REPORT_NAMES['self'], $args);

					if(count($results) === 1) {
						$this->_locationId = $result['location_id'];
					}
					else {
						$this->_locationId = false;
					}
				}

				return $this->_locationId;
			}
			elseif($this->_locationId !== null) {
				return $this->_locationId;
			}
			else {
				return false;
			}
		}

		/**
		  * @return array All port IDs or empty array
		  */
		public function getPortIds()
		{
			// @todo a coder
			return array();
		}

		/**
		  * @param $template string
		  * @param $label string
		  * @return false|int Cable ID or false if error has occured
		  */
		public function cabling($template, $label = null)
		{
			$this->_errorMessage = null;

			if(!$this->cableExists())
			{
				if($this->hasLocationId())
				{
					if($this->_adapter->templateExists($template))
					{
						if($label === null && $this->hasCableLabel()) {
							$label = $this->getCableLabel();
						}

						try {
							$cableId = $this->_adapter->addCable($this->getLocationId(), $template, $label);
						}
						catch(E\Message $e) {
							$this->_errorMessage = "Unable to create cable '".$template."' '".$label."' to location '".$this->locationApi->label."' in DCIM: ".$e->getMessage();
							$cableId = false;
						}

						if($cableId !== false) {
							$this->_hardReset(false);
							$this->_setObjectId($cableId);
							return $cableId;
						}
					}
					else {
						$this->_errorMessage = "DCIM template '".$template."' is missing";
					}
				}
				else {
					$this->_errorMessage = "DCIM location is required";
				}
			}
			else {
				$this->_errorMessage = "DCIM cable '".$this->label."' already exists";
			}

			return false;
		}

		/**
		  * @param $portApi Addon\Dcim\Api_Equipment_Port
		  * @return false|true
		  */
		public function connect(Api_Equipment_Port $portApi)
		{
			$this->_errorMessage = null;

			if($this->cableExists())
			{
				if($portApi->portExists())
				{
					try {
						$cableId = $this->_adapter->connectCable($this->getCableId(), $portApi->id);
					}
					catch(E\Message $e) {
						$this->_errorMessage = "Unable to connect cable '".$this->label."' to port '".$portApi->label."' in DCIM: ".$e->getMessage();
						$cableId = false;
					}

					if($cableId !== false) {
						return true;
					}
				}
				else {
					$this->_errorMessage = "DCIM port '".$portApi->label."' does not exist";
				}
			}
			else {
				$this->_errorMessage = "DCIM cable does not exist";
			}

			return false;
		}

		/**
		  * @param string $label
		  * @return bool
		  */
		public function renameLabel($label)
		{
			return $this->_updateInfos($label, $this->description);
		}

		/**
		  * @param string $description
		  * @return bool
		  */
		public function setDescription($description)
		{
			return $this->_updateInfos($this->label, $description);
		}

		/**
		  * @param string $label
		  * @param string $description
		  * @return bool
		  */
		public function updateInfos($label, $description)
		{
			return $this->_updateInfos($label, $description);
		}

		/**
		  * @param string $label
		  * @param string $description
		  * @return bool
		  */
		protected function _updateInfos($label, $description)
		{
			if($this->cableExists())
			{
				try {
					$status = $this->_adapter->updateCableInfos($this->getCableId(), $label, $description);
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to update cable informations from DCIM: ".$e->getMessage();
					$status = false;
				}

				$this->refresh();
				return $status;
			}
			else {
				return false;
			}
		}

		/**
		  * @return bool
		  */
		public function remove()
		{
			$this->_errorMessage = null;

			if($this->cableExists())
			{
				try {
					$status = $this->_adapter->removeCable($this->getCableId());
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to remove cable from DCIM: ".$e->getMessage();
					$status = false;
				}

				$this->_hardReset();
				return $status;
			}
			else {
				$this->_errorMessage = "DCIM cable does not exist";
			}

			return false;
		}

		/**
		  * @param bool $resetObjectId
		  * @return void
		  */
		protected function _hardReset($resetObjectId = false)
		{
			$this->_softReset($resetObjectId);
			$this->_resetAttributes();
			$this->_resetLocation();
		}

		protected function _resetAttributes()
		{
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'description': {
					return $this->_getField(self::FIELD_DESC, 'string&&!empty');
				}
				default: {
					return parent::__get($name);
				}
			}
		}

		public function __call($method, $parameters = null)
		{
			if(substr($method, 0, 3) === 'get')
			{
				$name = substr($method, 3);
				$name = mb_strtolower($name);

				switch($name)
				{
					case 'description': {
						return $this->_getField(self::FIELD_DESC, 'string&&!empty');
					}
				}
			}

			return parent::__call($method, $parameters);
		}
	}