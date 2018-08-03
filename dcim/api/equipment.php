<?php
	class Dcim_Api_Equipment extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'equipment';
		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - Equipment1',
				'cabinet' => 'CW - TOOLS-CLI - Equipment4',
				'location' => 'CW - TOOLS-CLI - Equipment2',
				'subLocation' => 'CW - TOOLS-CLI - Equipment3',
		);

		const TEMPLATE_PATH = '/../../templates/';
		const TEMPLATE_EXT = 'yaml';

		protected static $_templates = array();

		protected $_locationId;
		protected $_cabinetId;

		protected $_locationApi;
		protected $_cabinetApi;

		protected $_template = null;


		public function equipmentIdIsValid($equipmentId)
		{
			return $this->objectIdIsValid($equipmentId);
		}

		public function hasEquipmentId()
		{
			return $this->hasObjectId();
		}

		public function getEquipmentId()
		{
			return $this->getObjectId();
		}

		public function equipmentExists()
		{
			return $this->objectExists();
		}

		public function getEquipmentLabel()
		{
			return $this->getObjectLabel();
		}

		public function setLocationId($locationId)
		{
			if(!$this->equipmentExists() && Tools::is('int&&>0', $locationId)) {
				$this->_locationId = $locationId;
				return $this;
			}

			return false;
		}

		public function hasLocationId()
		{
			return ($this->_locationId !== null);
		}

		public function getLocationId()
		{
			if($this->equipmentExists())
			{
				if(!$this->hasLocationId()) {
					$result = $this->_DCIM->getLocationIdByEquipmentId($this->getEquipmentId());
					$this->_locationId = ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
				}

				return $this->_locationId;
			}
			elseif($this->hasLocationId()) {
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
				$locationId = $this->getLocationId();

				if($locationId !== false) {
					$this->_locationApi = new Dcim_Api_Location($locationId);
				}
				else {
					$this->_locationApi = false;
				}
			}

			return $this->_locationApi;
		}

		public function getCabinetId()
		{
			if($this->equipmentExists()) {
				$result = $this->_DCIM->getCabinetIdByEquipmentId($this->getEquipmentId());
				return ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);
			}
			else {
				return false;
			}
		}

		public function getCabinetApi()
		{
			if($this->_cabinetApi === null)
			{
				$cabinetId = $this->getCabinetId();

				if($cabinetId !== false) {
					$this->_cabinetApi = new Dcim_Api_Cabinet($cabinetId);
				}
				else {
					$this->_cabinetApi = false;
				}
			}

			return $this->_cabinetApi;
		}

		public function getPath()
		{
			$path = "";
			$locationApi = $this->getLocationApi();
			$cabinetApi = $this->getCabinetApi();

			if($locationApi !== false) {
				$path .= $locationApi->getPath();
			}

			if($cabinetApi !== false) {
				$path .= ','.$cabinetApi->getLabel();
			}

			return $path;
		}

		public function getPosition()
		{
			if($this->equipmentExists()) {
				return $this->_DCIM->getUByEquipmentId($this->getEquipmentId());
			}
			else {
				return false;
			}
		}

		public function getSerialNumber()
		{
			return $this->getUserAttr('default', 'serialNumber');
		}

		public function getPortIds()
		{
			return $this->_DCIM->getPortIdsByEquipmentId($this->getEquipmentId());
		}

		public function getConnectedPortIds()
		{
			$conPortIds = array();
			$portIds = $this->getPortIds();

			foreach($portIds as $portId)
			{
				$result = $this->_DCIM->getConnectedPortIdByPortId($portId);
				$nbPortId = ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);

				if($nbPortId !== false) {
					$conPortIds[$portId] = $nbPortId;
				}
			}

			return $conPortIds;
		}

		public function getCableIds()
		{
			$cableIds = array();
			$portIds = $this->_DCIM->getPortIdsByEquipmentId($this->getEquipmentId());

			foreach($portIds as $portId)
			{
				$result = $this->_DCIM->getConnectedCableIdByPortId($portId);
				$cableId = ($this->_DCIM->isValidReturn($result)) ? ($result) : (false);

				if($cableId !== false) {
					$cableIds[] = $cableId;
				}
			}

			return $cableIds;
		}

		// /!\ rename est reserve
		public function renameLabel($label)
		{
			if($this->equipmentExists()) {
				$result = $this->_DCIM->updateEquipmentInfos($this->getEquipmentId(), $label);
				return ($result === true);
			}
			else {
				return false;
			}
		}

		protected function _loadTemplate($templateName)
		{
			if(!array_key_exists($templateName, self::$_templates))
			{
				$file = realpath(__DIR__.'/'.self::TEMPLATE_PATH);
				$file .= '/'.$templateName.'.'.self::TEMPLATE_EXT;

				if(file_exists($file) && is_file($file) && is_readable($file))
				{
					$yaml = yaml_parse_file($file);

					if($yaml !== false) {
						self::$_templates[$templateName] = $this->_template = $yaml;
					}
					else {
						throw new Exception("Impossible de parser le template YAML @ ".$file, E_USER_ERROR);
					}
				}
				else {
					throw new Exception("Impossible de lire le template YAML @ ".$file, E_USER_ERROR);
				}
			}
			else {
				$this->_template = self::$_templates[$templateName];
			}
		}

		protected function _reset($resetEquipmentId = false)
		{
			if($resetEquipmentId) {
				$this->_objectId = null;
			}

			$this->_objectExists = null;
			$this->_objectLabel = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'locationApi': {
					return $this->getLocationApi();
				}
				case 'cabinetApi': {
					return $this->getCabinetApi();
				}
				default: {
					return parent::__get($name);
				}
			}
		}

		public static function searchEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, $cabinetId = null, $locationId = null, $recursion = false)
		{
			$args = array('label' => $equipmentLabel, 'description' => $equipmentDesc, 'serialnumber' => $equipmentSN);

			if(Tools::is('int&&>0', $cabinetId)) {
				$reportName = 'cabinet';
				$args['cabinetid'] = $cabinetId;
			}
			elseif(Tools::is('int&&>0', $locationId)) {
				$args['locationid'] = $locationId;
				$reportName = ($recursion) ? ('subLocation') : ('location');
			}
			else {
				$reportName = 'label';
			}

			return self::$_DCIM->getReportResults(self::REPORT_NAMES[$reportName], $args);
		}
	}