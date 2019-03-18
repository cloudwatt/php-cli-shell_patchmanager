<?php
	namespace Addon\Dcim;

	use Core as C;

	class Api_Cabinet extends Api_Abstract_Location
	{
		const OBJECT_KEY = 'CABINET';
		const OBJECT_TYPE = 'cabinet';
		const OBJECT_NAME = 'cabinet';

		const REPORT_NAMES = array(
				'self' => 'CW - TOOLS-CLI - Cabinet0',
				'label' => 'CW - TOOLS-CLI - Cabinet1',
				'location' => 'CW - TOOLS-CLI - Cabinet2',
				'subLocation' => 'CW - TOOLS-CLI - Cabinet3',
		);

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'name';
		const FIELD_DESC = 'description';

		const SIDE_FRONT = 'front';
		const SIDE_REAR = 'rear';

		/**
		  * Enable or disable cache feature
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_cache = array();		// DCIM server ID keys, boolean value

		/**
		  * All cabinets (cache)
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_objects = array();	// DCIM server ID keys, array value


		public function cabinetIdIsValid($cabinetId)
		{
			return $this->objectIdIsValid($cabinetId);
		}

		public function hasCabinetId()
		{
			return $this->hasObjectId();
		}

		public function getCabinetId()
		{
			return $this->getObjectId();
		}

		public function cabinetExists()
		{
			return $this->objectExists();
		}

		public function getCabinetLabel()
		{
			return $this->getObjectLabel();
		}

		protected function _getObject()
		{
			if($this->_objectExists === null || $this->objectExists())
			{
				if($this->_objectDatas === null)
				{
					$args = array('cabinetid' => $this->getCabinetId());
					$results = $this->_DCIM->getReportResults(self::REPORT_NAMES['self'], $args);

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

		public function getLocationId()
		{
			if($this->cabinetExists())
			{
				if($this->_locationId === null)
				{
					// Solution sans rapport
					/*$equipmentIds = $this->getEquipmentIds();

					if(C\Tools::is('array&&count>0', $equipmentIds))
					{
						$equipmentId = current($equipmentIds);

						$Api_Equipment = new Api_Equipment($equipmentId);
						$locationId = $Api_Equipment->getLocationId();
						$this->_locationId = ($locationId !== false) ? ($locationId) : (false);
					}
					else {
						$this->_locationId = false;
					}*/

					$args = array('cabinetid' => $this->getCabinetId());
					$results = self::$_DCIM->getReportResults(self::REPORT_NAMES['self'], $args);

					if(count($results) === 1) {
						$result = $results[0];
						$this->_locationId = $result['location_id'];
					}
					else {
						$this->_locationId = false;
					}
				}

				return $this->_locationId;
			}
			else {
				return false;
			}
		}

		public function getTemplateU()
		{
			$templateU = $this->_getField('template_u', 'string&&!empty');
			$templateU = substr($templateU, 0, -1);
			
			return (C\Tools::is('int&&>0', $templateU)) ? ((int) $templateU) : (false);
		}

		public function getPath($includeLabel = false, $pathSeparator = false)
		{
			$locationApi = $this->getLocationApi();

			if($locationApi !== false)
			{
				$path = $locationApi->getPath(true, $pathSeparator);

				if($path !== false && $includeLabel)
				{
					if($pathSeparator === false) {
						$pathSeparator = self::SEPARATOR_PATH;
					}

					$path .= $pathSeparator.$this->getCabinetLabel();
				}

				return $path;
			}
			else {
				return false;
			}
		}

		/**
		  * @return array All equipment IDs or empty array
		  */
		public function getEquipmentIds()
		{
			if($this->cabinetExists()) {
				return $this->_DCIM->getEquipmentIdsByCabinetId($this->getCabinetId());
			}
			else {
				return array();
			}
		}

		public function getEquipmentId($equipmentLabel)
		{
			if($this->cabinetExists()) {
				$result = $this->_DCIM->getEquipmentIdByCabinetIdEquipmentLabel($this->getCabinetId(), $equipmentLabel);
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function getEquipmentIdByU($positionU)
		{
			if(preg_match('#^u([0-9]{1,2})$#i', $positionU, $position)) {
				$positionU = (int) $position[1];
			}

			if(C\Tools::is('int&&>0', $positionU))
			{
				$equipments = Api_Equipment::searchEquipments('*', null, null, $this->getCabinetId(), null, false, true);

				foreach($equipments as $equipment)
				{
					if((int) $equipment['position_u'] === (int) $positionU) {
						return $equipment[self::FIELD_ID];
					}
				}
			}

			return false;
		}

		public function findEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, $recursion = false, $firstLevel = true)
		{
			if($this->hasCabinetId()) {
				$cabinetId = $this->getCabinetId();
				return Api_Equipment::searchEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, $cabinetId, null, $recursion, $firstLevel);
			}
			else {
				return false;
			}
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
				case 'locationApi': {
					return $this->getLocationApi();
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

		/**
		  * Return all cabinets matches request
		  *
		  * @param $cabinetLabel string Cabinet label, wildcard * is allowed
		  * @param $locationId int location ID
		  * @param $recursion bool
		  * @return false|array
		  */
		public static function searchCabinets($cabinetLabel, $locationId = null, $recursion = false)
		{
			$args = array('label' => $cabinetLabel);

			array_walk($args, function(&$item)
			{
				if(!C\Tools::is('human', $item)) {
					$item = self::WILDCARD;
				}
			});

			if(C\Tools::is('int&&>0', $locationId)) {
				$args['locationid'] = $locationId;
				$reportName = ($recursion) ? ('subLocation') : ('location');
			}
			else {
				$reportName = 'label';
			}

			$results = self::$_DCIM->getReportResults(self::REPORT_NAMES[$reportName], $args);

			if($results !== false)
			{
				foreach($results as &$result) {
					$fullPath = explode(self::SEPARATOR_PATH, $result['fullpath']);
					$fullPath = array_reverse($fullPath);
					$fullPath[] = $result['location_name'];
					$result['path'] = implode(self::SEPARATOR_PATH, $fullPath);
					$result['fullpath'] = $result['path'];
				}
			}

			return $results;
		}

		/**
		  * @param Addon\Dcim\Main $DCIM
		  * @return bool
		  */
		protected static function _setObjects(C\Addon\Adapter $DCIM = null)
		{
			if($DCIM === null) {
				$DCIM = self::$_DCIM;
			}

			$id = $DCIM->getServerId();
			$result = self::searchCabinets(self::WILDCARD);

			if($result !== false) {
				self::$_objects[$id] = $result;
				return true;
			}
			else {
				return false;
			}
		}
	}