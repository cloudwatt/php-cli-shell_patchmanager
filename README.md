PHP-CLI SHELL for PATCHMANAGER
-------------------

This repository is the addon for PHP-CLI SHELL about PATCHMANAGER service.  
You have to use base PHP-CLI SHELL project that is here: https://github.com/cloudwatt/php-cli-shell_base


REQUIREMENTS
-------------------

#### PATCHMANAGER
* Import profiles which are in addons/dcim/ressources
    * formats: ressources/dcim/formats
	* Reports: ressources/dcim/reports
	* Searches: ressources/dcim/searches  

__*/!\ Do not rename custom profiles*__  
__*/!\ Version 2.0 add new profiles!*__


INSTALLATION
-------------------

#### APT PHP
Ubuntu only, you can get last PHP version from this PPA:  
__*https://launchpad.net/~ondrej/+archive/ubuntu/php*__
* add-apt-repository ppa:ondrej/php
* apt update

You have to install a PHP version >= 7.1:
* apt install php7.3-cli php7.3-mbstring php7.3-readline php7.3-soap php7.3-curl

For MacOS users which use PHP 7.3, there is an issue with PCRE.
You have to add this configuration in your php.ini:
```ini
pcre.jit=0
```
*To locate your php.ini, use this command: php -i | grep "Configuration File"*


## USE PHAR

Download last PHAR release and its key from [releases](https://github.com/cloudwatt/php-cli-shell_patchmanager/releases)

![wizard](documentation/readme/wizard.gif)

Wizard help:
`$ php php-cli-shell.phar --help`

Create PATCHMANAGER configuration with command:
`$ php php-cli-shell.phar configuration:application:factory dcim`  
*For more informations about configuration file, see 'CONFIGURATION FILE' section*

Create PATCHMANAGER launcher with command:
`$ php php-cli-shell.phar launcher:application:factory dcim`

__*The PHAR contains all PHP-CLI SHELL components (Base, DCIM, IPAM and Firewall)*__


## USE SOURCE

#### REPOSITORIES
* git clone https://github.com/cloudwatt/php-cli-shell_base
* git checkout tags/v2.1.2
* git clone https://github.com/cloudwatt/php-cli-shell_patchmanager
* git checkout tags/v2.1.2
* Merge these two repositories
	
#### CONFIGURATION FILE
* mv configurations/dcim.json.example configurations/dcim.json
* vim configurations/dcim.json
    * servers field contains all PatchManager server which must be identified by custom key [DCIM_SERVER_KEY]  
	  __server key must be unique and you will use it on next steps. You have an example in config file__
	* userAttrs section contains all custom attributes which must be created in your PatchManager  
	  __If you have a serial number custom attribute, change [PM_ATTR_SN] with the name of this attribute otherwise leave false__
	* preferences section contains all options about PatchManager  
	  __CSV delimiter option must be identical between PHP-CLI configuration and PatchManager user preferences__
* Optionnal
    * You can create user configuration files to overwrite some configurations  
	  These files will be ignored for commits, so your user config files can not be overwrited by a futur release
	* mv configurations/dcim.user.json.example configurations/dcim.user.json
	* vim configurations/dcim.user.json  
	  Change configuration like browserCmd or DCIM preferences
	* All *.user.json files are ignored by .gitignore

#### PHP LAUNCHER FILE
* mv dcim.php.example patchmanager.php
* vim patchmanager.php
    * Change [DCIM_SERVER_KEY] with the key of your PatchManager server in configuration file


EXECUTION
-------------------

#### CREDENTIALS FILE
/!\ For security reason, you can use a read only account!  
__*Change informations which are between []*__
* vim credentialsFile
    * read -sr USER_PASSWORD_INPUT
    * export DCIM_[DCIM_SERVER_KEY]_LOGIN=[YourLoginHere]
    * export DCIM_[DCIM_SERVER_KEY]_PASSWORD=$USER_PASSWORD_INPUT  
          __Change [DCIM_SERVER_KEY] with the key of your PatchManager server in configuration file__

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
