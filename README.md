# PHP-CLI SHELL for PATCHMANAGER

This repository is the addon for PHP-CLI SHELL about PATCHMANAGER service.  
You have to use base PHP-CLI SHELL project that is here: https://github.com/cloudwatt/php-cli-shell_base


# PRE-REQUIS

#### PATCHMANAGER
* Import profiles which are in ressources/dcim
    * formats: ressources/dcim/formats
	* Reports: ressources/dcim/reports
	* Searches: ressources/dcim/searches


# INSTALLATION

#### APT PHP
__*https://launchpad.net/~ondrej/+archive/ubuntu/php*__
* add-apt-repository ppa:ondrej/php
* apt-get update
* apt install php7.1-cli php7.1-mbstring php7.1-readline php7.1-soap  
__Do not forget to install php7.1-soap__

#### REPOSITORIES
* git clone https://github.com/cloudwatt/php-cli-shell_base
* git checkout tags/v1.0
* git clone https://github.com/cloudwatt/php-cli-shell_patchmanager
* git checkout tags/v1.0
* Merge these two repositories
	
#### CONFIGURATION FILE
* mv config.json.example config.json
* vim config.json
    * servers field contains all PatchManager server addresses which must be identified by custom key [PM_SERVER_KEY]  
	  __server key must be unique and you will use it on next steps. You have an example in config file__
	* userAttrs field contains all custom attributes which must be created on your PatchManager  
	  __If you have a serial number custom attribute, change [PM_ATTR_SN] with the name of this attribute__

#### PHP LAUNCHER FILE
* mv dcim.php.example patchmanager.php
* vim patchmanager.php
    * Change [PM_SERVER_KEY] with the key of your PatchManager server in configuration file

#### CREDENTIALS FILE
/!\ For security reason, use a read only account!
__*Change informations which are between []*__
* vim credentialsFile
    * read -sr USER_PASSWORD_INPUT
    * export DCIM_[PM_SERVER_KEY]_LOGIN=[YourLoginHere]
    * export DCIM_[PM_SERVER_KEY]_PASSWORD=$USER_PASSWORD_INPUT  
	  __Change [PM_SERVER_KEY] with the key of your PatchManager server in configuration file__


# EXECUTION

#### SHELL
Launch PHP-CLI Shell for PatchManager service
* source credentialsFile
* php patchmanager.php

#### COMMAND
Get command result in order to handle with your OS shell.  
/!\ The result is JSON so you can use JQ https://stedolan.github.io/jq/  
__*Change informations which are between []*__
* source credentialsFile
* php patchmanager.php "[myCommandHere]"