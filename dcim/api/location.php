<?php
	class Dcim_Api_Location extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'location';
		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - Location1',
				'location' => 'CW - TOOLS-CLI - Location2',
				'subLocation' => 'CW - TOOLS-CLI - Location3',
		);

		const ROOT_SITE = 'Cloudwatt';

		static protected $_rootLocationId = null;

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
					$path = $this->getPath();
					$path = explode(',', $path);

					$locationId = $this->getRootLocationId();

					for($i=1; $i<count($path); $i++)
					{
						$locationId = $this->_DCIM->getLocationIdByParentLocationIdLocationLabel($locationId, $path[$i], false);

						if($locationId === false) {
							break;
						}
					}

					$this->_parentLocationId = ($i === count($path)) ? ($locationId) : (false);
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
				return false;
			}
		}

		public function getSubLocationId($locationLabel)
		{
			if($this->locationExists()) {
				return $this->_DCIM->getLocationIdByParentLocationIdLocationLabel($this->getLocationId(), $locationLabel, false);
			}
			else {
				return false;
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
				/*case 'getRootLocationId':
					return self::getRootLocationId();*/
				default:
					throw new Exception('Method '.$method.' does not exist', E_USER_ERROR);
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
			if(self::$_rootLocationId === null) {
				$result = self::$_DCIM->getSiteId(self::ROOT_SITE);
				self::$_rootLocationId = (self::$_DCIM->isValidReturn($result)) ? ($result) : (false);
			}

			return self::$_rootLocationId;
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