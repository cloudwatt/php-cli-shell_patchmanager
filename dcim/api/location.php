<?php
	class Dcim_Api_Location extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'location';
		const REPORT_NAMES = array(
				'root' => 'CW - TOOLS-CLI - Location - Root',
				'label' => 'CW - TOOLS-CLI - Location1',
				'location' => 'CW - TOOLS-CLI - Location2',
				'subLocation' => 'CW - TOOLS-CLI - Location3',
		);

		static protected $_rootLocationId = null;
		static protected $_rootLocationIds = null;

		protected $_parentLocationId;
		protected $_parentLocationApi;


		public function locationIdIsValid($locationId)
		{
			return $this->objectIdIsValid($locationId);
		}

		public function hasLocationId()
		{
			return $this->hasObjectId();
		}

		public function getLocationId()
		{
			return $this->getObjectId();
		}

		public function locationExists()
		{
			return $this->objectExists();
		}

		public function getLocationLabel()
		{
			return $this->getObjectLabel();
		}

		public function getParentLocationId()
		{
			if($this->locationExists())
			{
				if($this->_parentLocationId === null)
				{
					$this->_parentLocationId = false;

					$path = $this->getPath();
					$path = explode(',', $path);

					$selfClassName = static::class;
					$Dcim_Api_Location = new $selfClassName();
					$locationId = $Dcim_Api_Location->getSubLocationId($path[0]);

					if($locationId !== false)
					{
						for($i=1; $i<count($path); $i++)
						{
							$locationId = $this->_DCIM->getLocationIdByParentLocationIdLocationLabel($locationId, $path[$i], false);

							if($locationId === false) {
								break;
							}
						}

						if($i === count($path)) {
							$this->_parentLocationId = $locationId;
						}
					}
				}

				return $this->_parentLocationId;
			}
			else {
				return false;
			}
		}

		public function getParentLocationApi()
		{
			if($this->_parentLocationApi === null)
			{
				$locationId = $this->getParentLocationId();

				if($locationId !== false) {
					$this->_parentLocationApi = new Dcim_Api_Location($locationId);
				}
				else {
					$this->_parentLocationApi = false;
				}
			}

			return $this->_parentLocationApi;
		}

		public function getPath()
		{
			if($this->locationExists()) {
				$result = $this->_DCIM->getLocationPathByLocationId($this->getLocationId());
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function getSubLocationIds()
		{
			if($this->locationExists()) {
				return $this->_DCIM->getSubLocationIds($this->getLocationId(), false);
			}
			else {
				return $this->getRootLocationIds();
			}
		}

		public function getSubLocationId($locationLabel)
		{
			if($this->locationExists()) {
				return $this->_DCIM->getLocationIdByParentLocationIdLocationLabel($this->getLocationId(), $locationLabel, false);
			}
			else {
				$results = self::$_DCIM->getReportResults(self::REPORT_NAMES['root']);
				$result = $this->_getSubObjects($results, 'name', $locationLabel);
				return (Tools::is('array', $result) && count($result) === 1) ? ($result[0]['entity_id']) : (false);
			}
		}

		public function getCabinetIds()
		{
			if($this->locationExists()) {
				return $this->_DCIM->getCabinetIdsByLocationId($this->getLocationId());
			}
			else {
				return false;
			}
		}

		public function getCabinetId($cabinetLabel)
		{
			if($this->locationExists()) {
				$result = $this->_DCIM->getCabinetIdByLocationIdCabinetLabel($this->getLocationId(), $cabinetLabel);
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function getEquipmentId($equipmentLabel)
		{
			if($this->locationExists()) {
				$result = $this->_DCIM->getEquipmentIdByLocationIdEquipmentLabel($this->getLocationId(), $equipmentLabel);
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function __call($method, $parameters = null)
		{
			switch($method)
			{
				default: {
					throw new Exception('Method '.$method.' does not exist', E_USER_ERROR);
				}
			}
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'parentLocationApi': {
					return $this->_parentLocationApi();
				}
				default: {
					return parent::__get($name);
				}
			}
		}

		public static function getRootLocationId()
		{
			if(self::$_rootLocationId === null)
			{
				$results = self::$_DCIM->getReportResults(self::REPORT_NAMES['root']);

				if(Tools::is('array', $results) && count($results) === 1) {
					self::$_rootLocationId = $results[0]['entity_id'];
				}
				else {
					throw new Exception("Unable to get root location ID", E_USER_ERROR);
				}
			}

			return self::$_rootLocationId;
		}

		public static function getRootLocationIds()
		{
			if(self::$_rootLocationIds === null)
			{
				$results = self::$_DCIM->getReportResults(self::REPORT_NAMES['root']);

				if(Tools::is('array&&count>0', $results))
				{
					array_walk($results, function(&$item) {
						$item = $item['entity_id'];
					});

					self::$_rootLocationIds = $results;
				}
				else {
					throw new Exception("Unable to retreive root location IDs", E_USER_ERROR);
				}
			}

			return self::$_rootLocationIds;
		}

		public static function searchLocations($locationLabel, $locationId = null, $recursion = false)
		{
			$args = array('label' => $locationLabel);

			if(Tools::is('int&&>0', $locationId)) {
				$args['locationid'] = $locationId;
				$reportName = ($recursion) ? ('subLocation') : ('location');
			}
			else {
				$reportName = 'label';
			}

			return self::$_DCIM->getReportResults(self::REPORT_NAMES[$reportName], $args);
		}
	}