<?php
	namespace App\Dcim;

	use Closure;

	use Core as C;

	use Cli as Cli;

	use Addon\Dcim;

	class Shell_Dcim extends Cli\Shell\Browser
	{
		const SHELL_HISTORY_FILENAME = '.dcim.history';

		/**
		  * @var Addon\Dcim\Connector\Abstract
		  */
		protected $_DCIM;

		protected $_commands = array(
			'help', 'history',
			'ls', 'll', 'cd', 'pwd', 'search', 'find', 'exit', 'quit',
			'list' => array(
				'location', 'cabinet', 'equipment',
			),
			'show' => array(
				'location', 'cabinet', 'equipment',
				/*'system', 'sys', 'server',
				'network', 'net', 'appliance'*/
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
				0 => array('location', 'cabinet', 'equipment'),
				1 => "#^\"?([a-z0-9\-_.:* ]+)\"?$#i"
			),
			'find' => array(
				0 => "#^\"?([a-z0-9\-_. /~]+)\"?$#i",
				1 => array('location', 'cabinet', 'equipment'),
				2 => "#^\"?([a-z0-9\-_.:* ]+)\"?$#i"
			),
			'list location' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'list cabinet' => array(0 => "#^\"?([a-z0-9\-_. ]+)\"?$#i", 1 => array('front', 'rear', 'both'), 2 => array('summary', 'full')),
			'list equipment' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'show location' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'show cabinet' => array(0 => "#^\"?([a-z0-9\-_. ]+)\"?$#i", 1 => array('front', 'rear', 'both'), 2 => array('summary', 'full')),
			'show equipment' => "#^\"?([a-z0-9\-_. ]+)\"?$#i",
			'modify equipment' => array(
				"#^\"?([a-z0-9\-_. ]+)\"?$#i",
				array('label', 'name', 'description', 'sn', 'serialnumber'),
				"#^\"?(.+)\"?$#i"
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
			'list' => "Affiche un type d'éléments; Dépend de la localisation actuelle. Utilisation: list [location|cabinet|equipment] [object]",
			'list location' => "Affiche les informations d'une localisation; Dépend de la localisation",
			'list cabinet' => "Affiche les informations d'une baie; Dépend de la localisation. Utilisation: list cabinet [name] [front|rear|both] [summury|full]",
			'list equipment' => "Affiche les informations d'un équipement; Dépend de la localisation",
			'show' => "Affiche un type d'éléments; Ne dépend pas de la localisation actuelle. Utilisation: show [location|cabinet|equipment] [object]",
			'show location' => "Affiche les informations d'une localisation",
			'show cabinet' => "Affiche les informations d'une baie. Utilisation: show cabinet [name] [front|rear|both] [summury|full]",
			'show equipment' => "Affiche les informations d'un équipement",
			'modify equipment' => "Modifie les informations d'un équipement. Utilisation: modify equipment [label|SN|.] [name|description|serialnumber] [value]",
			/*'show system' => "Affiche les informations d'un équipement",
			'show server' => "Alias de show system",
			'show sys' => "Alias de show system",
			'show network' => "Affiche les informations d'un équipement",
			'show appliance' => "Alias de show network",
			'show net' => "Alias de show network",*/
			'refresh caches' => "Rafraîchi les caches des objets du DCIM",
			'patchmanager' => "Lance la GUI de PatchManager",
		);

		/**
		  * @var bool
		  */
		protected $_objectCaching = false;


		public function __construct($configFilename, $server, $autoInitialisation = true)
		{
			parent::__construct($configFilename);

			if(!$this->isOneShotCall()) {
				$printInfoMessages = true;
				ob_end_flush();
			}
			else {
				$printInfoMessages = false;
			}

			try {
				// /!\ Compatible mono-serveur donc $server ne peut pas être un array
				$DCIM = new Dcim\Connector(array($server), $printInfoMessages, null, $this->_addonDebug);
			}
			catch(\Exception $e) {
				$this->error("Impossible de se connecter ou de s'authentifier au service DCIM:".PHP_EOL.$e->getMessage(), 'red');
				exit;
			}

			$this->_DCIM = $DCIM->getDcim();
			Dcim\Api_Abstract::setDcim($this->_DCIM);

			$this->_objectCaching = (bool) $DCIM->getConfig()->objectCaching;
			$this->_initAllApiCaches($this->_objectCaching);

			$this->_PROGRAM = new Shell_Program_Dcim($this, $this->_TERMINAL);

			foreach(array('ls', 'll', 'cd') as $cmd) {
				$this->_inlineArgCmds[$cmd] = Closure::fromCallable(array($this->_PROGRAM, 'shellAutoC_cd'));
				$this->_TERMINAL->setInlineArg($cmd, $this->_inlineArgCmds[$cmd]);
			}

			if($autoInitialisation) {
				$this->_init();
			}
		}

		protected function _initAllApiCaches($state)
		{
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
					$class::cache(true);

					$this->print("Initialisation du cache pour les objets ".$class::OBJECT_NAME." ...", 'blue');
					$status = $class::refreshCache($this->_DCIM);
					$this->_TERMINAL->deleteMessage(1, true);

					if($status === true) {
						$this->print("Initialisation du cache pour les objets ".$class::OBJECT_NAME." [OK]", 'green');
					}
					else {
						$this->error("Initialisation du cache pour les objets ".$class::OBJECT_NAME." [KO]", 'red');
						$class::cache(false);
						$this->print("Désactivation du cache pour les objets ".$class::OBJECT_NAME." [OK]", 'orange');
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
				case 'list location': {
					$status = $this->_PROGRAM->printLocationInfos($args, true);
					break;
				}
				case 'list cabinet': {
					$status = $this->_PROGRAM->printCabinetInfos($args, true);
					break;
				}
				case 'list equipment': {
					$status = $this->_PROGRAM->printEquipmentInfos($args, true);
					break;
				}
				case 'show location': {
					$status = $this->_PROGRAM->printLocationInfos($args, false);
					break;
				}
				case 'show cabinet': {
					$status = $this->_PROGRAM->printCabinetInfos($args, false);
					break;
				}
				case 'show equipment': {
					$status = $this->_PROGRAM->printEquipmentInfos($args, false);
					break;
				}
				case 'modify equipment': {
					$status = $this->_PROGRAM->modifyEquipment($args);
					break;
				}
				case 'refresh caches': {
					$this->_initAllApiCaches($this->_objectCaching);
					$status = true;
					break;
				}
				case 'patchmanager':
				{
					// @todo configurable
					$jnlp = ROOT_DIR ."/tmp/patchmanager.jnlp";

					if(!file_exists($jnlp))
					{
						$jnlpUrl = $this->_DCIM->getJnlpUrl(64);

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

			$cases = array(
				'Addon\Dcim\Api_Location' => array(
					'Addon\Dcim\Api_Location' => 'getSubLocationId',
					'Addon\Dcim\Api_Cabinet' => 'getCabinetId',
				),
				'Addon\Dcim\Api_Cabinet' => array(
					'Addon\Dcim\Api_Equipment' => 'getEquipmentId',
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
						$objectApiClass = get_class($objectApi);

						if(array_key_exists($objectApiClass, $cases))
						{
							foreach($cases[$objectApiClass] as $objectClass => $objectMethod)
							{
								$objectId = call_user_func(array($objectApi, $objectMethod), $part);

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