<?php
	namespace App\Dcim;

	use Core as C;

	use Cli as Cli;

	use Addon\Dcim;

	class Shell_Program_Dcim extends Cli\Shell\Program\Browser
	{
		const OBJECT_NAMES = array(
				Dcim\Api_Location::OBJECT_TYPE => Dcim\Api_Location::OBJECT_NAME,
				Dcim\Api_Cabinet::OBJECT_TYPE => Dcim\Api_Cabinet::OBJECT_NAME,
				Dcim\Api_Equipment::OBJECT_TYPE => Dcim\Api_Equipment::OBJECT_NAME,
				Dcim\Api_Cable::OBJECT_TYPE => Dcim\Api_Cable::OBJECT_NAME,
		);

		const RESULT_KEYS = array(
				Dcim\Api_Location::OBJECT_TYPE => 'locations',
				Dcim\Api_Cabinet::OBJECT_TYPE => 'cabinets',
				Dcim\Api_Equipment::OBJECT_TYPE => 'equipments',
				Dcim\Api_Cable::OBJECT_TYPE => 'cables',
		);

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
				'fields' => false,
				'format' => false
			),
			'cabinet' => array(
				'fields' => false,
				'format' => false
			),
			'equipment' => array(
				'fields' => false,
				'format' => false
			),
			'cable' => array(
				'fields' => false,
				'format' => false
			)
		);

		protected $_PRINT_TITLES = array(
			'location' => 'LOCATIONS',
			'cabinet' => 'CABINETS',
			'equipment' => 'EQUIPMENTS',
			'cable' => 'CABLES',
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

		/**
		  * @var Addon\Dcim\Service_Store
		  */
		protected $_addonStore;

		/**
		  * @var bool
		  */
		protected $_searchfromCurrentPath = true;


		public function __construct(Cli\Shell\Main $SHELL)
		{
			parent::__construct($SHELL);

			$this->_addonStore = Dcim\Orchestrator::getInstance()->service->store;
		}

		// SHOW
		// --------------------------------------------------
		protected function _getView(array $args)
		{
			$args = array_reverse($args);

			if(isset($args[1]) && $args[1] === '|') {
				return $args[0];
			}
			else {
				return false;
			}
		}

		public function listLocations(array $args)
		{
			return $this->_listObjects(Dcim\Api_Location::OBJECT_TYPE, $args);
		}

		public function showLocations(array $args)
		{
			return $this->_showObjects(Dcim\Api_Location::OBJECT_TYPE, $args);
		}

		public function listCabinets(array $args)
		{
			return $this->_listObjects(Dcim\Api_Cabinet::OBJECT_TYPE, $args);
		}

		public function showCabinets(array $args)
		{
			return $this->_showObjects(Dcim\Api_Cabinet::OBJECT_TYPE, $args);
		}

		protected function _printCabinetExtra(array $cabinets, array $args = null)
		{
			if(count($cabinets) === 1)
			{
				$this->_SHELL->displayWaitingMsg(true, false, 'searching DCIM equipments');

				$path = $cabinets[0]['path'].'/'.$cabinets[0]['name'];
				$objects = $this->_getObjects($path, $args);
				$this->_printObjectsList($objects);
				
				$this->_RESULTS[self::RESULT_KEYS[Dcim\Api_Equipment::OBJECT_TYPE]] = $objects['equipment'];
			}
		}

		public function listEquipments(array $args)
		{
			return $this->_listObjects(Dcim\Api_Equipment::OBJECT_TYPE, $args);
		}

		public function showEquipments(array $args)
		{
			return $this->_showObjects(Dcim\Api_Equipment::OBJECT_TYPE, $args);
		}

		protected function _printEquipmentExtra(array $equipments, array $args = null)
		{
			if(count($equipments) === 1)
			{
				$this->_SHELL->displayWaitingMsg(true, false, 'searching DCIM cables');

				$path = $equipments[0]['path'].'/'.$equipments[0]['name'];
				$objects = $this->_getObjects($path, $args);
				$this->_printObjectsList($objects);

				$this->_RESULTS[self::RESULT_KEYS[Dcim\Api_Cable::OBJECT_TYPE]] = $objects['cable'];
			}
		}

		protected function _listObjects($type, array $args)
		{
			$view = $this->_getView($args);

			switch($view)
			{
				case 'form': {
					$status = $this->_printObjectForm($type, $args, true, false);
					break;
				}
				default: {
					$status = $this->_printObjectList($type, $args, true, false);
				}
			}

			return ($status !== false);
		}

		protected function _showObjects($type, array $args)
		{
			$view = $this->_getView($args);

			switch($view)
			{
				case 'form': {
					$status = $this->_printObjectForm($type, $args, false, false);
					break;
				}
				default: {
					$status = $this->_printObjectList($type, $args, false, false);
				}
			}

			return ($status !== false);
		}

		protected function _printObjectForm($type, array $args, $fromCurrentPath = true, $recursion = false)
		{
			if(isset($args[0]))
			{
				$items = $this->_getItems($type, $args[0], $fromCurrentPath, $recursion, 'form');
				$status = $this->_printInformations($type, $items);
				$objectName = self::OBJECT_NAMES[$type];
				$resultKey = self::RESULT_KEYS[$type];

				if($status === false) {
					$this->_SHELL->error("Objet '".$objectName."' introuvable", 'orange');
				}
				else {
					$this->_printObjectExtra($type, $items, $args);
				}

				$this->_RESULTS[$resultKey] = $items;
				return $items;
			}

			return false;
		}

		protected function _printObjectList($type, array $args, $fromCurrentPath = true, $recursion = false)
		{
			if(isset($args[0]))
			{
				$items = $this->_getItems($type, $args[0], $fromCurrentPath, $recursion, 'list');
				$objectName = self::OBJECT_NAMES[$type];
				$resultKey = self::RESULT_KEYS[$type];

				if(count($items) > 0)
				{
					if(!$this->_SHELL->isOneShotCall())
					{
						switch($type)
						{
							case Dcim\Api_Equipment::OBJECT_TYPE:
							{
								$equipments = $items;
								$items = array();
								$extra = array();

								foreach($equipments as $Dcim_Api_Equipment)
								{
									$position = $Dcim_Api_Equipment->getPosition();

									$items[] = array(
										'label' => $Dcim_Api_Equipment->getLabel(),
										'templateName' => $Dcim_Api_Equipment->getTemplateName(),
										'locationPath' => DIRECTORY_SEPARATOR.$Dcim_Api_Equipment->locationApi->getPath(true, DIRECTORY_SEPARATOR),
										'cabinetLabel' => $Dcim_Api_Equipment->cabinetApi->getLabel(),
										'positionSide' => $position['side'],
										'positionU' => $position['U'],
										'serialNumber' => $Dcim_Api_Equipment->getSerialNumber()
									);

									$extra[] = array(
										'name' => $Dcim_Api_Equipment->getLabel(),
										'path' => DIRECTORY_SEPARATOR.$Dcim_Api_Equipment->getPath(false, DIRECTORY_SEPARATOR),
									);
								}
								break;
							}
							//case Dcim\Api_Location::OBJECT_TYPE:
							//case Dcim\Api_Cabinet::OBJECT_TYPE:
							default:
							{
								foreach($items as &$item) {
									unset($item['header']);
								}
								unset($item);
								
								$extra = $items;
							}
						}
					}

					$this->_printObjectsList(array($type => $items));
					$this->_printObjectExtra($type, $extra, $args);
				}
				else {
					$this->_SHELL->error("Aucun objet '".$objectName."' n'a été trouvé", 'orange');
				}

				$this->_RESULTS[$resultKey] = $items;
				return $items;
			}

			return false;
		}

		protected function _getItems($type, $name, $fromCurrentPath, $recursion, $return)
		{
			$cases = array(
				Dcim\Api_Location::OBJECT_TYPE => array(
					'list' => '_getLocationInfos',
					'form' => '_getLocationInfos',
				),
				Dcim\Api_Cabinet::OBJECT_TYPE => array(
					'list' => '_getCabinetInfos',
					'form' => '_getCabinetInfos',
				),
				Dcim\Api_Equipment::OBJECT_TYPE => array(
					'list' => '_getEquipmentObjects',
					'form' => '_getEquipmentInfos',
				),
			);

			if(array_key_exists($type, $cases)) {
				$callable = array($this, $cases[$type][$return]);
				return call_user_func($callable, $name, $fromCurrentPath, null, $recursion);
			}
			else {
				throw new Exception("Unknown type '".$type."'", E_USER_ERROR);
			}
		}

		protected function _printObjectExtra($type, array $items, array $args)
		{
			switch($type)
			{
				case Dcim\Api_Location::OBJECT_TYPE: {
					break;
				}
				case Dcim\Api_Cabinet::OBJECT_TYPE: {
					$this->_printCabinetExtra($items, $args);
					break;
				}
				case Dcim\Api_Equipment::OBJECT_TYPE: {
					$this->_printEquipmentExtra($items, $args);
					break;
				}
				default: {
					throw new Exception("Unknown type '".$type."'", E_USER_ERROR);
				}
			}
		}
		// --------------------------------------------------

		// OBJECT > SEARCH
		// --------------------------------------------------
		public function printSearchObjects(array $args)
		{
			if(count($args) === 3)
			{			
				$time1 = microtime(true);
				$objects = $this->_searchObjects($args[0], $args[1], $args[2]);
				$time2 = microtime(true);

				if($objects !== false)
				{
					$this->_RESULTS->append($objects);
					$this->_SHELL->print('RECHERCHE ('.round($time2-$time1).'s)', 'black', 'white', 'bold');

					if(!$this->_SHELL->isOneShotCall())
					{
						if(isset($objects['locations']))
						{
							$counter = count($objects['locations']);
							$this->_SHELL->EOL()->print('LOCATIONS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['locations'] as &$location)
								{
									$location = array(
										$location['name'],
										$location['path'],
									);
								}
								unset($location);

								$table = C\Tools::formatShellTable($objects['locations']);
								$this->_SHELL->print($table, 'grey');
							}
							else {
								$this->_SHELL->error('Aucun résultat', 'orange');
							}
						}

						if(isset($objects['cabinets']))
						{
							$counter = count($objects['cabinets']);
							$this->_SHELL->EOL()->print('CABINETS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['cabinets'] as &$cabinet)
								{
									$cabinet = array(
										$cabinet['name'],
										$cabinet['path'],
									);
								}
								unset($cabinet);

								$table = C\Tools::formatShellTable($objects['cabinets']);
								$this->_SHELL->print($table, 'grey');
							}
							else {
								$this->_SHELL->error('Aucun résultat', 'orange');
							}
						}

						if(isset($objects['equipments']))
						{
							$counter = count($objects['equipments']);
							$this->_SHELL->EOL()->print('EQUIPMENTS ('.$counter.')', 'black', 'white');

							if($counter > 0)
							{
								foreach($objects['equipments'] as &$equipment)
								{
									$equipment = array(
										$equipment['name'],
										$equipment['templateName'],
										$equipment['path'],
										$equipment['serialNumber'],
									);
								}
								unset($equipment);

								$table = C\Tools::formatShellTable($objects['equipments']);
								$this->_SHELL->print($table, 'grey');
							}
							else {
								$this->_SHELL->error('Aucun résultat', 'orange');
							}
						}

						$this->_SHELL->EOL();
					}
				}
				else {
					$this->_SHELL->error("Aucun résultat trouvé", 'orange');
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
				case 'locations': {
					$locations = $this->_getLocationInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('locations' => $locations);
				}
				case 'cabinet':
				case 'cabinets': {
					$cabinets = $this->_getCabinetInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('cabinets' => $cabinets);
				}
				case 'equipment':
				case 'equipments': {
					$equipments = $this->_getEquipmentInfos($objectSearch, $this->_searchfromCurrentPath, $path, true);
					return array('equipments' => $equipments);
				}
				case 'all': {
					$locations = $this->_searchObjects($path, 'location', $objectSearch);
					$cabinets = $this->_searchObjects($path, 'cabinet', $objectSearch);
					$equipments = $this->_searchObjects($path, 'equipment', $objectSearch);
					return array_merge($locations, $cabinets, $equipments);
				}
				default: {
					throw new Exception("Search item '".$objectType."' is unknow", E_USER_ERROR);
				}
			}
		}

		protected function _getLocationResults($location, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			if($fromCurrentPath)
			{
				$pathApi = $this->_browser($path, false);
				$currentApi = $this->_getLastLocationPath($pathApi);

				if($currentApi instanceof Dcim\Api_Location) {
					$locationId = $currentApi->getLocationId();
					$locations = Dcim\Api_Location::searchLocations($location, $locationId, $recursion);
				}
			}
			else {
				$locations = Dcim\Api_Location::searchLocations($location);
			}

			return (is_array($locations)) ? ($locations) : (array());
		}

		protected function _getCabinetResults($cabinet, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			if($fromCurrentPath)
			{
				$pathApi = $this->_browser($path, false);
				$currentApi = $this->_getLastLocationPath($pathApi);

				if($currentApi instanceof Dcim\Api_Location) {
					$locationId = $currentApi->getLocationId();
					$cabinets = Dcim\Api_Cabinet::searchCabinets($cabinet, $locationId, $recursion);
				}
			}
			else {
				$cabinets = Dcim\Api_Cabinet::searchCabinets($cabinet);
			}

			return (is_array($cabinets)) ? ($cabinets) : (array());
		}

		protected function _getEquipmentResults($equipment, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			if($fromCurrentPath)
			{
				$currentApi = $this->_browser($path);

				if($currentApi instanceof Dcim\Api_Cabinet) {
					$cabinetId = $currentApi->getCabinetId();
					$equipments = Dcim\Api_Equipment::searchEquipments($equipment, $equipment, $equipment, $cabinetId, null);
				}
				elseif($currentApi instanceof Dcim\Api_Location) {
					$locationId = $currentApi->getLocationId();
					$equipments = Dcim\Api_Equipment::searchEquipments($equipment, $equipment, $equipment, null, $locationId, $recursion);
				}
			}
			else {
				$equipments = Dcim\Api_Equipment::searchEquipments($equipment, $equipment, $equipment);
			}

			return (is_array($equipments)) ? ($equipments) : (array());
		}

		protected function _getLocationObjects($location, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$locations = $this->_getLocationResults($location, $fromCurrentPath, $path, $recursion);

			foreach($locations as &$location) {
				$location = Dcim\Api_Location::factory($location[Dcim\Api_Location::FIELD_ID]);
			}
			unset($location);

			return $locations;
		}

		protected function _getCabinetObjects($cabinet, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$cabinets = $this->_getCabinetResults($cabinet, $fromCurrentPath, $path, $recursion);

			foreach($cabinets as &$cabinet) {
				$cabinet = Dcim\Api_Cabinet::factory($cabinet[Dcim\Api_Cabinet::FIELD_ID]);
			}
			unset($cabinet);

			return $cabinets;
		}

		protected function _getEquipmentObjects($equipment, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$equipments = $this->_getEquipmentResults($equipment, $fromCurrentPath, $path, $recursion);

			foreach($equipments as &$equipment) {
				$equipment = Dcim\Api_Equipment::factory($equipment[Dcim\Api_Equipment::FIELD_ID]);
			}
			unset($equipment);

			return $equipments;
		}

		protected function _getLocationInfos($location, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$items = array();

			$locations = $this->_getLocationResults($location, $fromCurrentPath, $path, $recursion);

			foreach($locations as $location)
			{
				$item = array();
				$item['header'] = $location['name'];
				$item['name'] = $location['name'];
				$item['path'] = '/'.str_replace(Dcim\Api_Location::SEPARATOR_PATH, '/', $location['path']);
				$items[] = $item;
			}

			return $items;
		}

		protected function _getCabinetInfos($cabinet, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$items = array();

			$cabinets = $this->_getCabinetResults($cabinet, $fromCurrentPath, $path, $recursion);

			foreach($cabinets as $cabinet)
			{
				$item = array();
				$item['header'] = $cabinet['name'];
				$item['name'] = $cabinet['name'];
				$item['path'] = DIRECTORY_SEPARATOR.str_replace(Dcim\Api_Cabinet::SEPARATOR_PATH, DIRECTORY_SEPARATOR, $cabinet['path']);
				$items[] = $item;
			}

			return $items;
		}

		protected function _getEquipmentInfos($equipment, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$items = array();

			$equipments = $this->_getEquipmentObjects($equipment, $fromCurrentPath, $path, $recursion);

			foreach($equipments as $Dcim_Api_Equipment)
			{
				$item = array();
				$item['header'] = $Dcim_Api_Equipment->getLabel();
				$item['name'] = $Dcim_Api_Equipment->getLabel();
				$item['templateName'] = $Dcim_Api_Equipment->getTemplateName();
				//$item['description'] = $Dcim_Api_Equipment->getDescription();
				$item['serialNumber'] = $Dcim_Api_Equipment->getSerialNumber();
				$item['locationName'] = $Dcim_Api_Equipment->locationApi->getLabel();
				//$item['locationPath'] = DIRECTORY_SEPARATOR.str_replace(Dcim\Api_Location::SEPARATOR_PATH, DIRECTORY_SEPARATOR, $Dcim_Api_Equipment->locationApi->getPath(true));
				$item['cabinetName'] = $Dcim_Api_Equipment->cabinetApi->getLabel();
				//$item['cabinetPath'] = DIRECTORY_SEPARATOR.str_replace(Dcim\Api_Cabinet::SEPARATOR_PATH, DIRECTORY_SEPARATOR, $Dcim_Api_Equipment->cabinetApi->getPath(true));
				$item['path'] = DIRECTORY_SEPARATOR.str_replace(Dcim\Api_Equipment::SEPARATOR_PATH, DIRECTORY_SEPARATOR, $Dcim_Api_Equipment->getPath());

				$position = $Dcim_Api_Equipment->getPosition();
				$item['position'] = array($position['side'], $position['U']);

				$items[] = $item;
			}

			return $items;
		}
		// --------------------------------------------------

		// // Service_Cli_Abstract : SYSTEM METHODS
		// --------------------------------------------------
		public function printObjectInfos(array $args, $fromCurrentContext = true)
		{
			// /!\ ls AUB --> On ne doit pas afficher AUB mais le contenu de AUB !
			/*$objectApi = end($this->_pathApi);

			switch(get_class($objectApi))
			{
				case 'Addon\Dcim\Api_Location':
					$cases = array(
						'location' => '_getLocationInfos',
						'cabinet' => '_getCabinetInfos',
						'equipment' => '_getEquipmentInfos',		// Des équipements pourraient être directement dans une location
					);
					break;
				case 'Addon\Dcim\Api_Cabinet':
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

			$result = $this->_printObjectInfos($cases, $args, $fromCurrentContext);

			if($result !== false)
			{
				list($status, $objectType, $infos) = $result;

				/**
				  * /!\ Attention aux doublons lorsque printObjectsList est appelé manuellement
				  * Voir code pour ls ou ll dans services/browser méthode _routeShellCmd
				  */
				/*if($status && $objectType === 'equipment') {
					$this->_printEquipmentExtra($infos);
				}*/

				return $status;
			}
			else {
				return false;
			}
		}

		protected function _getObjects($context = null, array $args = null)
		{
			$path = $context;
			$side = null;
			$view = null;

			$items = array(
				Dcim\Api_Location::OBJECT_TYPE => array(),
				Dcim\Api_Cabinet::OBJECT_TYPE => array(),
				Dcim\Api_Equipment::OBJECT_TYPE => array(),
				Dcim\Api_Cable::OBJECT_TYPE => array(),
			);

			$currentApi = $this->_browser($path);
			$currentType = $currentApi::OBJECT_TYPE;

			/**
			  * Pourquoi ::class et non ::OBJECT_TYPE?
			  * Voir Application\Ipam->_getObjects
			  */
			$cases = array(
				Dcim\Api_Location::OBJECT_TYPE => array(
					Dcim\Api_Location::class => 'getSubLocationIds',
					Dcim\Api_Cabinet::class => 'getCabinetIds',
				),
				Dcim\Api_Cabinet::OBJECT_TYPE => array(false),
				Dcim\Api_Equipment::OBJECT_TYPE =>  array(false),
			);

			if(array_key_exists($currentType, $cases))
			{
				foreach($cases[$currentType] as $objectClass => $objectMethod)
				{
					if($objectMethod !== false) {
						$objects = call_user_func(array($currentApi, $objectMethod));
					}
					else {
						$objects = false;
					}

					if(C\Tools::is('array&&count>0', $objects))
					{
						$objectType = $objectClass::OBJECT_TYPE;

						foreach($objects as $object)
						{
							switch($objectType)
							{
								case Dcim\Api_Location::OBJECT_TYPE: {
									$Dcim_Api_Abstract = Dcim\Api_Location::factory($object);
									break;
								}
								case Dcim\Api_Cabinet::OBJECT_TYPE: {
									$Dcim_Api_Abstract = Dcim\Api_Cabinet::factory($object);
									break;
								}
								default: {
									throw new Exception("Object type '".$objectType."' is not valid", E_USER_ERROR);
								}
							}

							$objectName = $Dcim_Api_Abstract->getObjectLabel();
							$items[$objectType][] = array('name' => $objectName);
						}
					}
					elseif($currentApi instanceof Dcim\Api_Cabinet)
					{
						if(isset($args[1])) {
							$side = mb_strtolower($args[1]);
						}

						if(isset($args[2])) {
							$view = mb_strtolower($args[2]);
						}

						$equipments = $currentApi->getEquipmentIds();

						foreach($equipments as $equipment)
						{
							$Dcim_Api_Equipment = Dcim\Api_Equipment::factory($equipment);

							$objectName = $Dcim_Api_Equipment->getLabel();
							$position = $Dcim_Api_Equipment->getPosition();
							$templateU = $Dcim_Api_Equipment->getTemplateU();
							$serialNumber = $Dcim_Api_Equipment->getSerialNumber();

							$items[Dcim\Api_Equipment::OBJECT_TYPE][] = array(
									'name' => $objectName,
									'side' => $position['side'],
									'positionU' => $position['U'],
									'templateU' => $templateU,
									'serialNumber' => $serialNumber,
							);
						}
					}
					elseif($currentApi instanceof Dcim\Api_Equipment)
					{
						$ports = $currentApi->getConnectedPortIds();

						foreach($ports as $port)
						{
							$portApi = Dcim\Api_Equipment_Port::factory($port);
							$nbPort = $portApi->getEndConnectedPortId();

							if($nbPort !== false)
							{
								$nbPortApi = Dcim\Api_Equipment_Port::factory($nbPort);

								$items[Dcim\Api_Cable::OBJECT_TYPE][] = array(
										'port' => $portApi->getLabel(),
										'name' => $portApi->cableApi->getLabel(),
										'nbPort' => $nbPortApi->getLabel(),
										'nbName' => $nbPortApi->equipmentApi->getLabel(),
								);
							}
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

			usort($items[Dcim\Api_Location::OBJECT_TYPE], $compare);
			usort($items[Dcim\Api_Cabinet::OBJECT_TYPE], $compare);
			usort($items[Dcim\Api_Cable::OBJECT_TYPE], $compare);

			/*$compare = function($a, $b)
			{
				if($a['side'] !== $b['side']) {
					return (mb_strtolower($a['side']) === Dcim\Api_Cabinet::SIDE_FRONT) ? (-1) : (1);
				}
				elseif($a['positionU'] !== $b['positionU']) {
					return ($a['positionU'] < $b['positionU']) ? (1) : (-1);			// On souhaite avoir les U en haut en 1er
				}
				else {
					return strnatcasecmp($a['name'], $b['name']);
				}
			};

			usort($items[Dcim\Api_Equipment::OBJECT_TYPE], $compare);*/

			$items[Dcim\Api_Equipment::OBJECT_TYPE] = $this->_formatCabinetEquipments($currentApi, $items[Dcim\Api_Equipment::OBJECT_TYPE], $side, $view);

			return array(
				'location' => $items[Dcim\Api_Location::OBJECT_TYPE],
				'cabinet' => $items[Dcim\Api_Cabinet::OBJECT_TYPE],
				'equipment' => $items[Dcim\Api_Equipment::OBJECT_TYPE],
				'cable' => $items[Dcim\Api_Cable::OBJECT_TYPE]
			);
		}

		protected function _formatCabinetEquipments(Dcim\Api_Abstract $currentApi, array $equipments, $side = null, $view = null)
		{
			$results = array();

			if($currentApi instanceof Dcim\Api_Cabinet)
			{
				$inUsingU = array();
				$cabinetU = $currentApi->getTemplateU();

				switch($side)
				{
					case 'front': {
						$side = Dcim\Api_Cabinet::SIDE_FRONT;
						break;
					}
					case 'rear': {
						$side = Dcim\Api_Cabinet::SIDE_REAR;
						break;
					}
					default: {
						$side = null;
					}
				}

				if($view !== 'summary') {
					$view = null;
				}

				/**
				  * On souhaite avoir les U en haut en 1er
				  */
				for($i=$cabinetU; $i>0; $i--)
				{
					if($side === null) {
						$results[$i] =  array(null, null, $i, '<-- front', "      ", 'rear -->', $i, null, null);
					}
					else {
						$results[$i] =  array(null, null, $i);
					}
				}

				$posMalus = ($side === null) ? (7) : (0);

				foreach($equipments as $equipment)
				{
					if($side !== null && $side !== $equipment['side']) {
						continue;
					}

					$j = ($equipment['side'] === Dcim\Api_Cabinet::SIDE_FRONT) ? (0) : ($posMalus);

					for($i=0; $i<$equipment['templateU']; $i++)
					{
						$inUsingU[] = $U = (int) ($equipment['positionU']+$i);

						if(!isset($results[$U][0+$j])) {
							$results[$U][0+$j] = $equipment['name'];
							$results[$U][1+$j] = $equipment['serialNumber'];
						}
						else {
							$results[$U][0+$j] .= PHP_EOL.$equipment['name'];
							$results[$U][1+$j] .= PHP_EOL.$equipment['serialNumber'];
						}
					}
				}

				if($view === 'summary')
				{
					for($i=$cabinetU; $i>0; $i--)
					{
						if(!in_array($i, $inUsingU, true)) {
							unset($results[$i]);
						}
					}
				}
			}

			return $results;
		}
		// --------------------------------------------------

		// EQUIPMENTS : MODIFY
		// --------------------------------------------------
		public function modifyEquipment(array $args)
		{
			if(count($args) >= 3)
			{
				if($args[0] === '.')
				{
					$refreshPrompt = true;
					$Dcim_Api_Equipment = $this->_getCurrentPathApi();

					if(!$Dcim_Api_Equipment instanceof Dcim\Api_Equipment) {
						$this->_SHELL->error("L'emplacement actuel n'est pas un équipement, merci de vous déplacer jusqu'à l'équipement concerné ou indiquez son nom", 'orange');
						unset($Dcim_Api_Equipment);
					}
				}
				else
				{
					$refreshPrompt = false;
					$equipments = Dcim\Api_Equipment::searchEquipments($args[0], null, $args[0]);

					if($equipments !== false)
					{
						switch(count($equipments))
						{
							case 0: {
								$this->_SHELL->error("Aucun équipement n'a été trouvé durant la recherche de '".$args[0]."'", 'orange');
								break;
							}
							case 1: {
								$equipmentId = $equipments[0][Dcim\Api_Equipment::FIELD_ID];
								$Dcim_Api_Equipment = Dcim\Api_Equipment::factory($equipmentId);
								break;
							}
							default: {
								$this->_SHELL->error("Plusieurs équipements ont été trouvés durant la recherche de '".$args[0]."'", 'orange');
							}
						}
					}
					else {
						$this->_SHELL->error("Une erreur s'est produite durant la recherche de l'équipement '".$args[0]."'", 'orange');
					}
				}

				if(isset($Dcim_Api_Equipment))
				{
					switch($args[1])
					{
						case 'name':
						case 'label': {
							$status = $Dcim_Api_Equipment->renameLabel($args[2]);
							break;
						}
						case 'description': {
							$status = $Dcim_Api_Equipment->setDescription($args[2]);
							break;
						}
						case 'sn':
						case 'serialnumber': {
							$status = $Dcim_Api_Equipment->setSerialNumber($args[2]);
							break;
						}
						default: {
							$this->_SHELL->error("L'attribut '".$args[1]."' n'est pas valide pour un équipement", 'orange');
							return false;
						}
					}

					if($status) {
						$this->_SHELL->print("L'équipement '".$Dcim_Api_Equipment->label."' a été modifié!", 'green');
					}
					else
					{
						if($Dcim_Api_Equipment->hasErrorMessage()) {
							$this->_SHELL->error($Dcim_Api_Equipment->getErrorMessage(), 'orange');
						}
						else {
							$this->_SHELL->error("L'équipement '".$Dcim_Api_Equipment->label."' n'a pas pu être modifié!", 'orange');
						}
					}

					if($refreshPrompt) {
						$this->_SHELL->refreshPrompt();
					}
				}

				return true;
			}
			else {
				return false;
			}
		}
		// --------------------------------------------------

		protected function _getLastLocationPath(array $pathApi)
		{
			return $this->_searchLastPathApi($pathApi, 'Addon\Dcim\Api_Location');
		}

		// ----------------- AutoCompletion -----------------
		public function shellAutoC_cd($cmd, $search = null)
		{
			$Core_StatusValue = new C\StatusValue(false, array());

			if($search === null) {
				$search = '';
			}
			elseif($search === false) {
				return $Core_StatusValue;
			}

			/**
			  * /!\ Pour eviter le double PHP_EOL (celui du MSG et de la touche ENTREE)
			  * penser à désactiver le message manuellement avec un lineUP
			  */
			$this->_SHELL->displayWaitingMsg(true, false, 'Searching DCIM objects');

			if($search !== '' && $search !== DIRECTORY_SEPARATOR && substr($search, -1, 1) !== DIRECTORY_SEPARATOR) {
				$search .= DIRECTORY_SEPARATOR;
			}

			$input = $search;
			$firstChar = substr($search, 0, 1);

			if($firstChar === DIRECTORY_SEPARATOR) {
				$mode = 'absolute';
				$input = substr($input, 1);						// Pour le explode / implode
				$search = substr($search, 1);					// Pour le explode / foreach
				$baseApi = $this->_getRootPathApi();
				$pathApi = array($baseApi);
			}
			elseif($firstChar === '~') {
				return $Core_StatusValue;
			}
			else {
				$mode = 'relative';
				$pathApi = $this->_getPathApi();
				$baseApi = $this->_getCurrentPathApi();
			}

			/*$this->_SHELL->print('MODE: '.$mode.PHP_EOL, 'green');
			$this->_SHELL->print('PATH: '.$baseApi->getPath(true, DIRECTORY_SEPARATOR).PHP_EOL, 'orange');
			$this->_SHELL->print('INPUT: '.$input.PHP_EOL, 'green');
			$this->_SHELL->print('SEARCH: '.$search.PHP_EOL, 'green');*/

			$searchParts = explode(DIRECTORY_SEPARATOR, $search);

			foreach($searchParts as $index => $search)
			{
				$baseApi = end($pathApi);

				if($search === '..')
				{
					if(count($pathApi) > 1) {
						$status = false;
						$results = array();
						array_pop($pathApi);
					}
					else {
						continue;
					}
				}
				else
				{
					$Core_StatusValue__browser = $this->_shellAutoC_cd_browser($baseApi, $search);

					$status = $Core_StatusValue__browser->status;
					$result = $Core_StatusValue__browser->result;

					if(is_array($result))
					{
						if($status === false && count($result) === 0)
						{
							// empty directory
							if($search === '') {
								$status = true;
								$results = array('');	// Workaround retourne un seul resultat avec en clé input et en valeur ''
							}
							// no result found
							else
							{
								$Core_StatusValue__browser = $this->_shellAutoC_cd_browser($baseApi, null);

								if($Core_StatusValue__browser instanceof C\StatusValue) {
									$status = $Core_StatusValue__browser->status;
									$results = $Core_StatusValue__browser->results;
								}
								// /!\ Ne doit jamais se réaliser!
								else {
									return $Core_StatusValue;
								}
							}

							break;
						}
						else {
							$status = false;
							$results = $result;
							break;
						}
					}
					elseif($result instanceof Dcim\Api_Abstract)
					{
						$pathApi[] = $result;
						$results = array('');			// Workaround retourne un seul resultat avec en clé input et en valeur ''

						/**
						  * Ne surtout pas arrêter le traitement afin que l'on trouve un répertoire vide
						  * Cela permet d'enclencher le workaround et de terminer proprement l'algorithme
						  */
						/*if($result instanceof Dcim\Api_Equipment) {
							break;
						}*/
					}
					// /!\ Ne doit jamais se réaliser!
					else {
						return $Core_StatusValue;
					}
				}
			}

			$parts = explode(DIRECTORY_SEPARATOR, $input);
			array_splice($parts, $index, count($parts), '');
			$input = implode(DIRECTORY_SEPARATOR, $parts);

			/*$this->_SHELL->print('index: '.$index.PHP_EOL, 'red');
			$this->_SHELL->print('count: '.count($parts).PHP_EOL, 'red');*/

			if($mode === 'absolute') {
				$input = DIRECTORY_SEPARATOR.$input;
			}

			//$this->_SHELL->print('INPUT: '.$input.PHP_EOL, 'blue');

			$options = array();

			foreach($results as $result)
			{
				if($result !== '') {
					$result .= DIRECTORY_SEPARATOR;
				}

				$options[$input.$result] = $result;
			}

			/*$this->_SHELL->print('STATUS: '.$status.PHP_EOL, 'blue');
			$this->_SHELL->print('OPTIONS: '.PHP_EOL, 'blue');
			var_dump($options); $this->_SHELL->EOL();*/
			
			$Core_StatusValue->setStatus($status);
			$Core_StatusValue->setOptions($options);

			// Utile car la désactivation doit s'effectuer avec un lineUP, voir message plus haut
			$this->_SHELL->deleteWaitingMsg(true);

			return $Core_StatusValue;
		}

		/**
		  * @param Addon\Dcim\Api_Abstract $baseApi
		  * @param null|string $search
		  * @return Core\StatusValue
		  */
		protected function _shellAutoC_cd_browser($baseApi, $search = null)
		{
			$locations = true;
			$cabinets = true;
			$equipments = true;

			$status = false;
			$results = array();
			$baseApiClassName = get_class($baseApi);

			if($baseApiClassName === 'Addon\Dcim\Api_Location')
			{
				$locations = $baseApi->findLocations($search.'*', false);

				if($locations !== false)
				{
					$locations = array_column($locations, Dcim\Api_Location::FIELD_NAME, Dcim\Api_Location::FIELD_ID);

					if(($locationId = array_search($search, $locations, true)) !== false) {
						$results = Dcim\Api_Location::factory($locationId);
					}
					elseif(count($locations) > 0) {
						$results = array_merge($results, array_values($locations));
					}
				}

				$cabinets = $baseApi->findCabinets($search.'*', false);

				if($cabinets !== false)
				{
					$cabinets = array_column($cabinets, Dcim\Api_Cabinet::FIELD_NAME, Dcim\Api_Cabinet::FIELD_ID);

					if(($cabinetId = array_search($search, $cabinets, true)) !== false) {
						$results = Dcim\Api_Cabinet::factory($cabinetId);
					}
					elseif(count($cabinets) > 0) {
						$results = array_merge($results, array_values($cabinets));
					}
				}
			}

			//if($baseApiClassName === 'Addon\Dcim\Api_Location' || $baseApiClassName === 'Addon\Dcim\Api_Cabinet')
			if($baseApiClassName === 'Addon\Dcim\Api_Cabinet')
			{
				$equipments = $baseApi->findEquipments($search.'*', null, null, false, true);

				if($equipments !== false)
				{
					$equipments = array_column($equipments, Dcim\Api_Equipment::FIELD_NAME, Dcim\Api_Equipment::FIELD_ID);

					if(($equipmentId = array_search($search, $equipments, true)) !== false) {
						$status = true;
						$results = Dcim\Api_Equipment::factory($equipmentId);
					}
					elseif(count($equipments) > 0) {
						$results = array_merge($results, array_values($equipments));
					}
				}
			}

			if(is_array($results))
			{
				/**
				  * Si aucun des recherches ne fonctionnent ou si plusieurs résultats ont été trouvés mais qu'aucun ne correspond à la recherche
				  * alors cela signifie qu'on est arrivé au bout du traitement, on ne pourrait pas aller plus loin, donc on doit retourner true
				  */
				$status = (($locations === false && $cabinets === false && $equipments === false) || count($results) > 0);
			}

			return new C\StatusValue($status, $results);
		}
		// --------------------------------------------------
	}