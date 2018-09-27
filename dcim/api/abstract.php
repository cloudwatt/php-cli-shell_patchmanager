<?php
	abstract class Dcim_Api_Abstract
	{
		protected static $_DCIM = null;

		protected $_errorMessage = null;

		protected $_objectId = null;
		protected $_objectExists = null;		// /!\ Important null pour forcer la detection
		protected $_objectLabel = null;			// /!\ Important null pour forcer la detection

		protected $_objectDatas = null;


		public function __construct($objectId = null)
		{
			if($this->objectIdIsValid($objectId)) {
				$this->_objectId = (int) $objectId;
				$this->objectExists();
			}
			elseif($objectId !== null) {
				throw new Exception("This object ID must be an integer, '".gettype($objectId)."' is not valid", E_USER_ERROR);
			}
		}

		public function getObjectType()
		{
			return static::OBJECT_TYPE;
		}

		public function objectIdIsValid($objectId)
		{
			return Tools::is('int&&>0', $objectId);
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
			if(!$this->hasObjectId()) {
				return false;
			}
			elseif($this->_objectExists === null) {
				$this->_objectExists = ($this->getObjectLabel() !== false);
			}
			return $this->_objectExists;
		}

		public function getObjectLabel()
		{
			if(!$this->hasObjectId()) {		// /!\ Ne pas appeler equipmentExists sinon boucle infinie
				return false;
			}
			elseif($this->_objectLabel === null) {
				$result = $this->_DCIM->resolvToLabel(static::OBJECT_TYPE, $this->getObjectId());
				$this->_objectLabel = ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			return $this->_objectLabel;
		}

		public function getLabel()
		{
			return $this->getObjectLabel();
		}

		public function getTemplateName()
		{
			if($this->objectExists()) {
				$result = $this->_DCIM->getEquipmentTemplateNameByEquipmentId($this->getObjectId());
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function getUserAttr($category, $attrLabel)
		{
			if($this->objectExists())
			{
				if(Tools::is('string&&!empty', $category)) {
					$attrName = $this->_DCIM->getUserAttrName($category, $attrLabel);
				}
				else {
					$attrName = $attrLabel;
				}

				$result = $this->_DCIM->getUserAttrById(static::OBJECT_TYPE, $this->getObjectId(), $attrName);
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		protected function _getSubObjects($objects, $fieldName, $name)
		{
			if($objects !== false)
			{
				if($name !== null)
				{
					$subObjects = array();

					foreach($objects as $object)
					{
						if($object[$fieldName] === $name) {
							$subObjects[] = $object;
						}
					}

					return $subObjects;
				}
				else {
					return $objects;
				}
			}
			else {
				return false;
			}
		}

		public function __get($name)
		{
			switch(mb_strtolower($name))
			{
				case 'dcim':
				case '_dcim': {
					return self::$_DCIM;
				}

				default: {
					throw new Exception("This attribute '".$name."' does not exist", E_USER_ERROR);
				}
			}
		}

		public function getErrorMessage()
		{
			return $this->_errorMessage;
		}

		public static function setDcim(DCIM_Main $DCIM)
		{
			self::$_DCIM = $DCIM;
		}
	}