<?php
	namespace Addon\Dcim;

	use Core as C;

	class Service_StoreContainer extends C\Addon\Service_StoreContainer
	{
		protected function _new($id)
		{
			switch($this->_type)
			{
				case Api_Location::OBJECT_TYPE: {
					return new Api_Location($id, $this->_service);
				}
				case Api_Cabinet::OBJECT_TYPE: {
					return new Api_Cabinet($id, $this->_service);
				}
				case Api_Equipment::OBJECT_TYPE: {
					return new Api_Equipment($id, $this->_service);
				}
				default: {
					return false;
				}
			}
		}
	}