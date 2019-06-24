<?php
	namespace Addon\Dcim;

	abstract class Api_Equipment_Abstract extends Api_Abstract
	{
		public function objectExists()
		{
			if($this->_objectExists !== null) {
				return $this->_objectExists;
			}
			elseif($this->hasObjectId()) {
				$this->_objectExists = ($this->getObjectLabel() !== false);
				return $this->_objectExists;
			}
			else {
				return false;
			}
		}

		public function getObjectLabel()
		{
			if($this->_objectLabel !== null) {		// /!\ Ne pas appeler hasObjectLabel sinon boucle infinie
				return $this->_objectLabel;
			}
			elseif($this->hasObjectId()) {			// /!\ Ne pas appeler objectExists sinon boucle infinie
				$result = $this->_adapter->resolvToLabel(static::OBJECT_TYPE, $this->getObjectId());
				$this->_objectLabel = ($this->_adapter->isValidReturn($result)) ? ($result) : (false);
				return $this->_objectLabel;
			}
			else {
				return false;
			}
		}

		protected function _getObject()
		{
			return false;
		}

		/**
		  * Return the parent equipment ID this is on
		  *
		  * @return false|int
		  */
		abstract public function getParentEquipmentId();

		/**
		  * Return the first top module equipment ID this is on
		  *
		  * @return false|int
		  */
		public function getModuleEquipmentId()
		{
			$parentEquipmentId = $this->getParentEquipmentId();
			return ($parentEquipmentId !== false) ? ($this->_getModuleEquipmentId($parentEquipmentId)) : (false);
		}

		/**
		  * Return the first top module equipment ID this is on
		  *
		  * @return int
		  */
		protected function _getModuleEquipmentId($equipmentId, $moduleId = false)
		{
			$parentEquipId = $this->_adapter->getParentEquipmentIdByEquipmentId($equipmentId);
			return ($this->_adapter->isValidReturn($parentEquipId)) ? ($this->_getModuleEquipmentId($parentEquipId, $equipmentId)) : ($moduleId);
		}
	}