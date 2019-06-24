<?php
	namespace Addon\Dcim;

	use ArrayObject;

	use Core as C;
	use Core\Exception as E;

	class Connector_Soap extends Connector_Abstract
	{
		const METHOD = 'SOAP';

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

		const SIDE_FRONT = 'front';
		const SIDE_REAR = 'rear';

		const FIBER_LC = 'fiber_lc';
		const FIBER_SC = 'fiber_sc';
		const FIBER_MM = 'fiber_multimode';
		const FIBER_SM = 'fiber_monomode';

		const ETHERNET_RJ45 = 'ethernet_rj45';
		const ETHERNET_CAT6 = 'ethernet_cat6';

		const CABLE_SIMPLEX = 'cable_simplex';
		const CABLE_DUPLEX = 'cable_duplex';

		/**
		  * DCIM server URL
		  * @var string
		  */
		protected $_server;

		/**
		  * Core\Soap API
		  * @var \ArrayObject
		  */
		protected $_soapAPI;

		/**
		  * @var int
		  */
		protected $_session;

		/**
		  * CSV delimiter must be identical in GUI preference
		  * DCIM > Preferences > CSV delimiter
		  *
		  * @var string
		  */
		protected $_reportCsvDelimiter;


		public function __construct(Service $service, C\Config $config, $server, $login, $password, $debug = false)
		{
			parent::__construct($service, $config, $debug);

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

			$this->_reportCsvDelimiter = $this->_config->preferences->report->csvDelimiter;
		}

		protected function _initSoapAPI($key, $server, $urn, $httpProxy, $httpsProxy)
		{
			$this->_soapAPI->{$key} = new C\Soap($server.'/'.$urn, 'DCIM_'.$key, $this->_debug);

			switch(substr($server, 0, 6))
			{
				case 'http:/':
					if(C\Tools::is('string&&!empty', $httpProxy)) {
						$useHttpProxy = $httpProxy;
					}
					break;
				case 'https:':
					if(C\Tools::is('string&&!empty', $httpsProxy)) {
						$useHttpProxy = $httpsProxy;
					}
					break;
				default:
					throw new Exception("L'adresse du serveur DCIM doit commencer par http ou https", E_USER_ERROR);
			}

			if(isset($useHttpProxy) && preg_match('#^(http(?:s)?:\/\/[^:]*)(?::([0-9]*))?$#i', $useHttpProxy, $matches))
			{
				$this->_soapAPI->{$key}->setOpt('proxy_host', $matches[1]);

				if(isset($matches[2])) {
					$this->_soapAPI->{$key}->setOpt('proxy_port', $matches[2]);
				}
			}
		}

		public function getServerId()
		{
			return $this->getServiceId();
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

		// =========== READER ============
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

		/**
		  * @param $locationId int Location ID
		  * @param $recursively bool Recursively or not
		  * @return array All sub location IDs or array of the current location ID
		  */
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
					unset($subLocationId);

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

		public function getCabinetTemplateNameByCabinetId($cabinetId)
		{
			$args = $this->getArgs(array('Cabinet', $cabinetId));					 // /!\ Cabinet avec C en majuscule!
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		/**
		  * @param $locationId int Location ID
		  * @return array All cabinet IDs or empty array
		  */
		public function getCabinetIdsByLocationId($locationId)
		{
			$cabinetIds = array();
			$cabinetLabels = $this->getCabinetLabelsByLocationId($locationId);

			foreach($cabinetLabels as $cabinetLabel)
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

		/**
		  * @param $locationId int Location ID
		  * @return array All cabinet labels or empty array
		  */
		public function getCabinetLabelsByLocationId($locationId)
		{
			$args = $this->getArgs(array($locationId));
			$cabinetLabels = $this->_soapAPI->getters->getCabinetsById($args)->return;
			$cabinetLabels = $this->explodeReturn($cabinetLabels);
			return $this->_castToString($cabinetLabels);
		}

		/**
		  * @param int $locationId
		  * @param string $cabinetLabel
		  * @return int|string Cabinet ID or string if an error occurs
		  */
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

		// @todo cast U to int
		public function getUByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$position = $this->_soapAPI->equipments->getCabinetUPosition($args)->return;
			$result = preg_match('#^U([0-9]{1,2})\[([a-z]*)\]$#i', $position, $matches);

			if($result === 1)
			{
				$matches[2] = mb_strtolower($matches[2]);

				return array(	// Compatible list() et key []
					0 => $matches[2], 1 => $matches[1],
					'side' => $matches[2], 'U' => $matches[1]
				);
			}
			else {
				return false;
			}
		}

		/**
		  * @param $cabinetId int Cabinet ID
		  * @return array All equipment labels or empty array
		  */
		public function getEquipmentLabelsByCabinetId($cabinetId)
		{
			$args = $this->getArgs(array('cabinet', $cabinetId));
			$equipmentLabels = $this->_soapAPI->getters->getEquipment($args)->return;
			$equipmentLabels = $this->explodeReturn($equipmentLabels);
			return $this->_castToString($equipmentLabels);
		}

		/**
		  * @param $cabinetId int Cabinet ID
		  * @return array All equipment IDs or empty array
		  */
		public function getEquipmentIdsByCabinetId($cabinetId)
		{
			$equipmentIds = array();
			$equipmentLabels = $this->getEquipmentLabelsByCabinetId($cabinetId);

			foreach($equipmentLabels as $index => $equipmentLabel)
			{
				if($this->isValidReturn($equipmentLabel))
				{
					// May return [ERROR] More than one Equipment found for identifier ***
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

		public function getEquipmentIdsByCableId($cableId)
		{
			$args = $this->getArgs(array($cableId));
			$equipmentIds = $this->_soapAPI->getters->getEquipmentIdByCable($args)->return;

			if(!$this->isValidReturn($equipmentIds)) {
				return array();
			}

			$equipmentIds = $this->explodeReturn($equipmentIds);
			return $this->_castToInt($equipmentIds);
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

		/**
		  * @param $equipmentId int Equipment ID
		  * @return array All slot IDs or empty array
		  */
		public function getSlotIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$slotIds = $this->_soapAPI->getters->getAllSlotsIDs($args)->return;
			$slotIds = $this->explodeReturn($slotIds);
			return $this->_castToInt($slotIds);
		}

		public function getSlotIdsByEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$resultSlotIds = array();
			$slotIds = $this->getSlotIdsByEquipmentId($equipmentId);

			foreach($slotIds as $slotId)
			{
				$_slotLabel = $this->resolvToLabel('Slot', $slotId);

				if(!$this->isValidReturn($_slotLabel)) {
					throw new Exception("Unable to retrieve slot label from id '".$slotId."': ".$_slotLabel, E_USER_ERROR);
				}
				elseif((string) $slotLabel === (string) $_slotLabel) {	// /!\ Cast to string for numeric label
					$resultSlotIds[] = $slotId;
				}
			}

			return $resultSlotIds;
		}

		public function getSlotIdByEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$args = $this->getArgs(array($equipmentId, $slotLabel));
			return $this->_soapAPI->resolver->resolveSlot($args)->return;

			/*
				$slotIds = $this->getSlotIdsByEquipmentIdSlotLabel($equipmentId, $slotLabel);
				return (count($slotIds) === 1) ? (current($slotIds)) : (false);
			*/
		}

		/**
		  * @param $equipmentId int Equipment ID
		  * @param $slotLabel string Slot label
		  * @return array All slot IDs or empty array
		  */
		public function getSlotIdsByParentEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$resultSlotIds = array();
			$slotIds = $this->getSlotIdsByEquipmentId($equipmentId);

			foreach($slotIds as $slotId)
			{
				$_slotLabel = $this->resolvToLabel('Slot', $slotId);

				if(!$this->isValidReturn($_slotLabel)) {
					throw new Exception("Unable to retrieve slot label from id '".$slotId."': ".$_slotLabel, E_USER_ERROR);
				}
				elseif((string) $slotLabel === (string) $_slotLabel) {	// /!\ Cast to string for numeric label
					$resultSlotIds[] = $slotId;
				}

				// Un slot peut contenir d'autres équipements, il faut donc les parcourir
				$equipmentId = $this->getSubEquipmentIdBySlotId($slotId);

				if($this->isValidReturn($equipmentId))
				{
					$results = $this->getSlotIdsByParentEquipmentIdSlotLabel($equipmentId, $slotLabel);

					if($results !== false) {
						$resultSlotIds = array_merge($resultSlotIds, $results);
					}
				}
			}

			return $resultSlotIds;
		}

		public function getSlotIdByParentEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$slotIds = $this->getSlotIdsByParentEquipmentIdSlotLabel($equipmentId, $slotLabel);
			return (count($slotIds) === 1) ? (current($slotIds)) : (false);
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
			$subEquipmentIds = $this->_soapAPI->getters->getEquipmentFromEquipment($args)->return;
			// /!\ Returns a list of database identifiers of the FIRST LEVEL CHILD equipment of equipment

			if($this->isValidReturn($subEquipmentIds))
			{
				$results = $subEquipmentIds;
				//$subEquipmentIds = explode(',', $subEquipmentIds);
				$subEquipmentIds = $this->explodeReturn($subEquipmentIds);
				$subEquipmentIds = $this->_castToInt($subEquipmentIds);

				foreach($subEquipmentIds as $subEquipmentId) {
					$equipmentIds = $this->getSubEquipmentIdsByEquipmentId($subEquipmentId);
					$results = array_merge($results, $equipmentIds);
				}

				return $results;
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

		public function getPortTemplateNameByPortId($portId)
		{
			$args = $this->getArgs(array('Port', $portId));					 // /!\ Port avec P en majuscule!
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		/**
		  * Return all port IDs of equipment and sub equipments
		  * Do not browse sub equipments to get port IDs
		  *
		  * @param $equipmentId int Equipment ID
		  * @return array All port IDs or empty array
		  */
		public function getPortIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$portIds = $this->_soapAPI->getters->getAllPortsIDs($args)->return;
			$portIds = $this->explodeReturn($portIds);
			return $this->_castToInt($portIds);
		}

		public function getPortIdsByEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$resultPortIds = array();
			$portIds = $this->getPortIdsByEquipmentId($equipmentId);

			foreach($portIds as $portId)
			{
				$_portLabel = $this->resolvToLabel('Port', $portId);

				if(!$this->isValidReturn($_portLabel)) {
					throw new Exception("Unable to retrieve port label from id '".$portId."': ".$_portLabel, E_USER_ERROR);
				}
				elseif((string) $portLabel === (string) $_portLabel) {	// /!\ Cast to string for numeric label
					$resultPortIds[] = $portId;
				}
			}

			return $resultPortIds;
		}

		public function getPortIdByEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$args = $this->getArgs(array($equipmentId, $portLabel));
			return $this->_soapAPI->resolver->resolvePort($args)->return;

			/*
				$portIds = $this->getPortIdsByEquipmentIdPortLabel($equipmentId, $portLabel);
				return (count($portIds) === 1) ? (current($portIds)) : (false);
			*/
		}

		/**
		  * @param $equipmentId int Equipment ID
		  * @param $portLabel string Port label
		  * @return array All port IDs or empty array
		  */
		public function getPortIdsByParentEquipmentIdPortLabel($equipmentId, $portLabel)
		{		
			return $this->getPortIdsByEquipmentIdPortLabel($equipmentId, $portLabel);

			// Un équipement peut contenir d'autres équipements, il faut donc les parcourir
			/*$equipmentIds = $this->getSubEquipmentIdsByEquipmentId($equipmentId);

			foreach($equipmentIds as $equipmentId)
			{
				if($this->isValidReturn($equipmentId)) {
					$results = $this->getPortIdsByParentEquipmentIdPortLabel($equipmentId, $portLabel);
					$resultPortIds = array_merge($resultPortIds, $results);
				}
			}

			return $resultPortIds;*/
		}

		public function getPortIdByParentEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$portIds = $this->getPortIdsByParentEquipmentIdPortLabel($equipmentId, $portLabel);
			return (count($portIds) === 1) ? (current($portIds)) : (false);
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

		public function getUserAttrsByEquipmentId($equipmentId)
		{
			return $this->getUserAttrsById('equipment', $equipmentId);
		}

		public function getUserAttrByEquipmentId($equipmentId, $userAttrName)
		{
			return $this->getUserAttrById('equipment', $equipmentId, $userAttrName);
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrBySlotId($slotId, $userAttrName)
		{
			$args = $this->getArgs(array($slotId, $userAttrName));
			return $this->_soapAPI->userAttrs->getIdSlotAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrByPortId($portId, $userAttrName)
		{
			$args = $this->getArgs(array($portId, $userAttrName));
			return $this->_soapAPI->userAttrs->getIdPortAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrsById($type, $id)
		{
			$args = $this->getArgs(array($type, $id));
			return $this->_soapAPI->userAttrs->getAllAttributeValues($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrById($type, $id, $userAttrName)
		{
			$args = $this->getArgs(array($type, $id, $userAttrName));
			return $this->_soapAPI->userAttrs->getAttribute($args)->return;
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
					$value = preg_quote($value);
					$value = str_ireplace('\\*', '.*', $value);
					$value = mb_strtolower($key).'-'.$value;
				});

				$wildCards = implode(',', $wildCards);
			}
			else {
				$wildCards = '';
			}

			$args = $this->getArgs(array($reportName, $wildCards, 'csv', false, false, ''));
			$results = $this->_soapAPI->search->reportToClient($args);

			// [ERROR] [ERROR] Incorrect format of the Wild Cards.
			if($this->isValidReturn($results))
			{
				$bytes = explode(';', $results->return);
				//$csv = implode(array_map("chr", $bytes));
				$csv = pack('C*', ...$bytes);
				$csv = str_replace("\n\000", '', $csv);
				$csv = explode("\n", $csv);

				array_walk($csv, function(&$line, $key) {
					$line = str_getcsv($line, $this->_reportCsvDelimiter);
				});

				$keys = array_shift($csv);

				array_walk($keys, function(&$key) {
					$key = mb_strtolower($key);
					$key = str_replace(' ', '_', $key);
				});

				array_walk($csv, function(&$value, $key, $keys)
				{
					if(count($keys) === count($value)) {
						$value = array_combine($keys, $value);
					}
					else {
						throw new Exception("DCIM report result is not valid, head field number (".count($keys).") do not match value field number (".count($value).")", E_USER_ERROR);
					}
				}, $keys);

				return $csv;
				
			}
			else {
				return false;
			}
		}
		// ===============================

		// =========== WRITER ============
		// ---------- Template -----------
		public function templateExists($templateName)
		{
			$args = $this->getArgs(array($templateName));
			$result = $this->_soapAPI->resolver->templateExist($args)->return;
			return ($this->isValidReturn($result) && $result === 'true');
		}
		// -------------------------------
		
		// ---------- Equipment ----------
		public function addEquipmentToCabinetId($cabinetId, $side, $positionU, $positionX, $templateName, $label = null, $description = null)
		{
			if($label === null) {
				$label = $templateName;
			}

			if($description === null) {
				$description = '';
			}

			$args = $this->getArgs(array($cabinetId, $templateName, $side, $label, $description, $positionU, $positionX));
			$result = $this->_soapAPI->equipments->addEquipmentInCabinet($args)->return;	// return equipmentId or error

			if($this->isValidReturn($result)) {
				return $result;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function addEquipmentToSlotId($slotId, $templateName, $label = null, $description = null, $side = self::SIDE_FRONT)
		{
			if($label === null) {
				$label = $templateName;
			}

			if($description === null) {
				$description = '';
			}

			$side = ($side === self::SIDE_FRONT) ? (self::SIDE_REAR) : (self::SIDE_FRONT);		// voir documentation

			$args = $this->getArgs(array($slotId, $templateName, $label, $description, $side));
			$result = $this->_soapAPI->equipments->addEquipmentInSlot($args)->return;		// return equipmentId or error

			if($this->isValidReturn($result)) {
				return $result;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function updateEquipmentInfos($equipmentId, $label, $description = null)
		{
			$args = $this->getArgs(array($equipmentId, $label, $description));
			$result = $this->_soapAPI->equipments->modifyEquipment($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function removeEquipment($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$result = $this->_soapAPI->equipments->deleteEquipmentCascade($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}
		// -------------------------------

		// ------------ Slot -------------
		public function updateSlotInfos($slotId, $label = null)
		{
			/**
			  * Modifies a slot's label. If the value of label is null then no
			  * change is made to the label.
			  */

			$args = $this->getArgs(array($slotId, $label));
			$result = $this->_soapAPI->equipments->modifySlot($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}
		// -------------------------------

		// ------------ Port -------------
		public function updatePortInfos($portId, $label = null, $color = null)
		{
			/**
			  * Modifies a port's label and/or color. If the value of label is
			  * null then no change is made to the label . If the value of color
			  * is null then no change is made to the color.
			  */

			$args = $this->getArgs(array($portId, $label, $color));
			$result = $this->_soapAPI->equipments->modifyPort($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function disconnectPort($portId)
		{
			$args = $this->getArgs(array($portId));
			$result = $this->_soapAPI->cables->disconnectPort($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}
		// -------------------------------

		// ------------ Cable ------------
		public function addCable($locationId, $templateName, $label, $description = '')
		{
			$args = $this->getArgs(array($locationId, $templateName, $label, $description));
			$result = $this->_soapAPI->cables->addCable($args)->return;						// return cableId or error

			if($this->isValidReturn($result)) {
				return $result;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function connectCable($cableId, $portId)
		{
			$args = $this->getArgs(array($cableId, $portId));
			$result = $this->_soapAPI->cables->connectCable($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function updateCableInfos($cableId, $label = null, $description = '')
		{
			$args = $this->getArgs(array($cableId, $label, $description));
			$result = $this->_soapAPI->cables->updateCable($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function removeCable($cableId)
		{
			$args = $this->getArgs(array($cableId));
			$result = $this->_soapAPI->cables->deleteCable($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}
		// -------------------------------

		// ---------- User Attrs ---------
		public function setUserAttrByEquipmentId($equipmentId, $userAttrName, $userAttrValue)
		{
			if($userAttrValue === null) {
				$userAttrValue = "";
			}
			elseif(is_array($userAttrValue)) {
				$userAttrValue = implode(self::USER_ATTR_LIST_SEPARATOR, $userAttrValue);
			}

			$args = $this->getArgs(array('Equipment', $equipmentId, $userAttrName, $userAttrValue));
			$result = $this->_soapAPI->userAttrs->setAttribute($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}

		public function setUserAttrByPortId($portId, $userAttrName, $userAttrValue)
		{
			if($userAttrValue === null) {
				$userAttrValue = "";
			}
			elseif(is_array($userAttrValue)) {
				$userAttrValue = implode(self::USER_ATTR_LIST_SEPARATOR, $userAttrValue);
			}
			
			$args = $this->getArgs(array($portId, $userAttrName, $userAttrValue));
			$result = $this->_soapAPI->userAttrs->setPortAttribute($args)->return;

			if($result === 'success') {
				return true;
			}
			else {
				throw new E\Message($result, E_USER_ERROR);
			}
		}
		// -------------------------------
		// ===============================

		// ============ TOOL =============
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
			if($return instanceof \stdClass) {
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

		public function resolvToTemplate($type, $id)
		{
			$args = $this->getArgs(array(ucfirst($type), $id));
			return $this->_soapAPI->getters->getTemplateName($args)->return;
		}

		/**
		  * @param string $category
		  * @param string $key
		  * @return false|string
		  */
		public function getUserAttrName($category, $key)
		{
			switch(mb_strtolower($category))
			{
				case 'base':
				case 'common':
				case 'default':
				{
					if($this->_config->userAttrs->default->labels->key_exists($key)) {
						return trim($this->_config->userAttrs->default->prefix.' '.$this->_config->userAttrs->default->labels[$key], ' ');
					}
					break;
				}

				case 'net':
				case 'network':
				{
					if(substr($key, -1) !== '#') {
						$key .= '#';
					}

					$key = str_pad($key, 3, '0', STR_PAD_LEFT);

					if($this->_config->userAttrs->network->labels->key_exists($key)) {
						return $key.' '.$this->_config->userAttrs->network->prefix.' '.$this->_config->userAttrs->network->labels[$key];
					}

					break;
				}

				case 'sys':
				case 'system':
				{
					if(substr($key, -1) !== '#') {
						$key .= '#';
					}

					$key = str_pad($key, 3, '0', STR_PAD_LEFT);

					if($this->_config->userAttrs->system->labels->key_exists($key)) {
						return $key.' '.$this->_config->userAttrs->system->prefix.' '.$this->_config->userAttrs->system->labels[$key];
					}

					break;
				}

				default:
				{
					if($this->_config->userAttrs->{$category}->labels->key_exists($key)) {
						return trim($this->_config->userAttrs->{$category}->prefix.' '.$this->_config->userAttrs->{$category}->labels[$key], ' ');
					}
					break;
				}
			}

			return false;
		}

		protected function _castToInt(array $datas)
		{
			foreach($datas as $index => &$data)
			{
				if($this->isValidReturn($data)) {	// /!\ Risque de variable empty
					$data = (int) $data;
				}
				else {
					unset($datas[$index]);
				}
			}
			unset($data);

			return $datas;
		}

		protected function _castToString(array $datas)
		{
			foreach($datas as $index => &$data)
			{
				if($this->isValidReturn($data)) {	// /!\ Risque de variable empty
					$data = (string) $data;
				}
				else {
					unset($datas[$index]);
				}
			}
			unset($data);

			return $datas;
		}
		// ===============================

		/**
		 * @param bool $debug
		 * @return $this
		 */
		public function debug($debug = true)
		{
			$this->_debug = (bool) $debug;

			foreach(self::SOAP_URN as $key => $urn)
			{
				if(isset($this->_soapAPI->{$key})) {
					$this->_soapAPI->{$key}->debug($this->_debug);
				}
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