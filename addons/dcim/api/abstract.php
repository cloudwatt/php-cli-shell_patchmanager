<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Api_Abstract extends C\Addon\Api_Abstract
	{
		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'name';

		const WILDCARD = '*';
		const SEPARATOR_PATH = ',';

		/**
		  * @var string
		  */
		protected static $_parentAdapter = __NAMESPACE__ .'\Main';

		/**
		  * @var Addon\Dcim\Main
		  */
		protected static $_DCIM = null;			// Global DCIM (enabled)

		/**
		  * @var Addon\Dcim\Main[]
		  */
		protected static $_aDCIM = array();		// a = all/array/available DCIM

		/**
		  * @var Addon\Dcim\Main
		  */
		protected $_DCIM_ = null;				// Local DCIM (for this instance)


		public function __construct($objectId = null)
		{
			parent::__construct($objectId);
			$this->_DCIM_ = &$this->_ownerAdapter;	// /!\ A executer avant _setObjectId
			$this->_setObjectId($objectId);			// @todo temp
		}

		public static function objectIdIsValid($objectId)
		{
			return C\Tools::is('int&&>0', $objectId);
		}

		public function hasObjectId()
		{
			return ($this->_objectId !== null);
		}

		public function getObjectId()
		{
			return $this->_objectId;
		}

		public function objectExists()
		{
			if($this->_objectExists !== null) {
				return $this->_objectExists;
			}
			elseif($this->hasObjectId()) {
				$this->_objectExists = ($this->_getObject() !== false);
				return $this->_objectExists;
			}
			else {
				return false;
			}
		}

		protected function _setObjectLabel($objectLabel)
		{
			if(!$this->objectExists() && C\Tools::is('string&&!empty', $objectLabel)) {
				$this->_objectLabel = $objectLabel;
				return true;
			}
			else {
				return false;
			}
		}

		public function hasObjectLabel()
		{
			return ($this->getObjectLabel() !== false);
		}

		public function getObjectLabel()
		{
			if($this->_objectLabel !== null) {		// /!\ Ne pas appeler hasObjectLabel sinon boucle infinie
				return $this->_objectLabel;
			}
			elseif($this->hasObjectId()) {			// /!\ Ne pas appeler objectExists sinon boucle infinie
				$objectDatas = $this->_getObject();
				$this->_objectLabel = ($objectDatas !== false) ? ($objectDatas[static::FIELD_NAME]) : (false);
				return $this->_objectLabel;
			}
			else {
				return false;
			}
		}

		/**
		  * Méthode courte comme getPath
		  */
		public function getLabel()
		{
			return $this->getObjectLabel();
		}

		public function getTemplateName()
		{
			if($this->objectExists()) {
				$result = $this->_DCIM->resolvToTemplate(static::OBJECT_TYPE, $this->getObjectId());
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $category
		  * @param null|string $attribute
		  * @return false|string
		  */
		public function getUserAttrField($category, $attribute = null)
		{
			if(!C\Tools::is('string&&!empty', $attribute)) {
				$attribute = $category;
				$category = 'default';
				$noCateg = true;
			}

			$attrField = $this->_DCIM->getUserAttrName($category, $attribute);
			return ($attrField === false && isset($noCateg)) ? ($attribute) : ($attrField);
		}

		/**
		  * @param string $category
		  * @param null|string $attribute
		  * @return false|string
		  */
		public function getUserAttribute($category, $attribute = null)
		{
			if($this->objectExists())
			{
				$attrField = $this->getUserAttrField($category, $attribute);

				if($attrField !== false) {
					$result = $this->_DCIM->getUserAttrById(static::OBJECT_TYPE, $this->getObjectId(), $attrField);
					return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
				}
			}

			return false;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'dcim':
				case '_DCIM': {
					return self::$_DCIM;
				}
				case 'id': {
					return $this->getObjectId();
				}
				case 'label': {
					return $this->getObjectLabel();
				}
				case 'templateName': {
					return $this->getTemplateName();
				}
				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
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
					case 'label': {
						return $this->getObjectLabel();
					}
				}
			}

			throw new Exception("Method '".$method."' does not exist", E_USER_ERROR);
		}

		/**
		  * @param string $section
		  * @param array $attributes
		  * @return false|string
		  */
		protected static function _getReportName($section, array $attributes)
		{
			if(array_key_exists($section, static::REPORT_NAMES))
			{
				$reportNames = static::REPORT_NAMES[$section];
				$attributes = implode('&', $attributes);

				if(is_string($reportNames)) {
					return $reportNames;
				}
				elseif(is_array($reportNames) && array_key_exists($attributes, $reportNames)) {
					return $reportNames[$attributes];
				}
			}

			return false;
		}

		/**
		  * @param string $reportName
		  * @param null|array $args
		  * @param false|string $fieldNameFilter
		  * @throw Addon\Dcim\Exception
		  * @return array
		  */
		protected static function _getObjectsFromReport($reportName, array $args = null, $fieldNameFilter = false)
		{
			if($args !== null)
			{
				array_walk($args, function(&$item)
				{
					if(!C\Tools::is('human', $item)) {
						$item = static::WILDCARD;
					}
				});
			}

			// @todo use _getReportName
			$results = self::$_DCIM->getReportResults(static::REPORT_NAMES[$reportName], $args);

			if(C\Tools::is('array&&count>0', $results))
			{
				if($fieldNameFilter !== false) {
					$results = array_column($results, $fieldNameFilter);
				}

				return $results;
			}
			else {
				throw new Exception("Unable to retrieve objects from report '".$reportName."'", E_USER_ERROR);
			}
		}

		/**
		  * @param Addon\Dcim\Main|Addon\Dcim\Main[] $DCIM
		  * @return bool
		  */
		public static function setDcim($DCIM)
		{
			return self::setAdapter($DCIM);
		}

		/**
		  * @param Addon\Dcim\Main|Addon\Dcim\Main[] $adapter
		  * @throw Core\Exception
		  * @return bool
		  */
		public static function setAdapter($adapter)
		{
			$status = parent::setAdapter($adapter);

			if($status) {
				self::$_DCIM = &self::$_adapter;
				self::$_aDCIM = &self::$_allAdapters;
			}

			return $status;
		}

		/**
		  * @return null|Addon\Dcim\Main|Addon\Dcim\Main[]
		  */
		public static function getDcim()
		{
			return self::getAdapter();
		}

		/**
		  * @param string $key
		  * @return bool
		  */
		public static function enableDcim($key)
		{
			return self::enableAdapter($key);
		}

		/**
		  * @return null|Addon\Dcim\Main
		  */
		public static function getDcimEnabled()
		{
			return self::getAdapterEnabled();
		}
	}