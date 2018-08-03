<?php
	include_once('abstract.php');

	class Service_Shell_Dcim extends Service_Shell_Abstract
	{
		protected $_OPTION_FIELDS = array(
			'location' => array(
				'fields' => array('name'),
			),
			'cabinet' => array(
				'fields' => array('name'),
			),
			'equipment' => array(
				'fields' => array('name'),
			)
		);

		protected $_LIST_TITLES = array(
			'location' => 'LOCATIONS',
			'cabinet' => 'CABINETS',
			'equipment' => 'EQUIPMENTS',
			'cable' => 'CABLES',
		);

		protected $_LIST_FIELDS = array(
			'location' => array(
				'fields' => array('name'),
				'format' => '%s'
			),
			'cabinet' => array(
				'fields' => array('name'),
				'format' => '%s'
			),
			'equipment' => array(
				'fields' => array('name', 'side', 'positionU', 'serialNumber'),
				'format' => '[%2$s] (U%3$d) %1$s {%4$s}'
			),
			'cable' => array(
				'fields' => array('port', 'name', 'nbPort', 'nbName'),
				'format' => '[%s] <-- %s --> [%s] {%s}'
			)
		);

		protected $_PRINT_FIELDS = array(
			'location' => array(
				'header' => '%s',
				'name' => PHP_EOL.'Nom: %s',
				'path' => 'Emplacement: %s',
			),
			'cabinet' => array(
				'header' => '%s',
				'name' => PHP_EOL.'Nom: %s',
				'path' => 'Emplacement: %s',
			),
			'equipment' => array(
				'header' => '%s',
				'templateName' => PHP_EOL.'Template: %s',
				'name' => 'Nom: %s',
				'description' => 'Description: %s',
				'serialNumber' => 'N° série: %s',
				'locationName' => 'Localisation: %s',
				'cabinetName' => 'Baie: %s',
				'position' => 'Position: %s / U%d',
				'path' => 'Emplacement: %s',
			),
		);


		protected function _getObjects($path = null)
		{
			$items = array(
				'Dcim_Api_Location' => array(),
				'Dcim_Api_Cabinet' => array(),
				'Dcim_Api_Equipment' => array(),
				'Dcim_Api_Cable' => array(),
			);

			$currentApi = $this->_browser($path);

			$cases = array(
				'Dcim_Api_Location' => array(
					'Dcim_Api_Location' => 'getSubLocationIds',
					'Dcim_Api_Cabinet' => 'getCabinetIds',
				),
				'Dcim_Api_Cabinet' => array(false),
				'Dcim_Api_Equipment' =>  array(false),
			);

			foreach($cases[get_class($currentApi)] as $objectClass => $objectMethod)
			{
				if($objectMethod !== false) {
					$objects = $currentApi->{$objectMethod}();
				}
				else {
					$objects = false;
				}

				if($objects !== false && count($objects) > 0)
				{
					foreach($objects as $object)
					{
						switch($objectClass)
						{
							case 'Dcim_Api_Location': {
								$Dcim_Api_Location = new Dcim_Api_Location($object);
								$objectName = $Dcim_Api_Location->getLocationLabel();
								break;
							}
							case 'Dcim_Api_Cabinet': {
								$Dcim_Api_Cabinet = new Dcim_Api_Cabinet($object);
								$objectName = $Dcim_Api_Cabinet->getCabinetLabel();
								break;
							}
						}

						$items[$objectClass][] = array('name' => $objectName);
					}
				}
				elseif($currentApi instanceof Dcim_Api_Cabinet)
				{
					$equipments = $currentApi->getEquipmentIds();

					if($equipments !== false)
					{
						foreach($equipments as $equipment)
						{
							$Dcim_Api_Equipment = new Dcim_Api_Equipment($equipment);
						
							$objectName = $Dcim_Api_Equipment->getLabel();
							$position = $Dcim_Api_Equipment->getPosition();
							$serialNumber = $Dcim_Api_Equipment->getSerialNumber();

							$items['Dcim_Api_Equipment'][] = array(
									'name' => $objectName,
									'side' => $position['side'],
									'positionU' => $position['U'],
									'serialNumber' => $serialNumber,
							);
						}
					}
				}
				elseif($currentApi instanceof Dcim_Api_Equipment)
				{
					$ports = $currentApi->getConnectedPortIds();

					if($ports !== false)
					{
						foreach($ports as $port => $nbPort)
						{
							$portApi = new Dcim_Api_Equipment_Port($port);
							$nbPortApi = new Dcim_Api_Equipment_Port($nbPort);

							$items['Dcim_Api_Cable'][] = array(
									'port' => $portApi->getLabel(),
									'name' => $portApi->cableApi->getLabel(),
									'nbPort' => $nbPortApi->getLabel(),
									'nbName' => $nbPortApi->equipmentApi->getLabel(),
							);
						}
					}
				}
			}

			/**
			  * /!\ index 0 doit toujours être le nom de l'objet ou l'identifiant (VlanID, IP)
			  */
			$compare = function($a, $b) {
				return strnatcasecmp(current($a), current($b));
			};

			usort($items['Dcim_Api_Location'], $compare);
			usort($items['Dcim_Api_Cabinet'], $compare);
			usort($items['Dcim_Api_Cable'], $compare);

			$compare = function($a, $b)
			{
				if($a['side'] !== $b['side']) {
					return (mb_strtolower($a['side']) === 'front') ? (-1) : (1);
				}
				elseif($a['positionU'] !== $b['positionU']) {
					return ($a['positionU'] < $b['positionU']) ? (1) : (-1);			// On souhaite avoir les U en haut en 1er
				}
				else {
					return strnatcasecmp($a['name'], $b['name']);
				}
			};

			usort($items['Dcim_Api_Equipment'], $compare);

			return array(
				'location' => $items['Dcim_Api_Location'],
				'cabinet' => $items['Dcim_Api_Cabinet'],
				'equipment' => $items['Dcim_Api_Equipment'],
				'cable' => $items['Dcim_Api_Cable']
			);
		}

		public function printObjectInfos(array $args, $fromCurrentPath = true)
		{
			// /!\ ls AUB --> On ne doit pas afficher AUB mais le contenu de AUB !
			/*$objectApi = end($this->_pathApi);

			switch(get_class($objectApi))
			{
				case 'Dcim_Api_Location':
					$cases = array(
						'location' => '_getLocationInfos',
						'cabinet' => '_getCabinetInfos',
						'equipment' => '_getEquipmentInfos',		// Des équipements pourraient être directement dans une location
					);
					break;
				case 'Dcim_Api_Cabinet':
					$cases = array(
						'equipment' => '_getEquipmentInfos'
					);
					break;
				default:
					$cases = array();
			}*/

			$cases = array(
				'equipment' => '_getEquipmentInfos'
			);

			$result = $this->_printObjectInfos($cases, $args, $fromCurrentPath);

			if($result !== false)
			{
				list($status, $objectType, $infos) = $result;

				if($status && $objectType === 'equipment') {
					$this->printEquipmentExtra($infos);
				}

				return $status;
			}
			else {
				return false;
			}
		}

		public function printLocationInfos(array $args, $fromCurrentPath = true, $recursion = false)
		{
			if(isset($args[0]))
			{
				$infos = $this->_getLocationInfos($args[0], $fromCurrentPath, null, $recursion);
				$status = $this->_printInformations('location', $infos);

				if($status === false) {
					Tools::e("Localisation introuvable", 'orange');
				}

				return true;
			}

			return false;
		}

		public function printCabinetInfos(array $args, $fromCurrentPath = true, $recursion = false)
		{
			if(isset($args[0]))
			{
				$infos = $this->_getCabinetInfos($args[0], $fromCurrentPath, null, $recursion);
				$status = $this->_printInformations('cabinet', $infos);

				if($status === false) {
					Tools::e("Baie introuvable", 'orange');
				}
				elseif(count($infos) === 1)
				{
					$full = false;

					if(isset($args[1]))
					{
						switch(mb_strtolower($args[1]))
						{
							case 'full':
								$full = true;
								break;
						}
					}

					$this->_MAIN->displayWaitingMsg();

					// @todo $full affiche tous les U de la baie
					$path = $infos[0]['path'].'/'.$infos[0]['name'];
					$objects = $this->_getObjects($path);
					$this->_MAIN->deleteWaitingMsg();
					$this->_printObjectsList($objects);
				}

				return true;
			}

			return false;
		}

		public function printEquipmentInfos(array $args, $fromCurrentPath = true, $recursion = false)
		{
			if(isset($args[0]))
			{
				$infos = $this->_getEquipmentInfos($args[0], $fromCurrentPath, null, $recursion);
				$status = $this->_printInformations('equipment', $infos);

				if($status === false) {
					Tools::e("Equipement introuvable", 'orange');
				}
				else {
					$this->printEquipmentExtra($infos);
				}

				return true;
			}

			return false;
		}

		protected function printEquipmentExtra(array $infos)
		{
			if(count($infos) === 1)
			{
				$this->_MAIN->displayWaitingMsg();

				$path = $infos[0]['path'].'/'.$infos[0]['name'];
				$objects = $this->_getObjects($path);
				$this->_MAIN->deleteWaitingMsg();
				$this->_printObjectsList($objects);
			}
		}

		protected function _getLocationInfos($location, $fromCurrentPath, $path = null, $recursion = false)
		{
			$items = array();
			$locations = array();

			if($fromCurrentPath)
			{
				$pathApi = $this->_browser($path, false);
				$currentApi = $this->_getLastLocationPath($pathApi);

				if($currentApi instanceof Dcim_Api_Location) {
					$locationId = $currentApi->getLocationId();
					$locations = Dcim_Api_Location::searchLocations($location, $locationId, $recursion);
				}
			}
			else {
				$locations = Dcim_Api_Location::searchLocations($location);
			}
			

			foreach($locations as $location)
			{
				//$Dcim_Api_Location = new Dcim_Api_Location($location['entity_id']);

				$item = array();
				$item['header'] = $location['name'];
				$item['name'] = $location['name'];
				$item['path'] = '/'.str_replace(',', '/', $location['path']);
				$items[] = $item;
			}

			return $items;
		}

		protected function _getCabinetInfos($cabinet, $fromCurrentPath, $path = null, $recursion = false)
		{
			$items = array();
			$cabinets = array();

			if($fromCurrentPath)
			{
				$pathApi = $this->_browser($path, false);
				$currentApi = $this->_getLastLocationPath($pathApi);

				if($currentApi instanceof Dcim_Api_Location) {
					$locationId = $currentApi->getLocationId();
					$cabinets = Dcim_Api_Cabinet::searchCabinets($cabinet, $locationId, $recursion);
				}
			}
			else {
				$cabinets = Dcim_Api_Cabinet::searchCabinets($cabinet);
			}
			

			foreach($cabinets as $cabinet)
			{
				//$Dcim_Api_Cabinet = new Dcim_Api_Cabinet($cabinet['entity_id']);

				$item = array();
				$item['header'] = $cabinet['name'];
				$item['name'] = $cabinet['name'];
				$item['path'] = '/'.str_replace(',', '/', $cabinet['path']);
				$items[] = $item;
			}

			return $items;
		}

		protected function _getEquipmentInfos($equipment, $fromCurrentPath, $path = null, $recursion = false)
		{
			$items = array();
			$equipments = array();

			if($fromCurrentPath)
			{
				$currentApi = $this->_browser($path);

				if($currentApi instanceof Dcim_Api_Cabinet) {
					$cabinetId = $currentApi->getCabinetId();
					$equipments = Dcim_Api_Equipment::searchEquipments($equipment, $equipment, $equipment, $cabinetId, null);
				}
				elseif($currentApi instanceof Dcim_Api_Location) {
					$locationId = $currentApi->getLocationId();
					$equipments = Dcim_Api_Equipment::searchEquipments($equipment, $equipment, $equipment, null, $locationId, $recursion);
				}
			}
			else {
				$equipments = Dcim_Api_Equipment::searchEquipments($equipment, $equipment, $equipment);
			}

			foreach($equipments as $equipment)
			{
				$Dcim_Api_Equipment = new Dcim_Api_Equipment($equipment['entity_id']);

				$item = array();
				$item['header'] = $Dcim_Api_Equipment->getLabel();
				$item['templateName'] = $Dcim_Api_Equipment->getTemplateName();
				$item['name'] = $Dcim_Api_Equipment->getLabel();
				//$item['description'] = $Dcim_Api_Equipment->getUserAttr(null, 'description');
				$item['serialNumber'] = $Dcim_Api_Equipment->getUserAttr('default', 'serialNumber');
				$item['locationName'] = $Dcim_Api_Equipment->locationApi->getLabel();
				$item['cabinetName'] = $Dcim_Api_Equipment->cabinetApi->getLabel();
				$item['path'] = '/'.str_replace(',', '/', $Dcim_Api_Equipment->getPath());

				$position = $Dcim_Api_Equipment->getPosition();
				$item['position'] = array($position['side'], $position['U']);

				$items[] = $item;
			}

			return $items;
		}

		public function printSearchObjects(array $args)
		{
			if(count($args) === 3)
			{			
				$time1 = microtime(true);
				$objects = $this->_searchObjects($args[0], $args[1], $args[2]);
				$time2 = microtime(true);

				$this->_MAIN->deleteWaitingMsg();

				if($objects !== false)
				{
					$this->_MAIN->setLastCmdResult($objects);
					$this->_MAIN->e(PHP_EOL.'RECHERCHE ('.round($time2-$time1).'s)', 'black', 'white', 'bold');

					if(!$this->_MAIN->isOneShotCall())
					{
						if(isset($objects['locations']))
						{
							$this->_MAIN->e(PHP_EOL);

							$counter = count($objects['locations']);
							$this->_MAIN->e(PHP_EOL.'LOCATIONS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['locations'] as $location)
								{
									$text1 = '['.$location['path'].']';
									$text1 .= Tools::t($text1, "\t", 2, 0, 8);
									$text2 = $location['header'];
									Tools::e(PHP_EOL.$text1.$text2, 'grey');
								}
							}
							else {
								Tools::e(PHP_EOL.'Aucun résultat', 'orange');
							}
						}

						if(isset($objects['cabinets']))
						{
							$this->_MAIN->e(PHP_EOL);

							$counter = count($objects['cabinets']);
							$this->_MAIN->e(PHP_EOL.'CABINETS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['cabinets'] as $cabinet)
								{
									$text1 = '['.$cabinet['path'].']';
									$text1 .= Tools::t($text1, "\t", 2, 0, 8);
									$text2 = $cabinet['header'];
									Tools::e(PHP_EOL.$text1.$text2, 'grey');
								}
							}
							else {
								Tools::e(PHP_EOL.'Aucun résultat', 'orange');
							}
						}

						if(isset($objects['equipments']))
						{
							$this->_MAIN->e(PHP_EOL);

							$counter = count($objects['equipments']);
							$this->_MAIN->e(PHP_EOL.'EQUIPMENTS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['equipments'] as $equipment)
								{
									$text1 = '['.$equipment['path'].']';
									$text1 .= Tools::t($text1, "\t", 7, 0, 8);
									$text2 = $equipment['templateName'];
									$text2 .= Tools::t($text2, "\t", 4, 0, 8);
									$text3 = $equipment['header'].' {'.$equipment['serialNumber'].'}';
									Tools::e(PHP_EOL.$text1.$text2.$text3, 'grey');
								}
							}
							else {
								Tools::e(PHP_EOL.'Aucun résultat', 'orange');
							}
						}
					}
				}
				else {
					Tools::e("Aucun résultat", 'orange');
				}

				return true;
			}

			return false;
		}

		protected function _searchObjects($path, $objectType, $objectSearch)
		{
			switch($objectType)
			{
				case 'location':
				{
					$locations = $this->_getLocationInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('locations' => $locations);
					break;
				}
				case 'cabinet':
				{
					$cabinets = $this->_getCabinetInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('cabinets' => $cabinets);
					break;
				}
				case 'equipment':
				{
					$equipments = $this->_getEquipmentInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('equipments' => $equipments);
					break;
				}
				case 'all':
				{
					$locations = $this->_searchObjects($path, 'location', $objectSearch);
					$cabinets = $this->_searchObjects($path, 'cabinet', $objectSearch);
					$equipments = $this->_searchObjects($path, 'equipment', $objectSearch);
					return array_merge($locations, $cabinets, $equipments);
					break;
				}
				default: {
					throw new Exception("Search item '".$objectType."' is unknow", E_USER_ERROR);
				}
			}
		}

		protected function _getLastLocationPath(array $pathApi)
		{
			return $this->_getLastApiPath($pathApi, 'Dcim_Api_Location');
		}
	}