<?php
	namespace Addon\Dcim;

	use ArrayObject;

	use Core as C;

	class Connector_Rest_Reader extends Connector_Abstract
	{
		//const REST_URL = 'http://patchmanager.corp.cloudwatt.com:8080/pmserver/';
		const REST_URL = 'http://84.39.32.144:8080/pmserver/';

		const REST_URN = array(
			'location' => '/rest/locations',
			'cabinet' => '/rest/cabinets',
			'equipment' => '/rest/equipment',
			'slot' => '/rest/slots',
			'port' => '/rest/ports',
			'cable' => '/rest/cables',
			'attr' => '/rest/custom-objects',
			'search' => '/rest/find',
		);


		const USER_ATTR_PREFIX__BASE = '';
		const USER_ATTR_PREFIX__NETWORK = 'Cloudwatt - Network - Switch -';
		const USER_ATTR_PREFIX__SYSTEM = 'Cloudwatt - System - Server -';

		const USER_ATTR_NAME__BASE = array(
			'Serial number',
		);

		const USER_ATTR_NAME__NETWORK = array(
			'01#' => 'Breakout Interfaces',
			'02#' => 'Link Aggregation',
			'03#' => 'MC-Link Aggregation',
			'04#' => 'Link Type',
			'05#' => 'Native VLAN',
			'06#' => 'VLAN(s)',
			'10#' => 'MC-LAG - Chassis',
			'11#' => 'MC-LAG - ICCP Link',
			'12#' => 'MC-LAG - ICL-PL Link',
			'13#' => 'MC-LAG - System ID',
			'14#' => 'MC-LAG - VLAN ID',
		);

		const USER_ATTR_NAME__SYSTEM = array(
		);

		const USER_ATTR_LIST_SEPARATOR = ':';


		protected $_restAPI = null;


		public function __construct($login, $password)
		{
			$this->_restAPI = new ArrayObject();

			foreach(self::REST_URN as $key => $urn)
			{
				$this->_restAPI->{$key} = new C\Rest('PatchManager_'.$key);

				$this->_restAPI->{$key}
						->setUrl(trim(self::REST_URL, '/').$urn)
						->setHttpAuthMethods(false)
						->setHttpAuthCredentials($login, $password);
			}
		}

		public function explodeReturn($return)
		{
			return explode(', ', trim($return, '[]'));
		}

		public function isValidReturn($return)
		{
			return (!empty($return) && $return !== null && $return !== 'null' && $return !== false && !preg_match('#ERROR|EXCEPTION#i', $return));
		}

		public function resolvToLabel($type, $id)
		{
			switch(mb_strtolower($type))
			{
				case 'site':
				case 'sites':
				case 'location':
				case 'locations':
					$result = $this->_restAPI->location->setUrn('ids/'.$id)->get(array('format' => 'api_location'));
					$result = $this->_getCallResponse($result);
					return ($result !== false) ? ($result['name']) : (false);
				case 'equipment':
					$result = $this->_restAPI->equipment->setUrn('ids/'.$id)->get(array('format' => 'api_equipment'));
					$result = $this->_getCallResponse($result);
					return ($result !== false) ? ($result['name']) : (false);
				default:
					throw new Exception("Ce type d'objet '".$type."' n'est pas supporté", E_USER_ERROR);
			}
		}

		public function isValidResponse(array $response)
		{
			return (!$this->isEmptyResponse($response) && !array_key_exists('Warning Message', $response) && !$this->isErrorResponse($response));
		}

		// ## http://84.39.32.144:8080/pmserver/rest/find?searchFor=Equipments&searchIn=127708&query=mx8
		public function isErrorResponse(array $response)
		{
			return (array_key_exists('Status', $response) && array_key_exists('Message', $response));
		}

		public function isEmptyResponse(array $response)
		{
			return (array_key_exists('No results found', $response));
		}

		protected function _getCallResponse($json)
		{
			$response = json_decode($json, true);
			return ($this->isValidResponse($response)) ? ($response) : (false);
		}

		protected function _cleanCallResponse($json, $entityIdFieldName = null, $entityIdToClean = null)
		{
			$response = json_decode($json, true);

			if($this->isValidResponse($response))
			{
				if($entityIdFieldName !== null && $entityIdToClean !== null)
				{
					foreach($response as $index => $data)
					{
						if(array_key_exists($entityIdFieldName, $data) && $data[$entityIdFieldName] === (string) $entityIdToClean) {
							unset($response[$index]);
						}
					}
				}

				return $response;
			}
			else {
				return false;
			}
		}

		protected function _reduceCallResponse($json)
		{
			$response = json_decode($json, true);

			if($this->isValidResponse($response)) {
				return $this->_reduceArray($response);
			}
			else {
				return false;
			}
		}

		protected function _reduceArray($array, $keepKeys = false)
		{
			// /!\ Utiliser current et non [0], example: {"locationId":[[{"entityId":"490652"}]],"name":[[{"name":"0"}]]}
			// {"cabinetId":"490895","name":"Z2 - K01","equipmentIds":[[{"entityId":"911687"}],[{"entityId":"911695"}],[{"entityId":"540703"}], ... ]}
			if(is_array($array))
			{
				if(count($array) > 1)
				{
					foreach($array as &$part) {
						$part = $this->_reduceArray($part, $keepKeys);
					}
					unset($part);

					return $array;
				}
				else
				{
					$part = current($array);

					if($keepKeys) {
						return $part;
					}
					else {
						return $this->_reduceArray($part, $keepKeys);
					}
				}
			}
			else {
				return $array;
			}
		}

		protected function _filterCallResponse($json, $filterFieldName, $filterTestName, $filterTestValue)
		{
			$response = json_decode($json, true);

			if($this->isValidResponse($response)) {
				return $this->_filterArray($response, $filterFieldName, $filterTestName, $filterTestValue);
			}
			else {
				return false;
			}
		}

		protected function _filterArray($array, $filterFieldName, $filterTestName, $filterTestValue)
		{
			foreach($array as $index => $data)
			{
				if(array_key_exists($filterFieldName, $data) && (
					($filterTestName !== null && C\Tools::is($filterTestName, $data[$filterFieldName])) ||
					($filterTestValue !== null && $filterTestValue === $data[$filterFieldName])
				)) {
					unset($array[$index]);
				}
			}

			return $array;
		}

		protected function _mergeCallResponse($json, array $fieldNamesToMerge, $destFieldName)
		{
			$response = json_decode($json, true);

			if($this->isValidResponse($response)) {
				return $this->_mergeArray($response, $fieldNamesToMerge, $destFieldName);
			}
			else {
				return false;
			}
		}

		protected function _mergeArray(array $array, array $fieldNamesToMerge, $destFieldName)
		{
			$counter = null;

			foreach($fieldNamesToMerge as $fieldName)
			{
				if($counter === null) {
					$counter = count($array[$fieldName]);
				}
				elseif($counter !== count($array[$fieldName])) {
					throw new Exception("Les tableaux à combiner ne comporte pas tous le même nombre d'éléments", E_USER_ERROR);
				}
			}

			$counter = 0;
			$array[$destFieldName] = array();

			while(true)
			{
				foreach($fieldNamesToMerge as $fieldNameToMerge)
				{
					if(array_key_exists($counter, $array[$fieldNameToMerge]))
					{
						$part = $array[$fieldNameToMerge][$counter];
						
						if(is_array($part))
						{
							$key = current(array_keys($part));
							$value = current(array_values($part));
							
							$array[$destFieldName][$counter][$key] = $value;
						}
					}
					else {
						break(2);
					}
				}
				
				$counter++;
			}

			return array_diff_key($array, array_flip($fieldNamesToMerge));
		}

		protected function _sliceArray(array $array, array $fieldNamesToSlice)
		{
			foreach($array as &$part) {
				$part = array_intersect_key($part, array_flip($fieldNamesToSlice));
			}
			unset($part);

			return $array;
		}

		public function getSiteId($siteName)
		{
			return $this->getLocationId($siteName);
		}

		public function getLocationId($locationName)
		{
			$result = $this->_restAPI->location->get(array('nameEq' => $locationName, 'format' => 'api_location'));
			$result = $this->_getCallResponse($result);
			return ($result !== false) ? ($result[0]['locationId']) : (false);
		}

		// ## http://84.39.32.144:8080/pmserver/rest/locations/ids/127708?format=api_location_subLocation
		// ## http://84.39.32.144:8080/pmserver/rest/locations/ids/1005653?format=api_location_subLocation
		public function getSubLocationId($locationId)
		{
			$result = $this->_restAPI->location->setUrn('ids/'.$locationId)->get(array('format' => 'api_location_subLocation'));
			$result = $this->_getCallResponse($result);

			if($result !== false)
			{
				$result = $result['locationIds'];
				$resultCount = count($result);

				if($resultCount >= 1)
				{
					if($resultCount >= 2) {
						throw new Exception("Il existe plusieurs sous emplacement pour cet emplacement", E_USER_ERROR);
					}

					return $this->getSubLocationId($result[0][0]['entityId']);
				}
			}

			return $locationId;
		}

		/*public function getSubLocationIds($locationId)
		{
			$args = $this->getArgs(array($locationId));
			$subLocationId = $this->_soapInstances['getters']->getSublocationsIdsById($args)->return;

			if(!empty($subLocationId))
			{
				$subLocationIds = explode(',', $subLocationId);

				foreach($subLocationIds as &$subLocationId) {
					$subLocationId = (array) $this->getSubLocationId($subLocationId);
				}
				unset($subLocationId);

				return $subLocationIds;
			}

			return $locationId;
		}*/

		public function getLocationIdByParentLocationIdLocationLabel($parentLocationId, $locationLabel)
		{
			//$result = $this->_restAPI->search->get(array('searchFor' => 'Locations', 'searchIn' => $parentLocationId, 'regex' => 'true', 'query' => '^('.preg_quote($locationLabel).')$', 'format' => 'api_find_location'));
			$result = $this->_restAPI->search->get(array('searchFor' => 'Locations', 'searchIn' => $parentLocationId, 'regex' => 'true', 'query' => '('.preg_quote($locationLabel).')$', 'format' => 'api_find_location'));
			$result = $this->_cleanCallResponse($result, 'locationId', $parentLocationId);

			if(count($result) > 1) {
				throw new Exception("Il existe plusieurs emplacements avec ce nom dans cet emplacement", E_USER_ERROR);
			}

			return ($result !== false) ? ($result[0]['locationId']) : (false);
		}

		// ## http://84.39.32.144:8080/pmserver/rest/find/?searchFor=Cabinets&searchIn=490673&regex=true&query=%28Z2+%5C-+G0.*%29%24&format=api_find_cabinet
		public function getCabinetIdByLocationIdCabinetLabel($locationId, $cabinetLabel)
		{
			//$result = $this->_restAPI->search->get(array('searchFor' => 'Cabinets', 'searchIn' => $locationId, 'regex' => 'true', 'query' => '^('.preg_quote($cabinetLabel).')$', 'format' => 'api_find_cabinet'));
			$result = $this->_restAPI->search->get(array('searchFor' => 'Cabinets', 'searchIn' => $locationId, 'regex' => 'true', 'query' => '('.preg_quote($cabinetLabel).')$', 'format' => 'api_find_cabinet'));
			$result = $this->_getCallResponse($result);	// /!\ Pas besoin de nettoyer car on recherche des baies dans une localisation

			if(count($result) > 1) {
				throw new Exception("Il existe plusieurs baies avec ce nom dans cet emplacement", E_USER_ERROR);
			}

			return ($result !== false) ? ($result[0]['cabinetId']) : (false);
		}

		public function getCabinetLabelsByLocationId($locationId)
		{
			$result = $this->_restAPI->search->get(array('searchFor' => 'Cabinets', 'searchIn' => $locationId, 'query' => '*', 'format' => 'api_find_cabinet'));
			$result = $this->_getCallResponse($result);	// /!\ Pas besoin de nettoyer car on recherche des baies dans une localisation

			if($result !== false)
			{
				foreach($result as &$_result) {
					$_result = $_result['name'];
				}
				unset($_result);

				return $result;
			}
			
			return false;
		}

		public function getCabinetIdByEquipmentId($equipmentId)
		{
			$result = $this->_restAPI->equipment->setUrn('ids/'.$equipmentId)->get(array('format' => 'api_equipment_cabinet'));
			$result = $this->_getCallResponse($result);
			return ($result !== false) ? ($result['cabinetId']) : (false);
		}

		// ## http://84.39.32.144:8080/pmserver/rest/equipment/ids/548287/?format=api_equipment
		public function getEquipmentTemplateNameByEquipmentId($equipmentId)
		{
			$result = $this->_restAPI->equipment->setUrn('ids/'.$equipmentId)->get(array('format' => 'api_equipment'));
			$result = $this->_getCallResponse($result);
			return ($result !== false) ? ($result['template']) : (false);
		}

		// ## http://84.39.32.144:8080/pmserver/rest/cabinets/ids/490895/?format=api_cabinet_equipment
		public function getEquipmentIdsByCabinetId($cabinetId)
		{
			$result = $this->_restAPI->search->get(array('searchFor' => 'Equipment', 'searchIn' => $cabinetId, 'query' => '*', 'format' => 'api_find_equipment'));
			$result = $this->_filterCallResponse($result, 'slotId', 'string&&!empty', null);

			if($result !== false) {
				$result = $this->_sliceArray($result, array('equipmentId'));
				$result = $this->_reduceArray($result);
				return array_values($result);
			}

			return false;
		}

		public function getEquipmentIdByCabinetIdEquipmentLabel($cabinetId, $equipmentLabel)
		{
			//$result = $this->_restAPI->search->get(array('searchFor' => 'Equipment', 'searchIn' => $cabinetId, 'query' => '^('.preg_quote($equipmentLabel).')$', 'format' => 'api_find_equipment'));
			$result = $this->_restAPI->search->get(array('searchFor' => 'Equipment', 'searchIn' => $cabinetId, 'regex' => 'true', 'query' => '('.preg_quote($equipmentLabel).')$', 'format' => 'api_find_equipment'));
			$result = $this->_filterCallResponse($result, 'slotId', 'string&&!empty', null);

			if(count($result) > 1) {
				throw new Exception("Il existe plusieurs équipements avec ce nom dans cet baie", E_USER_ERROR);
			}

			return ($result !== false) ? ($result[0]['equipmentId']) : (false);
		}

		// ## http://84.39.32.144:8080/pmserver/rest/cabinets/ids/490895/?format=api_cabinet_equipment
		public function getEquipmentIdByCabinetIdPositionU($cabinetId, $positionU)
		{
			$result = $this->getEquipmentIdsByCabinetId($cabinetId);

			if($result !== false)
			{
				foreach($result as $equipmentId) {
					list($side, $U) = $this->getUByEquipmentId($equipmentId);
					if((int) $U === (int) $positionU) return $equipmentId;
				}
			}

			return false;
		}

		// ## http://84.39.32.144:8080/pmserver/rest/equipment/ids/548287/?format=api_equipment
		public function getUByEquipmentId($equipmentId)
		{
			$result = $this->_restAPI->equipment->setUrn('ids/'.$equipmentId)->get(array('format' => 'api_equipment'));
			$result = $this->_getCallResponse($result);
			return ($result !== false) ? (array(
						0 => $result['positionSide'], 1 => $result['positionU'],
						'side' => $result['positionSide'], 'U' => $result['positionU'])
			) : (false);	// Compatibilité list() et key []
		}

		public function getEquipmentLabelsByCabinetId($cabinetId)
		{
			$result = $this->_restAPI->search->get(array('searchFor' => 'Equipment', 'searchIn' => $cabinetId, 'query' => '*', 'format' => 'api_find_equipment'));
			$result = $this->_filterCallResponse($result, 'slotId', 'string&&!empty', null);

			if($result !== false) {
				$result = $this->_sliceArray($result, array('name'));
				$result = $this->_reduceArray($result);
				return array_values($result);
			}

			return false;
		}

		public function getEquipmentIdBySlotId($slotId)
		{
			//$result = $this->_restAPI->search->get(array('searchFor' => 'Equipment', 'searchIn' => $slotId, 'query' => '*', 'format' => 'api_find_equipment'));

			$args = $this->getArgs(array($slotId));
			return $this->_soapInstances['getters']->getEquipmentFromSlot($args)->return;
		}

		public function getEquipmentIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapInstances['getters']->getEquipmentByPort($args)->return;
		}

		public function getEquipmentIdByUserAttr($userAttrName, $userAttrValue)
		{
			$args = $this->getArgs(array($userAttrName, $userAttrValue));
			return $this->_soapInstances['resolver']->resolveEquipmentByAttribute($args)->return;
		}

		public function getParentEquipmentIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapInstances['getters']->getParentEquipmentByEquipment($args)->return;
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
			return $this->_soapInstances['getters']->getTopLevelEquipmentBySlot($args)->return;
		}

		public function getTopLevelEquipmentIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapInstances['getters']->getTopLevelEquipmentByPort($args)->return;
		}

		public function getParentSlotIdByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			return $this->_soapInstances['getters']->getParentSlotByEquipment($args)->return;
		}

		public function getSlotIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$slotIds = $this->_soapInstances['getters']->getAllSlotsIDs($args)->return;
			return $this->explodeReturn($slotIds);
		}

		public function getSlotIdByEquipmentIdSlotLabel($equipmentId, $slotLabel)
		{
			$args = $this->getArgs(array($equipmentId, $slotLabel));
			return $this->_soapInstances['resolver']->resolveSlot($args)->return;
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
			return $this->_soapInstances['getters']->getTemplateName($args)->return;
		}

		public function getEquipmentIdsByParentEquipmentId($parentEquipmentId)
		{
			return $this->getSubEquipmentIdsByEquipmentId($parentEquipmentId);
		}

		public function getSubEquipmentIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$equipmentIds = $this->_soapInstances['getters']->getEquipmentFromEquipment($args)->return;
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
			return $this->_soapInstances['getters']->getEquipmentFromSlot($args)->return;
		}

		public function getPortIdsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$portIds = $this->_soapInstances['getters']->getAllPortsIDs($args)->return;
			return $this->explodeReturn($portIds);
		}

		public function getPortIdByEquipmentIdPortLabel($equipmentId, $portLabel)
		{
			$args = $this->getArgs(array($equipmentId, $portLabel));
			return $this->_soapInstances['resolver']->resolvePort($args)->return;
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
			return $this->_soapInstances['getters']->getPortByPort($args)->return;
		}

		public function getOtherSidePortIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapInstances['getters']->getOtherSidePort($args)->return;
		}

		public function getConnectedCableIdByPortId($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapInstances['getters']->getConnectedCable($args)->return;
		}

		public function getCableTemplateNameByCableId($cableId)
		{
			$args = $this->getArgs(array('Cable', $cableId)); // /!\ Cable avec C en majuscule!
			return $this->_soapInstances['getters']->getTemplateName($args)->return;
		}

		public function getUserAttrByEquipmentId($equipmentId, $userAttrName)
		{
			return $this->getUserAttrById('equipment', $equipmentId, $userAttrName);
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrById($type, $id, $userAttrName)
		{
			$args = $this->getArgs(array($type, $id, $userAttrName));
			return $this->_soapInstances['userAttrs']->getAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrsByEquipmentId($equipmentId)
		{
			$args = $this->getArgs(array('equipment', $equipmentId));
			return $this->_soapInstances['userAttrs']->getAllAttributeValues($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrsByPortId($portId)
		{
			$args = $this->getArgs(array('port', $portId));
			return $this->_soapInstances['userAttrs']->getAllAttributeValues($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrByPortId($portId, $userAttrName)
		{
			$args = $this->getArgs(array($portId, $userAttrName));
			return $this->_soapInstances['userAttrs']->getIdPortAttribute($args)->return;
		}

		// @todo Utiliser USER_ATTR_LIST_SEPARATOR
		public function getUserAttrByUserAttr($userAttrName, $userAttrValue, $returnUserAttrName)
		{
			$args = $this->getArgs(array($userAttrName, $userAttrValue, $returnUserAttrName));
			return $this->_soapInstances['userAttrs']->getAttributeByAttribute($args)->return;
		}

		public function getUserAttrValues($userAttrName)
		{
			$args = $this->getArgs(array($userAttrName));
			return $this->_soapInstances['getters']->getPossibleAttributeValues($args);
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

		public function getUserAttrName($category, $key)
		{
			switch(mb_strtolower($category))
			{
				case 'base':
				case 'default':
				{
					if(array_key_exists($key, self::USER_ATTR_NAME__BASE)) {
						return trim(self::USER_ATTR_PREFIX__BASE.' '.self::USER_ATTR_NAME__BASE[$key], ' ');
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

					if(array_key_exists($key, self::USER_ATTR_NAME__NETWORK)) {
						return $key.' '.self::USER_ATTR_PREFIX__NETWORK.' '.self::USER_ATTR_NAME__NETWORK[$key];
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

					if(array_key_exists($key, self::USER_ATTR_NAME__SYSTEM)) {
						return $key.' '.self::USER_ATTR_PREFIX__SYSTEM.' '.self::USER_ATTR_NAME__SYSTEM[$key];
					}

					break;
				}

				default: {
					throw new Exception('Category unknow', E_USER_ERROR);
				}
			}

			return false;
		}
	}