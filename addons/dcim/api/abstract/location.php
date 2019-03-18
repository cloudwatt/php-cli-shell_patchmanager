<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Api_Abstract_Location extends Api_Abstract
	{
		/**
		  * @var int
		  */
		protected $_locationId = null;

		/**
		  * @var Addon\Dcim\Api_Location
		  */
		protected $_locationApi = null;


		public function setLocationId($locationId)
		{
			if(!$this->objectExists() && C\Tools::is('int&&>0', $locationId)) {
				$this->_locationId = $locationId;
				return true;
			}
			else {
				return false;
			}
		}

		public function hasLocationId()
		{
			return ($this->getLocationId() !== false);
		}

		abstract public function getLocationId();

		public function getLocationApi()
		{
			if($this->_locationApi === null)
			{
				$locationId = $this->getLocationId();

				if($locationId !== false) {
					$this->_locationApi = new Api_Location($locationId);
				}
				else {
					$this->_locationApi = false;
				}
			}

			return $this->_locationApi;
		}

		/**
		  * @return void
		  */
		protected function _resetLocation()
		{
			$this->_locationId = null;
			$this->_locationApi = null;
		}
	}