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
	}