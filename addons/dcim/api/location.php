<?php
	namespace Addon\Dcim;

	use Core as C;

	class Api_Location extends Api_Abstract
	{
		const OBJECT_KEY = 'LOCATION';
		const OBJECT_TYPE = 'location';
		const OBJECT_NAME = 'location';

		const REPORT_NAMES = array(
				'root' => 'CW - TOOLS-CLI - Location - Root',
				'self' => 'CW - TOOLS-CLI - Location0',
				'label' => 'CW - TOOLS-CLI - Location1',
				'location' => 'CW - TOOLS-CLI - Location2',
				'subLocation' => 'CW - TOOLS-CLI - Location3',
		);

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'name';
		const FIELD_DESC = 'description';

		/**
		  * Enable or disable cache feature
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_cache = array();		// DCIM server ID keys, boolean value

		/**
		  * All locations (cache)
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_objects = array();	// DCIM server ID keys, array value

		/**
		  * @var array
		  */
		static protected $_rootLocations = null;

		/**
		  * @var int
		  */
		static protected $_rootLocationId = null;

		/**
		  * @var int
		  */
		protected $_parentLocationId = null;

		/**
		  * @var Addon\Dcim\Api_Location
		  */
		protected $_parentLocationApi = null;

		/**
		  * Path to self object
		  * @var array
		  */
		protected $_path = null;


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

		public function setLocationLabel($locationLabel)
		{
			return $this->_setObjectLabel($locationLabel);
		}

		public function hasLocationLabel()
		{
			return $this->hasObjectLabel();
		}

		public function getLocationLabel()
		{
			return $this->getObjectLabel();
		}

		protected function _getObject()
		{
			if($this->_objectExists === null || $this->objectExists())
			{
				if($this->_objectDatas === null)
				{
					$args = array('locationid' => $this->getLocationId());
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
					$Api_Location = new $selfClassName();
					$locationId = $Api_Location->getSubLocationId($path[0]);

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
					$this->_parentLocationApi = new Api_Location($locationId);
				}
				else {
					$this->_parentLocationApi = false;
				}
			}

			return $this->_parentLocationApi;
		}

		public function getPath($includeLabel = false, $pathSeparator = false)
		{
			if($this->locationExists())
			{
				if($this->_path === null)
				{
					// Ne retourne pas le chemin complet
					/*$result = $this->_DCIM->getLocationPathByLocationId($this->getLocationId());
					return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);*/

					$args = array('locationid' => $this->getLocationId());
					$results = self::$_DCIM->getReportResults(self::REPORT_NAMES['self'], $args);

					if(count($results) === 1) {
						$result = $results[0];
						$path = explode(self::SEPARATOR_PATH, $result['fullpath']);
						$this->_path = array_reverse($path);
					}
					else {
						$this->_path = false;
					}
				}

				if($this->_path !== false)
				{
					$path = $this->_path;

					if($includeLabel && $this->hasLocationLabel()) {
						$path[] = $this->getLocationLabel();
					}

					if($pathSeparator === false) {
						$pathSeparator = self::SEPARATOR_PATH;
					}

					return implode($pathSeparator, $path);
				}
			}
			elseif($includeLabel && $this->hasLocationLabel()) {
				return $this->getLocationLabel();
			}

			return false;
		}

		/**
		  * @todo /!\ getSubLocationIds si aucune sous location, retourne la location courante
		  * Ce n'est pas forcément bien, revoir cela afin de retourner array() (empty)
		  *
		  * @return array All sub location IDs or root location IDs
		  */
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
				$result = $this->_filterObjects($results, 'name', $locationLabel);
				return (C\Tools::is('array', $result) && count($result) === 1) ? ($result[0]['entity_id']) : (false);
			}
		}

		public function findLocations($locationLabel, $recursion = false)
		{
			if($this->hasLocationId()) {
				$locationId = $this->getLocationId();
				return self::searchLocations($locationLabel, $locationId, $recursion);
			}
			else
			{
				$rootLocationLabels = self::getRootLocations();

				$locationLabel = preg_quote($locationLabel, '#');
				$locationLabel = str_ireplace('\\*', '.*', $locationLabel);

				$results = array_filter($rootLocationLabels, function(&$item) use(&$locationLabel) {
					return preg_match("#".$locationLabel."#i", $item[self::FIELD_NAME]);
				});

				return array_values($results);
			}
		}

		/**
		  * @return array All cabinet IDs or empty array
		  */
		public function getCabinetIds()
		{
			if($this->locationExists()) {
				return $this->_DCIM->getCabinetIdsByLocationId($this->getLocationId());
			}
			else {
				return array();
			}
		}

		/**
		  * @return array All equipment labels or empty array
		  */
		public function getCabinetLabels()
		{
			if($this->locationExists()) {
				return $this->_DCIM->getCabinetLabelsByLocationId($this->getLocationId());
			}
			else {
				return array();
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

		public function findCabinets($cabinetLabel, $recursion = false)
		{
			if($this->hasLocationId()) {
				$locationId = $this->getLocationId();
				return Api_Cabinet::searchCabinets($cabinetLabel, $locationId, $recursion);
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

		public function findEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, $recursion = false)
		{
			if($this->hasLocationId()) {
				$locationId = $this->getLocationId();
				return Api_Equipment::searchEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, null, $locationId, $recursion);
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
			$this->_path = null;
		}

		protected function _resetLocation()
		{
			$this->_parentLocationId = null;
			$this->_parentLocationApi = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'description': {
					return $this->_getField(self::FIELD_DESC, 'string&&!empty');
				}
				case 'parentLocationApi': {
					return $this->_parentLocationApi();
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

		public static function getRootLocations()
		{
			if(self::$_rootLocations === null) {
				self::$_rootLocations = self::_getObjectsFromReport('root', null);
			}

			return self::$_rootLocations;
		}

		public static function getRootLocationIds()
		{
			$rootLocations = self::getRootLocations();
			return array_column($rootLocations, 'entity_id');
		}

		public static function getRootLocationLabels()
		{
			$rootLocations = self::getRootLocations();
			return array_column($rootLocations, self::FIELD_NAME);
		}

		public static function getRootLocationId()
		{
			if(self::$_rootLocationId === null)
			{
				$locations = self::getRootLocations();

				if(C\Tools::is('array', $locations) && count($locations) === 1) {
					self::$_rootLocationId = $locations[0]['entity_id'];
				}
				else {
					throw new Exception("Unable to get root location ID", E_USER_ERROR);
				}
			}

			return self::$_rootLocationId;
		}

		/**
		  * Return all locations matches request
		  *
		  * Ne pas rechercher que les locations root si locationId est égale à null
		  *
		  * @param string $locationLabel Location label, wildcard * is allowed
		  * @param int $locationId Location ID
		  * @param bool $recursion
		  * @return false|array
		  */
		public static function searchLocations($locationLabel, $locationId = null, $recursion = false)
		{
			$args = array('label' => $locationLabel);

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

			foreach($results as &$result) {
				$fullPath = explode(self::SEPARATOR_PATH, $result['fullpath']);
				$fullPath = array_reverse($fullPath);
				$result['path'] = implode(self::SEPARATOR_PATH, $fullPath);
				$result['fullpath'] = $result['path'];
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
			$result = self::searchLocations(self::WILDCARD);

			if($result !== false) {
				self::$_objects[$id] = $result;
				return true;
			}
			else {
				return false;
			}
		}
	}