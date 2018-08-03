<?php
	include_once('abstract.php');
	include_once('shell/dcim.php');
	include_once(__DIR__ . '/../classes/soap.php');
	include_once(__DIR__ . '/../dcim/abstract.php');
	include_once(__DIR__ . '/../dcim/api/abstract.php');
	include_once(__DIR__ . '/../dcim/api/location.php');
	include_once(__DIR__ . '/../dcim/api/cabinet.php');
	include_once(__DIR__ . '/../dcim/api/equipment.php');
	include_once(__DIR__ . '/../dcim/api/equipment/port.php');
	include_once(__DIR__ . '/../dcim/api/cable.php');

	class DCIM extends DCIM_Abstract {}
	class DCIM_Connector_Soap extends DCIM_Connector_Soap_Read_Abstract {}

	class SHELL extends Shell_Abstract {}

	class Service_Dcim extends Service_Abstract
	{
		const SHELL_HISTORY_FILENAME = '.dcim.history';

		protected $_DCIM;

		protected $_commands = array(
			'help', 'history', 'cdautocomplete',
			'ls', 'll', 'cd', 'pwd', 'find', 'exit', 'quit',
			'list' => array(
				'location', 'cabinet', 'equipment',
			),
			'show' => array(
				'location', 'cabinet', 'equipment',
				/*'system', 'sys', 'server',
				'network', 'net', 'appliance'*/
			),
			'patchmanager'
		);

		/**
		  * Arguments ne commencant pas par - mais étant dans le flow de la commande
		  *
		  * ls mon/chemin/a/lister
		  * cd mon/chemin/ou/aller
		  * find ou/lancer/ma/recherche
		  */
		protected $_inlineArgCmds = array(
			'cdautocomplete' => array(0 => array('enable', 'en', 'disable', 'dis')),
			'ls' => "#^\"?([a-z0-9\-_/~. ]+)\"?$#i",
			'll' => "#^\"?([a-z0-9\-_/~. ]+)\"?$#i",
			'cd' => "#^\"?([a-z0-9\-_/~. ]+)\"?$#i",
			'find' => array(0 => "#^\"?([a-z0-9\-_. /~]+)\"?$#i", 1 => array('location', 'cabinet', 'equipment'), 2 => "#^\"?([a-z0-9\-_.:* ]+)\"?$#i"),
			'list location' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'list cabinet' => array(0 => "#^\"?([a-z0-9\-_. ]+)\"?$#i", 1 => array('summary', 'full')),
			'list equipment' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'show location' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'show cabinet' => array(0 => "#^\"?([a-z0-9\-_. ]+)\"?$#i", 1 => array('summary', 'full')),
			'show equipment' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
		);

		/**
		  * Arguments commencant pas par - ou -- donc hors flow de la commande
		  *
		  * find ... -type [type] -name [name]
		  */
		protected $_outlineArgCmds = array(
		);

		protected $_manCommands = array(
			'history' => "Affiche l'historique des commandes",
			'cdautocomplete' => "Active (enable|en) ou désactive (disable|dis) l'autocompletion de la commande cd",
			'ls' => "Affiche la liste des éléments disponibles",
			'll' => "Alias de ls",
			'cd' => "Permet de naviguer dans l'arborescence",
			'pwd' => "Affiche la position actuelle dans l'arborescence",
			'find' => "Recherche avancée d'éléments. Utilisation: find [localisation|.] [type] [recherche]",
			'exit' => "Ferme le shell",
			'quit' => "Alias de exit",
			'list' => "Affiche un type d'éléments; Dépend de la localisation actuelle. Utilisation: list [location|cabinet|equipment] [object]",
			'list location' => "Affiche les informations d'une localisation; Dépend de la localisation",
			'list cabinet' => "Affiche les informations d'une baie; Dépend de la localisation",
			'list equipment' => "Affiche les informations d'un équipement; Dépend de la localisation",
			'show' => "Affiche un type d'éléments; Ne dépend pas de la localisation actuelle. Utilisation: show [location|cabinet|equipment] [object]",
			'show location' => "Affiche les informations d'une localisation",
			'show cabinet' => "Affiche les informations d'une baie",
			'show equipment' => "Affiche les informations d'un équipement",
			/*'show system' => "Affiche les informations d'un équipement",
			'show server' => "Alias de show system",
			'show sys' => "Alias de show system",
			'show network' => "Affiche les informations d'un équipement",
			'show appliance' => "Alias de show network",
			'show net' => "Alias de show network",*/
			'patchmanager' => "Lance la GUI de PatchManager",
		);

		// /!\ Ne pas activer par défaut afin d'accélerer la navigation
		protected $_cdautocomplete = false;


		public function __construct($configFilename, $server, $autoInitialisation = true)
		{
			parent::__construct($configFilename);

			$printInfoMessages = !$this->isOneShotCall();

			$DCIM = new DCIM(array($server), $printInfoMessages);
			$this->_DCIM = $DCIM->getDcim();
			Dcim_Api_Abstract::setDcim($this->_DCIM);

			$this->_Service_Shell = new Service_Shell_Dcim($this);

			if($autoInitialisation) {
				$this->_init();
			}
		}

		protected function _launchShell($welcomeMessage = true, $goodbyeMessage = true)
		{
			$exit = false;
			$this->_preLauchingShell($welcomeMessage);

			while(!$exit)
			{
				list($cmd, $args) = $this->_SHELL->launch();

				$this->_preRoutingShellCmd($cmd, $args);
				$exit = $this->_routeShellCmd($cmd, $args);
				$this->_postRoutingShellCmd($cmd, $args);
			}

			$this->_postLauchingShell($goodbyeMessage);
		}

		protected function _routeShellCmd($cmd, array $args)
		{
			$exit = false;

			switch($cmd)
			{
				case 'find': {
					$status = $this->_Service_Shell->printSearchObjects($args);
					break;
				}
				case 'list location': {
					$status = $this->_Service_Shell->printLocationInfos($args, true);
					break;
				}
				case 'list cabinet': {
					$status = $this->_Service_Shell->printCabinetInfos($args, true);
					break;
				}
				case 'list equipment': {
					$status = $this->_Service_Shell->printEquipmentInfos($args, true);
					break;
				}
				case 'show location': {
					$status = $this->_Service_Shell->printLocationInfos($args, false);
					break;
				}
				case 'show cabinet': {
					$status = $this->_Service_Shell->printCabinetInfos($args, false);
					break;
				}
				case 'show equipment': {
					$status = $this->_Service_Shell->printEquipmentInfos($args, false);
					break;
				}
				case 'patchmanager':
				{
					$jnlp = __DIR__ . "/../patchmanager.jnlp";

					if(!file_exists($jnlp))
					{
						$jnlpUrl = $this->_DCIM->getJnlpUrl(64);
						$status = file_put_contents($jnlp, fopen($jnlpUrl, 'r'));

						if(!$status) {
							Tools::e("Impossible de télécharger le JNLP [".$jnlpUrl."]", 'red');
							break;
						}
					}

					$this->deleteWaitingMsg();
					$handle = popen('javaws "'.$jnlp.'" > /dev/null 2>&1', 'r');
					pclose($handle);
					break;
				}
				default: {
					$exit = parent::_routeShellCmd($cmd, $args);
				}
			}

			if(isset($status) && !$status) {
				$this->deleteWaitingMsg();
				$msg = Tools::e($this->_manCommands[$cmd], 'red', 'normal', false, true);
				$this->_SHELL->printMessage($msg);
			}

			return $exit;
		}

		protected function _setObjectAutocomplete(array $fields = null)
		{
			if($fields === null) {
				$fields = array('location', 'cabinet', 'equipment');
			}
			return parent::_setObjectAutocomplete($fields);
		}

		protected function _moveToRoot()
		{
			if($this->_pathIds === null || $this->_pathApi === null) {
				$this->_pathIds[] = Dcim_Api_Location::getRootLocationId();
				$this->_pathApi[] = new Dcim_Api_Location(end($this->_pathIds));
			}

			return parent::_moveToRoot();
		}

		public function browser(array &$pathIds, array &$pathApi, $path)
		{
			if(Tools::is('string', $path)) {
				$path = explode('/', $path);
			}

			foreach($path as $index => $part)
			{
				switch($part)
				{
					case '':
					case '~':
					{
						if($index === 0) {
							array_splice($pathIds, 1);
							array_splice($pathApi, 1);
						}
						break;
					}
					case '.': {
						break;
					}
					case '..':
					{
						if(count($pathApi) > 1) {
							array_pop($pathIds);
							array_pop($pathApi);
						}
						break;
					}
					default:
					{
						$objectApi = end($pathApi);
						$objectApiClass = get_class($objectApi);

						$cases = array(
							'Dcim_Api_Location' => array(
								'Dcim_Api_Location' => 'getSubLocationId',
								'Dcim_Api_Cabinet' => 'getCabinetId',
							),
							'Dcim_Api_Cabinet' => array(
								'Dcim_Api_Equipment' => 'getEquipmentId',
							),
						);

						if(array_key_exists($objectApiClass, $cases))
						{
							foreach($cases[$objectApiClass] as $objectClass => $objectMethod)
							{
								$objectId = $objectApi->{$objectMethod}($part);

								if($objectId !== false) {
									$pathIds[] = $objectId;
									$pathApi[] = new $objectClass($objectId);
									break;
								}
							}
						}
					}
				}
			}
		}
	}