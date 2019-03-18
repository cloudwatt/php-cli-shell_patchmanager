<?php
	namespace App\Dcim;

	use Core as C;

	use Cli as Cli;

	use Addon\Dcim;

	class Shell_Program_Dcim extends Cli\Shell\Program\Browser
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
				//'fields' => array('name', 'side', 'positionU', 'serialNumber'),
				//'format' => '[%2$s] (U%3$d) %1$s {%4$s}'
				'fields' => false,
				'format' => false
			),
			'cable' => array(
				'fields' => array('port', 'name', 'nbPort', 'nbName'),
				'format' => '[%s] <-- %s --> [%s] {%s}'
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

		protected $_searchfromCurrentPath = true;


		protected function _getObjects($context = null)
		{
			$path = $context;

			$items = array(
				Dcim\Api_Location::OBJECT_KEY => array(),
				Dcim\Api_Cabinet::OBJECT_KEY => array(),
				Dcim\Api_Equipment::OBJECT_KEY => array(),
				Dcim\Api_Cable::OBJECT_KEY => array(),
			);

			$currentApi = $this->_browser($path);
			$currentApiClass = get_class($currentApi);

			$cases = array(
				'Addon\Dcim\Api_Location' => array(
					'Addon\Dcim\Api_Location' => 'getSubLocationIds',
					'Addon\Dcim\Api_Cabinet' => 'getCabinetIds',
				),
				'Addon\Dcim\Api_Cabinet' => array(false),
				'Addon\Dcim\Api_Equipment' =>  array(false),
			);

			if(array_key_exists($currentApiClass, $cases))
			{
				foreach($cases[$currentApiClass] as $objectClass => $objectMethod)
				{
					if($objectMethod !== false) {
						$objects = call_user_func(array($currentApi, $objectMethod));
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
								case 'Addon\Dcim\Api_Location': {
									$Dcim_Api_Location = new Dcim\Api_Location($object);
									$objectName = $Dcim_Api_Location->getLocationLabel();
									break;
								}
								case 'Addon\Dcim\Api_Cabinet': {
									$Dcim_Api_Cabinet = new Dcim\Api_Cabinet($object);
									$objectName = $Dcim_Api_Cabinet->getCabinetLabel();
									break;
								}
								default: {
									throw new Exception("Object class '".$objectClass."' is not valid", E_USER_ERROR);
								}
							}

							$items[$objectClass::OBJECT_KEY][] = array('name' => $objectName);
						}
					}
					elseif($currentApi instanceof Dcim\Api_Cabinet)
					{
						$equipments = $currentApi->getEquipmentIds();

						foreach($equipments as $equipment)
						{
							$Dcim_Api_Equipment = new Dcim\Api_Equipment($equipment);

							$objectName = $Dcim_Api_Equipment->getLabel();
							$position = $Dcim_Api_Equipment->getPosition();
							$templateU = $Dcim_Api_Equipment->getTemplateU();
							$serialNumber = $Dcim_Api_Equipment->getSerialNumber();

							$items[Dcim\Api_Equipment::OBJECT_KEY][] = array(
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
							$portApi = new Dcim\Api_Equipment_Port($port);
							$nbPort = $portApi->getEndConnectedPortId();

							if($nbPort !== false)
							{
								$nbPortApi = new Dcim\Api_Equipment_Port($nbPort);

								$items[Dcim\Api_Cable::OBJECT_KEY][] = array(
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

			usort($items[Dcim\Api_Location::OBJECT_KEY], $compare);
			usort($items[Dcim\Api_Cabinet::OBJECT_KEY], $compare);
			usort($items[Dcim\Api_Cable::OBJECT_KEY], $compare);

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

			usort($items[Dcim\Api_Equipment::OBJECT_KEY], $compare);*/

			$items[Dcim\Api_Equipment::OBJECT_KEY] = $this->_formatCabinetEquipments($currentApi, $items[Dcim\Api_Equipment::OBJECT_KEY]);

			return array(
				'location' => $items[Dcim\Api_Location::OBJECT_KEY],
				'cabinet' => $items[Dcim\Api_Cabinet::OBJECT_KEY],
				'equipment' => $items[Dcim\Api_Equipment::OBJECT_KEY],
				'cable' => $items[Dcim\Api_Cable::OBJECT_KEY]
			);
		}

		protected function _formatCabinetEquipments(Dcim\Api_Abstract $currentApi, array $equipments)
		{
			$results = array();

			if($currentApi instanceof Dcim\Api_Cabinet)
			{
				$templateU = $currentApi->getTemplateU();

				/**
				  * On souhaite avoir les U en haut en 1er
				  */
				for($i=$templateU; $i>0; $i--)
				{
					$results[$i] = array(
						null, null, $i, '<-- front', "      ", 'rear -->', $i, null, null
					);
				}

				foreach($equipments as $equipment)
				{
					if(array_key_exists((int) $equipment['positionU'], $results))
					{
						$j = ($equipment['side'] === Dcim\Api_Cabinet::SIDE_FRONT) ? (0) : (7);

						for($i=0; $i<$equipment['templateU']; $i++)
						{
							if($results[$equipment['positionU']+$i][0+$j] === null) {
								$results[$equipment['positionU']+$i][0+$j] = $equipment['name'];
								$results[$equipment['positionU']+$i][1+$j] = $equipment['serialNumber'];
							}
							else {
								$results[$equipment['positionU']+$i][0+$j] .= PHP_EOL.$equipment['name'];
								$results[$equipment['positionU']+$i][1+$j] .= PHP_EOL.$equipment['serialNumber'];
							}
						}
					}
				}
			}

			return $results;
		}

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
					$this->printEquipmentExtra($infos);
				}*/

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
					$this->_SHELL->error("Localisation introuvable", 'orange');
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
					$this->_SHELL->error("Baie introuvable", 'orange');
				}
				elseif(count($infos) === 1)
				{
					$side = null;
					$view = null;

					// @todo a coder
					if(isset($args[1]))
					{
						$args[1] = mb_strtolower($args[1]);

						if($args[1] === 'front' || $args[1] === 'rear') {
							$side = $args[1];
						}
					}

					// @todo a coder
					if(isset($args[2]))
					{
						$args[2] = mb_strtolower($args[2]);

						if($args[2] === 'summary' || $args[2] === 'full') {
							$view = $args[2];
						}
					}

					$path = $infos[0]['path'].'/'.$infos[0]['name'];
					$this->printObjectsList($path);
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
					$this->_SHELL->error("Equipement introuvable", 'orange');
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
			if(count($infos) === 1) {
				$path = $infos[0]['path'].'/'.$infos[0]['name'];
				$this->printObjectsList($path);
			}
		}

		protected function _getLocationInfos($location, $fromCurrentPath = true, $path = null, $recursion = false)
		{
			$items = array();
			$locations = array();

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
			

			foreach($locations as $location)
			{
				//$Dcim_Api_Location = new Dcim\Api_Location($location['entity_id']);

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
			$cabinets = array();

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
			

			foreach($cabinets as $cabinet)
			{
				//$Dcim_Api_Cabinet = new Dcim\Api_Cabinet($cabinet['entity_id']);

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
			$equipments = array();

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

			foreach($equipments as $equipment)
			{
				$Dcim_Api_Equipment = new Dcim\Api_Equipment($equipment['entity_id']);

				$item = array();
				$item['header'] = $Dcim_Api_Equipment->getLabel();
				$item['templateName'] = $Dcim_Api_Equipment->getTemplateName();
				$item['name'] = $Dcim_Api_Equipment->getLabel();
				//$item['description'] = $Dcim_Api_Equipment->getDescription();
				$item['serialNumber'] = $Dcim_Api_Equipment->getSerialNumber();
				$item['locationName'] = $Dcim_Api_Equipment->locationApi->getLabel();
				$item['cabinetName'] = $Dcim_Api_Equipment->cabinetApi->getLabel();
				$item['path'] = DIRECTORY_SEPARATOR.str_replace(Dcim\Api_Equipment::SEPARATOR_PATH, DIRECTORY_SEPARATOR, $Dcim_Api_Equipment->getPath());

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
								foreach($objects['locations'] as $location)
								{
									$text1 = '['.$location['path'].']';
									$text1 .= C\Tools::t($text1, "\t", 2, 0, 8);
									$text2 = $location['header'];
									$this->_SHELL->print($text1.$text2, 'grey');
								}
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
								foreach($objects['cabinets'] as $cabinet)
								{
									$text1 = '['.$cabinet['path'].']';
									$text1 .= C\Tools::t($text1, "\t", 2, 0, 8);
									$text2 = $cabinet['header'];
									$this->_SHELL->print($text1.$text2, 'grey');
								}
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
								foreach($objects['equipments'] as $equipment)
								{
									$text1 = '['.$equipment['path'].']';
									$text1 .= C\Tools::t($text1, "\t", 7, 0, 8);
									$text2 = $equipment['templateName'];
									$text2 .= C\Tools::t($text2, "\t", 4, 0, 8);
									$text3 = $equipment['header'].' {'.$equipment['serialNumber'].'}';
									$this->_SHELL->print($text1.$text2.$text3, 'grey');
								}
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
			return $this->_searchLastPathApi($pathApi, 'Addon\Dcim\Api_Location');
		}

		// MODIFY
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
								$Dcim_Api_Equipment = new Dcim\Api_Equipment($equipments[0][Dcim\Api_Equipment::FIELD_ID]);
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
						$results = new Dcim\Api_Location($locationId);
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
						$results = new Dcim\Api_Cabinet($cabinetId);
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
						$results = new Dcim\Api_Equipment($equipmentId);
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