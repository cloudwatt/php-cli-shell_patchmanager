<?php
	abstract class DCIM_Connector_Soap_Read_Abstract extends DCIM_Connector_Abstract
	{
		const SOAP_URN = array(
			'session' => 'session-api?wsdl',
			'resolver' => 'resolver-api?wsdl',
			'getters' => 'getters-api?wsdl',
			'equipments' => 'equipment-api?wsdl',
			'cables' => 'cable-api?wsdl',
			'userAttrs' => 'user-attributes-api?wsdl',
			'search' => 'search-api?wsdl',
		);

		const DCIM_INVALID_SESSION_ID = -2147483648;

		const USER_ATTR_LIST_SEPARATOR = ':';
 
		protected $_config;
		protected $_server;
		protected $_soapAPI;
		protected $_session;

		protected $_debug = false;


		public function __construct($server, $login, $password, $printInfoMessages = true)
		{
			$this->_config = CONFIG::getInstance()->DCIM;

			if($printInfoMessages) {
				Tools::e(PHP_EOL."Connection SOAP au DCIM @ ".$server." veuillez patienter ... ", 'blue');
			}

			$this->_server = rtrim($server, '/');
			$this->_soapAPI = new ArrayObject();

			$httpProxy = getenv('http_proxy');
			$httpsProxy = getenv('https_proxy');

			$this->_initSoapAPI('session', $this->_server, self::SOAP_URN['session'], $httpProxy, $httpsProxy);

			$args = $this->getArgs(array($login, $password));
			$this->_session = $this->_soapAPI->session->startSession($args)->return;

			if($this->_session !== self::DCIM_INVALID_SESSION_ID)
			{
				$_urn = self::SOAP_URN;
				unset($_urn['session']);

				foreach($_urn as $key => $urn) {
					$this->_initSoapAPI($key, $this->_server, $urn, $httpProxy, $httpsProxy);
				}
			}
			else {
				throw new Exception("Unable to authenticate to DCIM", E_USER_ERROR);
			}

			if($printInfoMessages) {
				Tools::e("[OK]", 'green');
			}
		}

		protected function _initSoapAPI($key, $server, $urn, $httpProxy, $httpsProxy)
		{
			$this->_soapAPI->{$key} = new SOAP($server.'/'.$urn, 'DCIM_'.$key);
		}

		public function getJnlpUrl($version = 64)
		{
			if($version === 32 || $version === false) {
				return $this->_server.'/patchmanager-alt.jnlp';
			}
			else {
				return $this->_server.'/patchmanager.jnlp';
			}
		}

		public function getSiteId($siteName)
		{
			return $this->getLocationId($siteName);
		}

		public function getLocationId($locationName)
		{
			$args = $this->getArgs(array($locationName));
			return $this->_soapAPI->resolver->resolveLocationToId($args)->return;
		}

		public function getSubLocationId($locationId, $recursively = true)
		{
			$args = $this->getArgs(array($locationId));
			$subLocationId = $this->_soapAPI->getters->getSublocationsIdsById($args)->return;

			if(!empty($subLocationId))
			{
				if(strpos($subLocationId, ',') !== false) {
					throw new Exception("Il existe plusieurs sous emplacement pour cet emplacement", E_USER_ERROR);
				}

				return ($recursively) ? ($this->getSubLocationId($subLocationId, true)) : ($subLocationId);
			}

			return $locationId;
		}

		public function getSubLocationIds($locationId, $recursively = true)
		{
			$args = $this->getArgs(array($locationId));
			$subLocationId = $this->_soapAPI->getters->getSublocationsIdsById($args)->return;

			if(!empty($subLocationId))
			{
				$subLocationIds = explode(',', $subLocationId);

				if($recursively)
				{
					$result = array();

					foreach($subLocationIds as &$subLocationId)
					{
						if($this->isValidReturn($subLocationId)) {
							$result = array_merge($result, (array) $this->getSubLocationId($subLocationId, true));
						}
					}

					return $result;
				}
				else {
					return $subLocationIds;
				}
			}

			return array($locationId);
		}

		public function getLocationIdByParentLocationIdLocationLabel($parentLocationId, $locationLabel, $recursively = true)
		{
			$args = $this->getArgs(array($parentLocationId));
			$locationIds = $this->_soapAPI->getters->getSublocationsIdsById($args)->return;

			if($this->isValidReturn($locationIds))
			{
				$locationIds = explode(',', $locationIds);

				foreach($locationIds as $locationId)
				{
					$_locationLabel = $this->resolvToLabel('location', $locationId);

					if($locationLabel === $_locationLabel) {
						return $locationId;
					}
				}

				if($recursively)
				{
					// /!\ On test en 1er si une location possède le bon label sinon on réitère
					foreach($locationIds as $locationId)
					{
						$result = $this->getLocationIdByParentLocationIdLocationLabel($locationId, $locationLabel, true);

						if($result !== false) {
							return $result;
						}
					}
				}
			}

			return false;
		}

		public function getLocationIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapAPI->getters->getLocation($args)->return;
		}

		public function getLocationPathByLocationId($locationId)
		{
			$args = $this->getArgs(array($locationId));
			return $this->_soapAPI->getters->getLocationPath($args)->return;
		}

		public function getCabinetIdsByLocationId($locationId)
		{
			$cabinetIds = array();
			$cabinetLabels = $this->getCabinetLabelsByLocationId($locationId);

			foreach($cabinetLabels as $index => $cabinetLabel)
			{
				if($this->isValidReturn($cabinetLabel))
				{
					$result = $this->getCabinetIdByLocationIdCabinetLabel($locationId, $cabinetLabel);

					if($this->isValidReturn($result)) {
						$cabinetIds[] = $result;
					}
				}
			}

			return $cabinetIds;
		}

		public function getCabinetLabelsByLocationId($locationId)
		{
			$args = $this->getArgs(array($locationId));
			$cabinetLabels = $this->_soapAPI->getters->getCabinetsById($args)->return;
			return $this->explodeReturn($cabinetLabels);
		}

		public function getCabinetIdByLocationIdCabinetLabel($locationId, $cabinetLabel)
		{
			$args = $this->getArgs(array($cabinetLabel, $locationId));
			return $this->_soapAPI->resolver->resolveCabinetToId($args)->return;
		}

		public function getCabinetIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapAPI->getters->getCabinetByEquipment($args)->return;
		}

		public function getEquipmentTemplateNameByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array('Equipment', $equipmentId));					 // /!\ Equipment avec E en majuscule!
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		public function getEquipmentIdByCabinetIdPositionU($cabinetId, $positionU)
		{
			$equipmentIds = $this->getEquipmentIdsByCabinetId($cabinetId);

			foreach($equipmentIds as $equipmentId) {
				list($side, $U) = $this->getUByEquipmentId($equipmentId);
				if((int) $U === (int) $positionU) return $equipmentId;
			}

			return false;
		}

		public function getUByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$position = $this->_soapAPI->equipments->getCabinetUPosition($args)->return;
			$result = preg_match('#^U([0-9]{1,2})\[([a-z]*)\]$#i', $position, $matches);
			return ($result === 1) ? (array(0 => $matches[2], 1 => $matches[1], 'side' => $matches[2], 'U' => $matches[1])) : (false);	// Compatible list() et key []
		}

		public function getEquipmentLabelsByCabinetId($cabinetId)
		{
			$args = $this->getArgs(array('cabinet', $cabinetId));
			$equipmentLabels = $this->_soapAPI->getters->getEquipment($args)->return;
			return $this->explodeReturn($equipmentLabels);
		}

		public function getEquipmentIdsByCabinetId($cabinetId)
		{
			$equipmentIds = array();
			$equipmentLabels = $this->getEquipmentLabelsByCabinetId($cabinetId);

			foreach($equipmentLabels as $index => $equipmentLabel)
			{
				if($this->isValidReturn($equipmentLabel))
				{
					$result = $this->getEquipmentIdByCabinetIdEquipmentLabel($cabinetId, $equipmentLabel);

					if($this->isValidReturn($result)) {
						$equipmentIds[] = $result;
					}
				}
			}

			return $equipmentIds;
		}

		public function getEquipmentIdByLocationIdEquipmentLabel($locationId, $equipmentLabel)
		{
			$args = $this->getArgs(array($equipmentLabel, 'location', $locationId));
			return $this->_soapAPI->resolver->resolveEquipmentToId2($args)->return;
		}

		public function getEquipmentIdByCabinetIdEquipmentLabel($cabinetId, $equipmentLabel)
		{
			$args = $this->getArgs(array($equipmentLabel, 'cabinet', $cabinetId));
			return $this->_soapAPI->resolver->resolveEquipmentToId2($args)->return;
		}

		public function getEquipmentIdBySlotId($slotId)
		{
			$args = $this->getArgs(array($slotId));
			return $this->_soapAPI->getters->getEquipmentFromSlot($args)->return;
		}

		public function getEquipmentIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapAPI->getters->getEquipmentByPort($args)->return;
		}

		public function getEquipmentIdByUserAttr($userAttrName, $userAttrValue)
		{
			$args = $this->getArgs(array($userAttrName, $userAttrValue));
			return $this->_soapAPI->resolver->resolveEquipmentByAttribute($args)->return;
		}

		public function getParentEquipmentIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapAPI->getters->getParentEquipmentByEquipment($args)->return;
		}

		public function getParentEquipmentIdBySlotId($slotId)
		{
			return $this->getEquipmentIdBySlotId($slotId);
		}

		public function getParentEquipmentIdByPortId($portId)
		{
			return $this->getEquipmentIdByPortId($portId);
		}

		public function getTopEquipmentIdBySlotId($slotId)
		{
			return $this->getTopLevelEquipmentIdBySlotId($slotId);
		}

		public function getTopEquipmentIdByPortId($portId)
		{
			return $this->getTopLevelEquipmentIdByPortId($portId);
		}

		public function getTopLevelEquipmentIdBySlotId($slotId)
		{
			$args = $this->getArgs(array($slotId));
			return $this->_soapAPI->getters->getTopLevelEquipmentBySlot($args)->return;
		}

		public function getTopLevelEquipmentIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapAPI->getters->getTopLevelEquipmentByPort($args)->return;
		}

		public function getParentSlotIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapAPI->getters->getParentSlotByEquipment($args)->return;
		}

		public function getSlotIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$slotIds = $this->_soapAPI->getters->getAllSlotsIDs($args)->return;
			return $this->explodeReturn($slotIds);
		}

		public function getSlotIdByEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$args = $this->getArgs(array($equipmentId, $slotLabel));
			return $this->_soapAPI->resolver->resolveSlot($args)->return;
		}

		public function getSlotIdByParentEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$resultSlotIds = array();
			$slotIds = $this->getSlotIdsByEquipmentId($equipmentId);

			foreach($slotIds as $slotId)
			{
				if($this->isValidReturn($slotId))	// /!\ Important, risque de variable empty
				{
					$_slotLabel = $this->resolvToLabel('Slot', $slotId);

					if(!$this->isValidReturn($_slotLabel)) {
						throw new Exception("Une erreur s'est produit pendant la convertion ID vers label", E_USER_ERROR);
					}
					elseif((string) $slotLabel === (string) $_slotLabel) {	// /!\ Cast to string for numeric label
						$resultSlotIds[] = $slotId;
					}
					else
					{
						$equipmentId = $this->getSubEquipmentIdBySlotId($slotId);

						if($this->isValidReturn($equipmentId))
						{
							$result = $this->getSlotIdByParentEquipmentIdSlotLabel($equipmentId, $slotLabel);

							if($result !== false) {
								$resultSlotIds[] = $result;
							}
						}
					}
				}
			}

			$counter = count($resultSlotIds);

			if($counter > 1) {
				throw new Exception("Il existe plusieurs slot correspondant à ce nom", E_USER_ERROR);
			}
			elseif($counter === 1) {
				return $resultSlotIds[0];
			}
			else {
				return false;
			}
		}

		public function getSlotTemplateNameBySlotId($slotId)
		{
			$args = $this->getArgs(array('Slot', $slotId)); // /!\ Slot avec S en majuscule!
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		public function getEquipmentIdsByParentEquipmentId($parentEquipmentId)
		{
			return $this->getSubEquipmentIdsByEquipmentId($parentEquipmentId);
		}

		public function getSubEquipmentIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$equipmentIds = $this->_soapAPI->getters->getEquipmentFromEquipment($args)->return;
			// /!\ Returns a list of database identifiers of the FIRST LEVEL CHILD equipment of equipment

			if($this->isValidReturn($equipmentIds))
			{
				$_equipmentIds = array();
				$equipmentIds = explode(',', $equipmentIds);

				foreach($equipmentIds as $equipmentId) {
					$temp = $this->getSubEquipmentIdsByEquipmentId($equipmentId);
					$_equipmentIds = array_merge($_equipmentIds, $temp);
				}

				return array_merge($equipmentIds, $_equipmentIds);
			}
			else {
				return array();
			}
		}

		public function getEquipmentIdByParentEquipmentIdEquipmentLabel($parentEquipmentId, $equipmentLabel)
		{
			$equipmentIds = $this->getEquipmentIdsByParentEquipmentId($parentEquipmentId);

			foreach($equipmentIds as $equipmentId) {
				$_equipmentLabel = $this->resolvToLabel('Equipment', $equipmentId);
				if($equipmentLabel === $_equipmentLabel) return $equipmentId;
			}

			return false;
		}

		public function getEquipmentIdByParentSlotId($parentSlotId)
		{
			return $this->getSubEquipmentIdBySlotId($parentSlotId);
		}

		public function getSubEquipmentIdBySlotId($slotId)
		{
			$args = $this->getArgs(array($slotId));
			return $this->_soapAPI->getters->getEquipmentFromSlot($args)->return;
		}

		public function getPortIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$portIds = $this->_soapAPI->getters->getAllPortsIDs($args)->return;
			return $this->explodeReturn($portIds);
		}

		public function getPortIdByEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$args = $this->getArgs(array($equipmentId, $portLabel));
			return $this->_soapAPI->resolver->resolvePort($args)->return;
		}

		public function getPortIdByParentEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$resultPortIds = array();
			$equipmentIds = $this->getSubEquipmentIdsByEquipmentId($equipmentId);
			array_unshift($equipmentIds, $equipmentId);

			foreach($equipmentIds as $equipmentId)
			{
				if($this->isValidReturn($equipmentId))	// /!\ Important, risque de variable empty
				{
					$portId = $this->getPortIdByEquipmentIdPortLabel($equipmentId, $portLabel);

					if($this->isValidReturn($portId)) {
						$resultPortIds[] = $portId;
					}
				}
			}

			$counter = count($resultPortIds);

			if($counter > 1) {
				throw new Exception("Il existe plusieurs ports correspondant à ce nom", E_USER_ERROR);
			}
			elseif($counter === 1) {
				return $resultPortIds[0];
			}
			else {
				return false;
			}
		}

		public function getConnectedPortIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapAPI->getters->getPortByPort($args)->return;
		}

		public function getOtherSidePortIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapAPI->getters->getOtherSidePort($args)->return;
		}

		public function getConnectedCableIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapAPI->getters->getConnectedCable($args)->return;
		}

		public function getCableTemplateNameByCableId($cableId)
		{
			$args = $this->getArgs(array('Cable', $cableId)); // /!\ Cable avec C en majuscule!
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		public function getUserAttrByEquipmentId($equipmentId, $userAttrName)
		{
			return $this->getUserAttrById('equipment', $equipmentId, $userAttrName);
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrById($type, $id, $userAttrName)
		{
			$args = $this->getArgs(array($type, $id, $userAttrName));
			return $this->_soapAPI->userAttrs->getAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array('equipment', $equipmentId));
			return $this->_soapAPI->userAttrs->getAllAttributeValues($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrsByPortId($portId)
		{
			$args = $this->getArgs(array('port', $portId));
			return $this->_soapAPI->userAttrs->getAllAttributeValues($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrByPortId($portId, $userAttrName)
		{
			$args = $this->getArgs(array($portId, $userAttrName));
			return $this->_soapAPI->userAttrs->getIdPortAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrByUserAttr($userAttrName, $userAttrValue, $returnUserAttrName)
		{
			$args = $this->getArgs(array($userAttrName, $userAttrValue, $returnUserAttrName));
			return $this->_soapAPI->userAttrs->getAttributeByAttribute($args)->return;
		}

		public function getUserAttrValues($userAttrName)
		{
			$args = $this->getArgs(array($userAttrName));
			return $this->_soapAPI->getters->getPossibleAttributeValues($args);
		}

		public function getReportResults($reportName, array $wildCards = null)
		{
			if($wildCards !== null)
			{
				array_walk($wildCards, function(&$value, $key) {
					$value = preg_replace('#([^.])\*#i', '$1.*', $value);
					$value = mb_strtolower($key).'-'.$value;
				});

				$wildCards = implode(',', $wildCards);
			}
			else {
				$wildCards = '';
			}

			$args = $this->getArgs(array($reportName, $wildCards, 'csv', false, false, ''));
			$results = $this->_soapAPI->search->reportToClient($args);

			if($this->isValidReturn($results)) {
				$bytes = explode(';', $results->return);
				//$csv = implode(array_map("chr", $bytes));
				$csv = pack('C*', ...$bytes);
				$csv = str_replace("\n\000", '', $csv);
				$csv = explode("\n", $csv);
				array_walk($csv, function(&$line, $key) {
					$line = str_getcsv($line, ';');
				});
				$keys = array_shift($csv);
				array_walk($keys, function(&$key) {
					$key = mb_strtolower($key);
					$key = str_replace(' ', '_', $key);
				});
				array_walk($csv, function(&$value, $key, $keys) {
					$value = array_combine($keys, $value);
				}, $keys);
				return $csv;
				
			}
			else {
				return false;
			}
		}

		public function getArgs(array $args)
		{
			$_args = new ArrayObject();

			if($this->_session !== null) {
				$_args->arg0 = $this->_session;
				$j=1;
			} else {
				$j=0;
			}

			for($i=0;$i<count($args);$i++) {
				$_args->{"arg".$j} = $args[$i];
				$j++;
			}

			return $_args;
		}

		public function explodeReturn($return)
		{
			return explode(', ', trim($return, '[]'));
		}

		public function isValidReturn($return)
		{
			/*
			class stdClass#17 (1) {
				public $return =>
				string(83) "[ERROR] java.io.FileNotFoundException: ./files/search/. (No such file or directory)"
			}
			*/
			if($return instanceof stdClass) {
				$return = $return->return;
			}

			// /!\ Ne pas utiliser empty: var_dump(empty(0)) --> bool(true), un étage peut être 0 par exemple
			return ($return !== "" && $return !== null && $return !== 'null' && $return !== false && !preg_match('#ERROR|EXCEPTION#i', $return));
		}

		public function resolvToLabel($type, $id)
		{
			$args = $this->getArgs(array($type, $id));
			return $this->_soapAPI->resolver->resolveToLabel($args)->return;
		}

		public function getUserAttrName($category, $key)
		{
			switch(mb_strtolower($category))
			{
				case 'base':
				case 'common':
				case 'default':
				{
					if(array_key_exists($key, $this->_config->userAttrs->default->labels)) {
						return trim($this->_config->userAttrs->default->prefix.' '.$this->_config->userAttrs->default->labels[$key], ' ');
					}
					break;
				}

				default:
				{
					if(array_key_exists($key, $this->_config->userAttrs->{$category}->labels)) {
						return trim($this->_config->userAttrs->{$category}->prefix.' '.$this->_config->userAttrs->{$category}->labels[$key], ' ');
					}
					break;
				}
			}

			return false;
		}

		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;

			foreach(self::SOAP_URN as $key => $urn) {
				$this->_soapAPI->{$key}->debug($this->_debug);
			}

			return $this;
		}

		public function close()
		{
			$this->_soapAPI->session->closeSession($this->_session);
			return $this;
		}

		public function __destruct()
		{
			$this->close();
		}
	}