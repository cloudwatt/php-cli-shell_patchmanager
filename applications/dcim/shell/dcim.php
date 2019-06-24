<?php
	namespace App\Dcim;

	use Closure;

	use Core as C;

	use Cli as Cli;

	use Addon\Dcim;

	class Shell_Dcim extends Cli\Shell\Browser
	{
		const SHELL_HISTORY_FILENAME = '.dcim.history';

		const REGEX_LOCATION_NAME = "#^\"?([a-z0-9\-_. ]+)\"?$#i";
		const REGEX_LOCATION_NAME_WC = "#^\"?([a-z0-9\-_. *]+)\"?$#i";
		const REGEX_CABINET_NAME = "#^\"?([a-z0-9\-_. ]+)\"?$#i";
		const REGEX_CABINET_NAME_WC = "#^\"?([a-z0-9\-_. *]+)\"?$#i";
		const REGEX_EQUIPMENT_NAME = "#^\"?([a-z0-9\-_. ]+)\"?$#i";
		const REGEX_EQUIPMENT_NAME_WC = "#^\"?([a-z0-9\-_. *]+)\"?$#i";

		protected $_commands = array(
			'help', 'history',
			'ls', 'll', 'cd', 'pwd', 'search', 'find', 'exit', 'quit',
			'list' => array(
				'locations', 'cabinets', 'equipments',
			),
			'show' => array(
				'locations', 'cabinets', 'equipments',
			),
			'modify' => array(
				'equipment',
			),
			'refresh' => array(
				'caches'
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
			'ls' => "#^\"?([a-z0-9\-_/\\\\~. ]+)\"?$#i",
			'll' => "#^\"?([a-z0-9\-_/\\\\~. ]+)\"?$#i",
			'cd' => "#^\"?([a-z0-9\-_/\\\\~. ]+)\"?$#i",
			'search' => array(
				0 => array('locations', 'cabinets', 'equipments'),
				1 => "#^\"?([a-z0-9\-_.:* ]+)\"?$#i"
			),
			'find' => array(
				0 => "#^\"?([a-z0-9\-_. /~]+)\"?$#i",
				1 => array('locations', 'cabinets', 'equipments'),
				2 => "#^\"?([a-z0-9\-_.:* ]+)\"?$#i"
			),
			'list locations' => array(0 => self::REGEX_LOCATION_NAME_WC, 1 => array('|'), 2 => array('form', 'list')),
			'list cabinets' => array(0 => self::REGEX_CABINET_NAME_WC, 1 => array('front', 'rear', 'both'), 2 => array('summary', 'full'), 3 => array('|'), 4 => array('form', 'list')),
			'list equipments' => array(0 => self::REGEX_EQUIPMENT_NAME_WC, 1 => array('|'), 2 => array('form', 'list')),
			'show locations' =>  array(0 => self::REGEX_LOCATION_NAME_WC, 1 => array('|'), 2 => array('form', 'list')),
			'show cabinets' => array(0 => self::REGEX_CABINET_NAME_WC, 1 => array('front', 'rear', 'both'), 2 => array('summary', 'full'), 3 => array('|'), 4 => array('form', 'list')),
			'show equipments' => array(0 => self::REGEX_EQUIPMENT_NAME_WC, 1 => array('|'), 2 => array('form', 'list')),
			'modify equipment' => array(
				self::REGEX_EQUIPMENT_NAME,
				array('label', 'name', 'description', 'sn', 'serialnumber'),
				"#^\"?([[:print:]]+)\"?$#i"
			),
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
			'ls' => "Affiche la liste des éléments disponibles",
			'll' => "Alias de ls",
			'cd' => "Permet de naviguer dans l'arborescence",
			'pwd' => "Affiche la position actuelle dans l'arborescence",
			'search' => "Recherche avancée d'éléments. Utilisation: search [type] [recherche]",
			'find' => "Recherche avancée d'éléments. Utilisation: find [localisation|.] [type] [recherche]",
			'exit' => "Ferme le shell",
			'quit' => "Alias de exit",
			'list' => "Affiche un type d'éléments; Dépend de la localisation actuelle. Utilisation: list [locations|cabinets|equipments] [object] | [form|list]",
			'list locations' => "Affiche les informations d'une ou plusieurs localisations; Dépend de la localisation. Utilisation: list locations [object] | [form|list]",
			'list cabinets' => "Affiche les informations d'une ou plusieurs baies; Dépend de la localisation. Utilisation: list cabinets [name] [front|rear|both] [summury|full] | [form|list]",
			'list equipments' => "Affiche les informations d'un ou plusieurs équipements; Dépend de la localisation. Utilisation: list equipments [object] | [form|list]",
			'show' => "Affiche un type d'éléments; Ne dépend pas de la localisation actuelle. Utilisation: show [locations|cabinets|equipments] [object] | [form|list]",
			'show locations' => "Affiche les informations d'une ou plusieurs localisations. Utilisation: show locations [object] | [form|list]",
			'show cabinets' => "Affiche les informations d'une ou plusieurs baies. Utilisation: show cabinets [name] [front|rear|both] [summury|full] | [form|list]",
			'show equipments' => "Affiche les informations d'un ou plusieurs équipements. Utilisation: show equipments [object] | [form|list]",
			'modify' => "Modifier un objet DCIM",
			'modify equipment' => "Modifie les informations d'un équipement. Utilisation: modify equipment [label|SN|.] [name|description|serialnumber] [value]",
			'refresh caches' => "Rafraîchi les caches des objets du DCIM",
			'patchmanager' => "Lance la GUI de PatchManager",
		);

		/**
		  * @var Addon\Dcim\Service
		  */
		protected $_addonService;


		/**
		  * @param string|array|Core\Config $configuration
		  * @param string $server DCIM server key
		  * @param bool $autoInitialisation
		  * @return $this
		  */
		public function __construct($configuration, $server, $autoInitialisation = true)
		{
			parent::__construct($configuration);

			if(!$this->isOneShotCall()) {
				$printInfoMessages = true;
				ob_end_flush();
			}
			else {
				$printInfoMessages = false;
			}

			$this->_initAddon($server, $printInfoMessages);

			$this->_PROGRAM = new Shell_Program_Dcim($this, $this->_TERMINAL);

			foreach(array('ls', 'll', 'cd') as $cmd) {
				$this->_inlineArgCmds[$cmd] = Closure::fromCallable(array($this->_PROGRAM, 'shellAutoC_cd'));
				$this->_TERMINAL->setInlineArg($cmd, $this->_inlineArgCmds[$cmd]);
			}

			if($autoInitialisation) {
				$this->_init();
			}
		}

		protected function _initAddon($server, $printInfoMessages)
		{
			$Dcim_Orchestrator = Dcim\Orchestrator::getInstance($this->_CONFIG->DCIM);
			$this->_addonService = $Dcim_Orchestrator->debug($this->_addonDebug)->newService($server);

			if($printInfoMessages) {
				$adapterMethod = $this->_addonService->getMethod();
				C\Tools::e(PHP_EOL."Connection ".$adapterMethod." au DCIM @ ".$server." veuillez patienter ... ", 'blue');
			}

			try {
				$isReady = $this->_addonService->initialization();
			}
			catch(\Exception $e) {
				if($printInfoMessages) { C\Tools::e("[KO]", 'red'); }
				$this->error("Impossible de démarrer le service DCIM:".PHP_EOL.$e->getMessage(), 'red');
				exit;
			}

			if(!$isReady) {
				if($printInfoMessages) { C\Tools::e("[KO]", 'red'); }
				$this->error("Le service DCIM n'a pas pu être correctement initialisé", 'red');
				exit;
			}

			if($printInfoMessages) {
				C\Tools::e("[OK]", 'green');
			}

			$this->_refreshAddonCaches();
		}

		protected function _refreshAddonCaches()
		{
			$state = (bool) $this->_addonService->config->objectCaching;

			if($state)
			{
				$classes = array(
					'Addon\Dcim\Api_Location',
					'Addon\Dcim\Api_Cabinet',
					'Addon\Dcim\Api_Equipment',
				);

				foreach($classes as $class)
				{
					$this->EOL();
					$cache = $status = $this->_addonService->cache;

					/**
					  * Do not forget to enable cache
					  * Test if cache is enabled
					  */
					if($cache !== false && $cache->enable())
					{
						$this->print("Initialisation du cache pour les objets ".$class::OBJECT_NAME." ...", 'blue');
						$status = $cache->refresh($class::OBJECT_TYPE);
						$this->_TERMINAL->deleteMessage(1, true);

						if($status === true) {
							$this->print("Initialisation du cache pour les objets ".$class::OBJECT_NAME." [OK]", 'green');
						}
						else {
							$this->error("Initialisation du cache pour les objets ".$class::OBJECT_NAME." [KO]", 'red');
							$this->print("Désactivation du cache pour les objets ".$class::OBJECT_NAME." [OK]", 'orange');
							$cache->erase($class::OBJECT_TYPE);
						}
					}
				}
			}
			else {
				$this->error("Le cache des objets est désactivé, pour l'activer éditez la configuration", 'orange');
			}
		}

		protected function _preLauchingShell($welcomeMessage = true)
		{
			parent::_preLauchingShell($welcomeMessage);

			/**
			  * /!\ Les rapports pour le DCIM sont au format CSV
			  * L'utilisateur peut changer la conf du séparateur CSV depuis la GUI du DCIM
			  * Ce test permet de déterminer si la config de l'addon DCIM est correcte ou non
			  */
			$rootLocations = Dcim\Api_Location::getRootLocations();

			if(count($rootLocations) > 0 && count($rootLocations[0]) === 1) {
				$csvDelimiter = $this->_CONFIG->DCIM->preferences->report->csvDelimiter;
				$outputExample = current(array_keys($rootLocations[0]));
				$errorMsg = "La configuration de l'addon DCIM pour le séparateur CSV (DCIM > preferences > report > csvDelimiter) '".$csvDelimiter."' ";
				$errorMsg .= "ne semble pas correspondre aux préférences de l'utilisateur dans le DCIM.";
				$errorMsg .= PHP_EOL.PHP_EOL."Voici ce que retourne le DCIM: ".$outputExample;
				$this->error($errorMsg, 'red');
				exit;
			}
		}

		protected function _routeShellCmd($cmd, array $args)
		{
			$exit = false;

			switch($cmd)
			{
				case 'search': {
					array_unshift($args, DIRECTORY_SEPARATOR);
					$status = $this->_PROGRAM->printSearchObjects($args);
					break;
				}
				case 'find': {
					$status = $this->_PROGRAM->printSearchObjects($args);
					break;
				}
				case 'list locations': {
					$status = $this->_PROGRAM->listLocations($args);
					break;
				}
				case 'list cabinets': {
					$status = $this->_PROGRAM->listCabinets($args);
					break;
				}
				case 'list equipments': {
					$status = $this->_PROGRAM->listEquipments($args);
					break;
				}
				case 'show locations': {
					$status = $this->_PROGRAM->showLocations($args);
					break;
				}
				case 'show cabinets': {
					$status = $this->_PROGRAM->showCabinets($args);
					break;
				}
				case 'show equipments': {
					$status = $this->_PROGRAM->showEquipments($args);
					break;
				}
				case 'modify equipment': {
					$status = $this->_PROGRAM->modifyEquipment($args);
					break;
				}
				case 'refresh caches': {
					$this->_refreshAddonCaches();
					$status = true;
					break;
				}
				case 'patchmanager':
				{
					// @todo configurable
					$jnlp = ROOT_DIR ."/tmp/patchmanager.jnlp";

					if(!file_exists($jnlp))
					{
						$jnlpUrl = $this->_addonService->adapter->getJnlpUrl(64);

						// @todo configurable
						$options = array(
							"ssl" => array(
								"verify_peer" => false,
								"verify_peer_name" => false
							)
						);

						$context = stream_context_create($options);
						$ressource = fopen($jnlpUrl, 'r', false, $context);
						$status = file_put_contents($jnlp, $ressource);

						if($status === false) {
							$this->error("Impossible de télécharger le JNLP [".$jnlpUrl."]", 'red');
							break;
						}
					}

					$this->deleteWaitingMsg();
					$cmd = $this->_CONFIG->DEFAULT->sys->javawsCmd;
					$handle = popen($cmd.' "'.$jnlp.'" > /dev/null 2>&1', 'r');
					pclose($handle);
					break;
				}
				default: {
					$exit = parent::_routeShellCmd($cmd, $args);
				}
			}

			if(isset($status)) {
				$this->_routeShellStatus($cmd, $status);
			}

			return $exit;
		}

		protected function _moveToRoot()
		{
			if($this->_pathIds === null || $this->_pathApi === null)
			{
				$Dcim_Api_Location = new Dcim\Api_Location();
				$Dcim_Api_Location->setLocationLabel(DIRECTORY_SEPARATOR);

				$this->_pathIds[] = null;
				$this->_pathApi[] = $Dcim_Api_Location;
			}

			return parent::_moveToRoot();
		}

		public function browser(array &$pathIds, array &$pathApi, $path)
		{
			if(C\Tools::is('string', $path)) {
				$path = explode(DIRECTORY_SEPARATOR, $path);
			}

			/**
			  * Pourquoi ::class et non ::OBJECT_TYPE?
			  * Voir Application\Ipam->browser
			  */
			$cases = array(
				Dcim\Api_Location::OBJECT_TYPE => array(
					Dcim\Api_Location::class => 'getSubLocationId',
					Dcim\Api_Cabinet::class => 'getCabinetId',
				),
				Dcim\Api_Cabinet::OBJECT_TYPE => array(
					Dcim\Api_Equipment::class => 'getEquipmentId',
				),
			);

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
						$objectType = $objectApi::OBJECT_TYPE;

						if(array_key_exists($objectType, $cases))
						{
							foreach($cases[$objectType] as $objectClass => $objectMethod)
							{
								$objectId = call_user_func(array($objectApi, $objectMethod), $part);

								if($objectId !== false) {
									$pathApi[] = $objectClass::factory($objectId);
									$pathIds[] = $objectId;
									break;
								}
							}
						}
					}
				}
			}
		}
	}