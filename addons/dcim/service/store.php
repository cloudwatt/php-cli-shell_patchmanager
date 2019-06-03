<?php
	namespace Addon\Dcim;

	use Core as C;

	class Service_Store extends C\Addon\Service_Store
	{
		/**
		  * @return bool
		  */
		public function initialization()
		{
			$this->newContainer(Api_Location::OBJECT_TYPE);
			$this->newContainer(Api_Cabinet::OBJECT_TYPE);
			$this->newContainer(Api_Equipment::OBJECT_TYPE);
			return true;
		}

		/**
		  * @param string $type
		  * @return Addon\Dcim\Service_StoreContainer
		  */
		protected function _newContainer($type)
		{
			return new Service_StoreContainer($this->_service, $type, false);
		}
	}