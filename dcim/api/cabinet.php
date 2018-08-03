<?php
	class Dcim_Api_Cabinet extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'cabinet';
		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - Cabinet1',
				'location' => 'CW - TOOLS-CLI - Cabinet2',
				'subLocation' => 'CW - TOOLS-CLI - Cabinet3',
		);

		protected $_locationId;
		protected $_locationApi;


		public function cabinetIdIsValid($cabinetId)
		{
			return $this->objectIdIsValid($cabinetId);
		}

		public function hasCabinetId()
		{
			return $this->hasObjectId();
		}

		public function getCabinetId()
		{
			return $this->getObjectId();
		}

		public function cabinetExists()
		{
			return $this->objectExists();
		}

		public function getCabinetLabel()
		{
			return $this->getObjectLabel();
		}

		public function getLocationId()
		{
			if($this->cabinetExists())
			{
				if($this->_locationId === null)
				{
					$equipmentIds = $this->getEquipmentIds();

					if(Tools::is('array&&count>0', $equipmentIds))
					{
						$equipmentId = current($equipmentIds);

						$Dcim_Api_Equipment = new Dcim_Api_Equipment($equipmentId);
						$locationId = $Dcim_Api_Equipment->getLocationId();
						$this->_locationId = ($locationId !== false) ? ($locationId) : (false);
					}
					else {
						$this->_locationId = false;
					}
				}

				return $this->_locationId;
			}
			else {
				return false;
			}
		}

		public function getLocationApi()
		{
			if($this->_locationApi === null)
			{
				$locationId = $this->getEquipmentLocationId();

				if($locationId !== false) {
					$this->_locationApi = new Dcim_Api_Location($locationId);
				}
				else {
					$this->_locationApi = false;
				}
			}

			return $this->_locationApi;
		}

		public function getEquipmentIds()
		{
			if($this->cabinetExists()) {
				return $this->_DCIM->getEquipmentIdsByCabinetId($this->getCabinetId());
			}
			else {
				return false;
			}
		}

		public function getEquipmentId($equipmentLabel)
		{
			if($this->cabinetExists()) {
				$result = $this->_DCIM->getEquipmentIdByCabinetIdEquipmentLabel($this->getCabinetId(), $equipmentLabel);
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'locationApi': {
					return $this->getLocationApi();
				}
				default: {
					return parent::__get($name);
				}
			}
		}

		public static function searchCabinets($cabinetLabel, $locationId = null, $recursion = false)
		{
			$args = array('label' => $cabinetLabel);

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