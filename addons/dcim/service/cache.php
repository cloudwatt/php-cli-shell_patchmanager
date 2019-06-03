<?php
	namespace Addon\Dcim;

	use Core as C;

	class Service_Cache extends C\Addon\Service_Cache
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
		  * @return Addon\Dcim\Service_CacheContainer
		  */
		protected function _newContainer($type)
		{
			return new Service_CacheContainer($this->_service, $type, false);
		}

		/**
		  * @param string $type
		  * @return bool
		  */
		protected function _refresh($type)
		{
			switch($type)
			{
				case Api_Location::OBJECT_TYPE: {
					return $this->refreshLocations();
				}
				case Api_Cabinet::OBJECT_TYPE: {
					return $this->refreshCabinets();
				}
				case Api_Equipment::OBJECT_TYPE: {
					return $this->refreshEquipments();
				}
				default: {
					return false;
				}
			}
		}

		/**
		  * @return bool
		  */
		public function refreshLocations()
		{
			if($this->isEnabled() && $this->cleaner(Api_Location::OBJECT_TYPE))
			{
				$locations = Api_Location::searchLocations(Api_Location::WILDCARD);

				if($locations !== false)
				{
					$container = $this->getContainer(Api_Location::OBJECT_TYPE, true);
					$status = $container->registerSet(Api_Location::FIELD_ID, $locations);

					if(!$status) {
						$container->reset();
					}

					return $status;
				}
			}

			return false;
		}

		/**
		  * @return bool
		  */
		public function refreshCabinets()
		{
			if($this->isEnabled() && $this->cleaner(Api_Cabinet::OBJECT_TYPE))
			{
				$cabinets = Api_Cabinet::searchCabinets(Api_Cabinet::WILDCARD);

				if($cabinets !== false)
				{
					$container = $this->getContainer(Api_Cabinet::OBJECT_TYPE, true);
					$status = $container->registerSet(Api_Cabinet::FIELD_ID, $cabinets);

					if(!$status) {
						$container->reset();
					}

					return $status;
				}
			}

			return false;
		}

		/**
		  * @return bool
		  */
		public function refreshEquipments()
		{
			return false;
		}
	}