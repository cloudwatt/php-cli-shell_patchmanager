<?php
	namespace Addon\Dcim;

	use Core as C;

	class Api_Equipment extends Api_Abstract_Location
	{
		const OBJECT_KEY = 'EQUIPMENT';
		const OBJECT_TYPE = 'equipment';
		const OBJECT_NAME = 'equipment';

		const REPORT_NAMES = array(
				'self' => 'CW - TOOLS-CLI - Equipment0',
				'label' => array(
					'label' => 'CW - TOOLS-CLI - Equipment1-0',
					'description' => 'CW - TOOLS-CLI - Equipment1-1',
					'serialNumber' => 'CW - TOOLS-CLI - Equipment1-2',
					'label&description' => 'CW - TOOLS-CLI - Equipment1-3',
					'label&serialnumber' => 'CW - TOOLS-CLI - Equipment1-4',
					'description&serialnumber' => 'CW - TOOLS-CLI - Equipment1-5',
					'label&description&serialnumber' => 'CW - TOOLS-CLI - Equipment1-6',
				),
				'cabinet' => array(
					'label' => 'CW - TOOLS-CLI - Equipment4-0',
					'description' => 'CW - TOOLS-CLI - Equipment4-1',
					'serialNumber' => 'CW - TOOLS-CLI - Equipment4-2',
					'label&description' => 'CW - TOOLS-CLI - Equipment4-3',
					'label&serialnumber' => 'CW - TOOLS-CLI - Equipment4-4',
					'description&serialnumber' => 'CW - TOOLS-CLI - Equipment4-5',
					'label&description&serialnumber' => 'CW - TOOLS-CLI - Equipment4-6',
				),
				'location' => array(
					'label' => 'CW - TOOLS-CLI - Equipment2-0',
					'description' => 'CW - TOOLS-CLI - Equipment2-1',
					'serialNumber' => 'CW - TOOLS-CLI - Equipment2-2',
					'label&description' => 'CW - TOOLS-CLI - Equipment2-3',
					'label&serialnumber' => 'CW - TOOLS-CLI - Equipment2-4',
					'description&serialnumber' => 'CW - TOOLS-CLI - Equipment2-5',
					'label&description&serialnumber' => 'CW - TOOLS-CLI - Equipment2-6',
				),
				'subLocation' => array(
					'label' => 'CW - TOOLS-CLI - Equipment3-0',
					'description' => 'CW - TOOLS-CLI - Equipment3-1',
					'serialNumber' => 'CW - TOOLS-CLI - Equipment3-2',
					'label&description' => 'CW - TOOLS-CLI - Equipment3-3',
					'label&serialnumber' => 'CW - TOOLS-CLI - Equipment3-4',
					'description&serialnumber' => 'CW - TOOLS-CLI - Equipment3-5',
					'label&description&serialnumber' => 'CW - TOOLS-CLI - Equipment3-6',
				),
		);

		const FIELD_ID = 'entity_id';
		const FIELD_NAME = 'label';
		const FIELD_DESC = 'description';

		/**
		  * Enable or disable cache feature
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_cache = array();		// DCIM server ID keys, boolean value

		/**
		  * All equipements (cache)
		  * /!\ Cache must be per type
		  *
		  * @var array
		  */
		protected static $_objects = array();	// DCIM server ID keys, array value

		/**
		  * @var int
		  */
		protected $_cabinetId = null;

		/**
		  * @var Addon\Dcim\Api_Cabinet
		  */
		protected $_cabinetApi = null;

		/**
		  * array(0 => $matches[2], 1 => $matches[1], 'side' => $matches[2], 'U' => $matches[1])
		  * @var array
		  */
		protected $_position = null;

		/**
		  * @var string
		  */
		protected $_serialNumber = null;


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

		public function setEquipmentLabel($equipmentLabel)
		{
			return $this->_setObjectLabel($equipmentLabel);
		}

		public function hasEquipmentLabel()
		{
			return $this->hasObjectLabel();
		}

		public function getEquipmentLabel()
		{
			return $this->getObjectLabel();
		}

		protected function _getObject()
		{
			if($this->_objectExists === null || $this->objectExists())
			{
				if($this->_objectDatas === null)
				{
					$args = array('equipmentid' => $this->getEquipmentId());
					$results = $this->_DCIM->getReportResults(self::REPORT_NAMES['self'], $args);

					if(count($results) === 1) {
						$this->_objectDatas = $results[0];
					}
					else {
						$this->_objectDatas = false;
					}
				}

				return $this->_objectDatas;
			}
			else {
				return false;
			}
		}

		/**
		  * Do not call hasLocationId otherwise risk of infinite loop
		  */
		public function getLocationId()
		{
			if($this->equipmentExists())
			{
				if($this->_locationId === null) {
					$result = $this->_DCIM->getLocationIdByEquipmentId($this->getEquipmentId());
					$this->_locationId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
				}

				return $this->_locationId;
			}
			elseif($this->_locationId !== null) {
				return $this->_locationId;
			}
			else {
				return false;
			}
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
					$this->_cabinetApi = new Api_Cabinet($cabinetId);
				}
				else {
					$this->_cabinetApi = false;
				}
			}

			return $this->_cabinetApi;
		}

		public function getPath($includeLabel = false, $pathSeparator = false)
		{
			$locationApi = $this->getLocationApi();
			$cabinetApi = $this->getCabinetApi();

			/**
			  * Un equipement est obligatoirement dans une moins un emplacement
			  * Mais il n'est pas forcément dans une baie, donc ne pas tester $cabinetApi immédiatement
			  */
			if($locationApi !== false)
			{
				$path = $locationApi->getPath(true, $pathSeparator);

				if($path !== false)
				{
					if($cabinetApi !== false)
					{
						$cabinetLabel = $cabinetApi->getLabel();

						if($cabinetLabel !== false) {
							$path .= self::SEPARATOR_PATH.$cabinetLabel;
						}
					}

					if($includeLabel)
					{
						if($pathSeparator === false) {
							$pathSeparator = self::SEPARATOR_PATH;
						}

						$path .= $pathSeparator.$this->getEquipmentLabel();
					}
				}

				return $path;
			}
			else {
				return false;
			}
		}

		// array(0 => $matches[2], 1 => $matches[1], 'side' => $matches[2], 'U' => $matches[1])
		public function getPosition()
		{
			if($this->equipmentExists())
			{
				if($this->_position === null) {
					$this->_position = $this->_DCIM->getUByEquipmentId($this->getEquipmentId());
				}

				return $this->_position;
			}
			else {
				return false;
			}
		}

		public function getPositionU()
		{
			return (($position = $this->getPosition()) !== false) ? ($position['U']) : (false);
		}

		public function getPositionSide()
		{
			return (($position = $this->getPosition()) !== false) ? ($position['side']) : (false);
		}

		public function getTemplateU()
		{
			$templateU = $this->_getField('template_u', 'string&&!empty');
			$templateU = substr($templateU, 0, -1);
			
			if(C\Tools::is('int&&>0', $templateU)) {
				return (int) $templateU;
			}
			elseif(C\Tools::is('float&&>0', (float) $templateU)) {
				return (int) ceil($templateU);
			}
			else {
				return false;
			}
		}

		public function getSerialNumber()
		{
			if($this->equipmentExists())
			{
				if($this->_serialNumber === null) {
					$this->_serialNumber = $this->getUserAttribute('default', 'serialNumber');
				}

				return $this->_serialNumber;
			}
			else {
				return false;
			}
		}

		/**
		  * Return the port ID of port label
		  * If no port ID is found or many port IDs are found, return false
		  * To get all port IDs of port label use getPortIds method
		  *
		  * @param $portLabel string Port label
		  * @return false|int Port ID or false if error has occured
		  */
		public function getPortId($portLabel)
		{
			if($this->equipmentExists()) {
				return $this->_DCIM->getPortIdByParentEquipmentIdPortLabel($this->getEquipmentId(), $portLabel);
			}
			else {
				return false;
			}
		}

		/**
		  * Retourne un tableau des ID des ports présents sur cet équipement
		  * ainsi que ceux présents sur les sous équipements (modules)
		  *
		  * @param $portLabel null|string Port label
		  * @return array All port IDs or empty array
		  */
		public function getPortIds($portLabel = null)
		{
			if($this->equipmentExists())
			{
				if($portLabel === null) {
					return $this->_DCIM->getPortIdsByEquipmentId($this->getEquipmentId());
					
				}
				else {
					return $this->_DCIM->getPortIdsByParentEquipmentIdPortLabel($this->getEquipmentId(), $portLabel);
				}
			}
			else {
				return array();
			}
		}

		/**
		  * Retourne un tableau des ID des ports connectés à cet équipement
		  *
		  * Les clés du tableau sont les IDs des ports connectés voisins à cet équipement
		  * Les valeurs du tableau sont les IDs des ports connectés présents sur cet équipement
		  *
		  * @return array All connected port IDs or empty array
		  */
		public function getConnectedPortIds()
		{
			$conPortIds = array();
			$portIds = $this->getPortIds();

			foreach($portIds as $portId)
			{
				$result = $this->_DCIM->getConnectedPortIdByPortId($portId);
				$nbPortId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);

				if($nbPortId !== false) {
					$conPortIds[$nbPortId] = $portId;
				}
			}

			return $conPortIds;
		}

		public function getSlotId($slotLabel)
		{
			if($this->equipmentExists()) {
				return $this->_DCIM->getSlotIdByParentEquipmentIdSlotLabel($this->getEquipmentId(), $slotLabel);
			}
			else {
				return false;
			}
		}

		/**
		  * @param $slotLabel null|string Slot label
		  * @return array All slot IDs or empty array
		  */
		public function getSlotIds($slotLabel = null)
		{
			if($this->equipmentExists())
			{
				if($slotLabel === null) {
					return $this->_DCIM->getSlotIdsByEquipmentId($this->getEquipmentId());
				}
				else {
					return $this->_DCIM->getSlotIdsByParentEquipmentIdSlotLabel($this->getEquipmentId(), $slotLabel);
				}
			}
			else {
				return array();
			}
		}

		/**
		  * @param $slotId int Slot ID
		  * @return false|int Module ID
		  */
		public function getModuleId($slotId)
		{
			if($this->equipmentExists() && C\Tools::is('int&&>0', $slotId)) {
				$result = $this->_DCIM->getEquipmentIdBySlotId($slotId);
				return ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);
			}
			else {
				return false;
			}
		}

		/**
		  * @return array All cable IDs or empty array
		  */
		public function getCableIds()
		{
			$cableIds = array();
			$portIds = $this->_DCIM->getPortIdsByEquipmentId($this->getEquipmentId());

			foreach($portIds as $portId)
			{
				$result = $this->_DCIM->getConnectedCableIdByPortId($portId);
				$cableId = ($this->_DCIM->isValidReturn($result)) ? ((int) $result) : (false);

				if($cableId !== false) {
					$cableIds[] = $cableId;
				}
			}

			return $cableIds;
		}

		/**
		  * @param $cabinetName string
		  * @param $side string
		  * @param $positionU int
		  * @param $template Core\Item
		  * @param $label string
		  * @param $description string
		  * @param $positionX int
		  * @return false|int Equipment ID or false if error has occured
		  */
		public function rack($cabinetName, $side, $positionU, C\Item $template, $label = null, $description = null, $positionX = 0)
		{
			$this->_errorMessage = null;

			if(!$this->equipmentExists())
			{
				if($this->hasLocationId())
				{
					$cabinetId = $this->_DCIM->getCabinetIdByLocationIdCabinetLabel($this->getLocationId(), $cabinetName);

					if($this->_DCIM->isValidReturn($cabinetId))
					{
						if($side === Api_Cabinet::SIDE_FRONT || $side === Api_Cabinet::SIDE_REAR)
						{
							// @todo equipment U existe?
							if(C\Tools::is('int&&>0', $positionU))
							{
								$equipmentTemplate = $template->chassis;

								if($this->_DCIM->templateExists($equipmentTemplate))
								{
									if($label === null && $this->hasEquipmentLabel()) {
										$label = $this->getEquipmentLabel();
									}

									if(C\Tools::is('string&&!empty', $label))
									{
										if(!C\Tools::is('string', $description)) {
											$description = '';
										}

										if(C\Tools::is('int&&>=0', $positionX))
										{
											try {
												$equipmentId = $this->_DCIM->addEquipmentToCabinetId($cabinetId, $side, $positionU, $positionX, $equipmentTemplate, $label, $description);
											}
											catch(E\Message $e) {
												$this->_errorMessage = "Unable to rack equipment '".$equipmentTemplate."' '".$label."' to position '".$positionU."'U in DCIM: ".$e->getMessage();
												$equipmentId = false;
											}

											if($equipmentId !== false)
											{
												$this->_hardReset(false);
												$this->_setObjectId($equipmentId);

												foreach(array('card', 'fan', 'power') as $extensionName)
												{
													if($template->key_exists($extensionName) && $template[$extensionName] instanceof C\Item && count($template[$extensionName]) > 0)
													{
														foreach($template[$extensionName] as $extensionDatas)
														{
															if($extensionDatas->key_exists('slot') && $extensionDatas->key_exists('equipment'))
															{
																try {
																	$extEquipId = $this->addModule($extensionDatas['slot'], $extensionDatas['equipment']);
																}
																catch(E\Message $e) {
																	$this->_errorMessage = "Unable to rack equipment '".$extensionDatas['equipment']."' to slot '".$extensionDatas['slot']."' in DCIM: ".$e->getMessage();
																	$extEquipId = false;
																}

																if($extEquipId !== false)
																{
																	if($extensionDatas->key_exists('ports'))
																	{
																		/**
																		  * Afin de faciliter la déclaration du template, dans celui-ci il n'est pas préciser
																		  * le type (slot ou port) de l'objet à renommer, de ce fait il ne faut pas tester le
																		  * status des commandes _renameSlot et _renamePort
																		  */
																		foreach($extensionDatas['ports'] as $currentName => $newName)
																		{
																			$slotStatus = $this->_renameSlot($extEquipId, $currentName, $newName);
																			$portStatus = $this->_renamePort($extEquipId, $currentName, $newName);

																			/*if($slotStatus === false || $portStatus === false) {
																				$this->_errorMessage = "Unable to rename slots or ports of equipment '".$extensionDatas['equipment']."' with name '".$newName."'";
																				return false;
																			}*/
																		}
																	}
																}
																else
																{
																	if(!$this->hasErrorMessage()) {
																		$this->_errorMessage = "Unable to rack equipment '".$extensionDatas['equipment']."' to slot '".$extensionDatas['slot']."'";
																	}

																	return false;
																}
															}
														}
													}
												}

												return $equipmentId;
											}
										}
										else {
											$this->_errorMessage = "Position X '".$positionX."' must be integer greater or equal to 0";
										}
									}
									else {
										$this->_errorMessage = "Label equipment is required";
									}
								}
								else {
									$this->_errorMessage = "DCIM template '".$equipmentTemplate."' is missing";
								}
							}
							else {
								$this->_errorMessage = "Position U '".$positionU."' must be integer greater to 0";
							}
						}
						else {
							$this->_errorMessage = "Side '".$side."' is not valid";
						}
					}
					else {
						$this->_errorMessage = "DCIM cabinet '".$cabinetName."' is missing ";
					}
				}
				else {
					$this->_errorMessage = "DCIM location is required";
				}
			}
			else {
				$this->_errorMessage = "DCIM equipment '".$this->label."' already exists";
			}

			return false;
		}

		/**
		  * @param $slotLabel string Slot label
		  * @param $templateName string Template name
		  * @param $moduleLabel string Module label
		  * @return false|int Module ID
		  */
		public function addModule($slotLabel, $templateName, $moduleLabel = null)
		{
			$this->_errorMessage = null;
			return $this->_addModule($this->getEquipmentId(), $slotLabel, $templateName, $moduleLabel);
		}

		protected function _addModule($equipmentId, $slotLabel, $templateName, $moduleLabel = null)
		{
			if($this->equipmentExists())
			{
				if($this->_DCIM->templateExists($templateName))
				{
					$slotIds = $this->_DCIM->getSlotIdsByParentEquipmentIdSlotLabel($equipmentId, $slotLabel);

					switch(count($slotIds))
					{
						case 0: {
							$this->_errorMessage = "No slot '".$slotLabel."' exists for this equipment '".$this->getEquipmentLabel()."'";
						}
						case 1:
						{
							$slotId = current($slotIds);
							$moduleId = $this->getModuleId($slotId);

							if($moduleId === false)
							{
								if($moduleLabel === null) {
									$moduleLabel = $slotLabel;
								}

								try{
									$equipmentId = $this->_DCIM->addEquipmentToSlotId($slotId, $templateName, $moduleLabel);
								}
								catch(E\Message $e) {
									$this->_errorMessage = "Unable to rack equipment '".$templateName."' '".$moduleLabel."' to slot '".$slotLabel."' in DCIM: ".$e->getMessage();
									$equipmentId = false;
								}

								if($equipmentId !== false) {
									return $equipmentId;
								}
							}
							else {
								$this->_errorMessage = "There is already equipment in slot '".$slotLabel."' for this equipment '".$this->getEquipmentLabel()."'";
							}

							break;
						}
						default: {
							$this->_errorMessage = "Many slot '".$slotLabel."' exists for this equipment '".$this->getEquipmentLabel()."'";
						}
					}
				}
				else {
					$this->_errorMessage = "The template '".$templateName."' does not exist in DCIM";
				}
			}
			else {
				$this->_errorMessage = "The equipment '".$this->getEquipmentLabel()."' does not exist in DCIM";
			}

			return false;
		}

		public function renameSlot($currentLabel, $newLabel)
		{
			if($this->equipmentExists()) {
				return $this->_renameSlot($this->getEquipmentId(), $currentLabel, $newLabel);
			}
			else {
				return false;
			}
		}

		protected function _renameSlot($equipmentId, $currentLabel, $newLabel)
		{

			$slotId = $this->_DCIM->getSlotIdByEquipmentIdSlotLabel($equipmentId, $currentLabel);

			if($this->_DCIM->isValidReturn($slotId))
			{
				try {
					$status = $this->_DCIM->updateSlotInfos($slotId, $newLabel);
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to rename slot from DCIM: ".$e->getMessage();
					$status = false;
				}

				return ($status) ? ($slotId) : (false);
			}
			else {
				return false;
			}
		}

		public function renamePort($currentLabel, $newLabel)
		{
			if($this->equipmentExists()) {
				return $this->_renamePort($this->getEquipmentId(), $currentLabel, $newLabel);
			}
			else {
				return false;
			}
		}

		protected function _renamePort($equipmentId, $currentLabel, $newLabel)
		{
			$portId = $this->_DCIM->getPortIdByEquipmentIdPortLabel($equipmentId, $currentLabel);

			if($this->_DCIM->isValidReturn($portId))
			{
				try {
					$status = $this->_DCIM->updatePortInfos($portId, $newLabel);
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to rename port from DCIM: ".$e->getMessage();
					$status = false;
				}

				return ($status) ? ($portId) : (false);
			}
			else {
				return false;
			}
		}

		/**
		  * @param string $label
		  * @return bool
		  */
		public function renameLabel($label)
		{
			return $this->_updateInfos($label, $this->description);
		}

		/**
		  * @param string $description
		  * @return bool
		  */
		public function setDescription($description)
		{
			return $this->_updateInfos($this->label, $description);
		}

		/**
		  * @param string $label
		  * @param string $description
		  * @return bool
		  */
		public function updateInfos($label, $description)
		{
			return $this->_updateInfos($label, $description);
		}

		/**
		  * @param string $label
		  * @param string $description
		  * @return bool
		  */
		protected function _updateInfos($label, $description)
		{
			$this->_errorMessage = null;

			if($this->equipmentExists())
			{
				try {
					$status = $this->_DCIM->updateEquipmentInfos($this->getEquipmentId(), $label, $description);
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to update equipment informations from DCIM: ".$e->getMessage();
					$status = false;
				}

				$this->refresh();
				return $status;
			}
			else {
				$this->_errorMessage = "DCIM equipment does not exist";
				return false;
			}
		}

		/**
		  * @param string $serialNumber
		  * @return bool
		  */
		public function setSerialNumber($serialNumber)
		{
			$this->_errorMessage = null;

			if($this->equipmentExists())
			{
				$snFieldName = $this->getUserAttrField('default', 'serialNumber');

				try {
					$status = $this->_DCIM->setUserAttrByEquipmentId($this->getEquipmentId(), $snFieldName, $serialNumber);
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to set equipment serial number in DCIM: ".$e->getMessage();
					$status = false;
				}

				$this->_serialNumber = null;
				return $status;
			}
			else {
				$this->_errorMessage = "DCIM equipment does not exist";
				return false;
			}
		}

		/**
		  * @return bool
		  */
		public function remove()
		{
			$this->_errorMessage = null;

			if($this->equipmentExists())
			{
				try {
					$status = $this->_DCIM->removeEquipment($this->getEquipmentId());
				}
				catch(E\Message $e) {
					$this->_errorMessage = "Unable to remove equipment from DCIM: ".$e->getMessage();
					$status = false;
				}

				$this->_hardReset();
				return $status;
			}
			else {
				$this->_errorMessage = "DCIM equipment does not exist";
				return false;
			}
		}

		/**
		  * @param bool $resetObjectId
		  * @return void
		  */
		protected function _hardReset($resetObjectId = false)
		{
			$this->_softReset($resetObjectId);
			$this->_resetAttributes();
			$this->_resetLocation();
			$this->_resetCabinet();
		}

		protected function _resetAttributes()
		{
			$this->_serialNumber = null;
		}

		protected function _resetCabinet()
		{
			$this->_cabinetId = null;
			$this->_cabinetApi = null;
			$this->_position = null;
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'description': {
					return $this->_getField(self::FIELD_DESC, 'string&&!empty');
				}
				case 'serialNumber': {
					return $this->getSerialNumber();
				}
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

		public function __call($method, $parameters = null)
		{
			if(substr($method, 0, 3) === 'get')
			{
				$name = substr($method, 3);
				$name = mb_strtolower($name);

				switch($name)
				{
					case 'description': {
						return $this->_getField(self::FIELD_DESC, 'string&&!empty');
					}
				}
			}

			return parent::__call($method, $parameters);
		}

		/**
		  * Return all equipments and sub equipments in slot matches request
		  * Can return powers, cards, modules, ...
		  *
		  * @param $equipmentLabel string Equipment label, wildcard * is allowed
		  * @param $equipmentDesc string Equipment description, wildcard * is allowed
		  * @param $equipmentSN string Equipment serial number, wildcard * is allowed
		  * @param $cabinetId int cabinet ID
		  * @param $locationId int location ID
		  * @param $recursion bool
		  * @param $firstLevel bool
		  * @return false|array
		  */
		public static function searchEquipments($equipmentLabel, $equipmentDesc, $equipmentSN, $cabinetId = null, $locationId = null, $recursion = false, $firstLevel = true)
		{
			// /!\ Ordre important pour la détection du nom du rapport
			$args = array('label' => $equipmentLabel, 'description' => $equipmentDesc, 'serialnumber' => $equipmentSN);

			$args = array_filter($args, function ($item) {
				return C\Tools::is('human', $item);
			});

			$reportAttributes = array_keys($args);

			if(C\Tools::is('int&&>0', $cabinetId)) {
				$reportSection = 'cabinet';
				$args['cabinetid'] = $cabinetId;
			}
			elseif(C\Tools::is('int&&>0', $locationId)) {
				$args['locationid'] = $locationId;
				$reportSection = ($recursion) ? ('subLocation') : ('location');
			}
			else {
				$reportSection = 'label';
			}

			$reportName = self::_getReportName($reportSection, $reportAttributes);

			if($reportName !== false)
			{
				$results = self::$_DCIM->getReportResults($reportName, $args);

				if($results !== false && $firstLevel == true)
				{
					foreach($results as $index => $result)
					{
						if($result['parent_entity_id'] !== '') {
							unset($results[$index]);
						}
					}
				}

				return $results;
			}
			else {
				return false;
			}
		}
	}