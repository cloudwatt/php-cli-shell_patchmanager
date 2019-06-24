<?php
	namespace Addon\Dcim;

	abstract class Equipment_Interface_Virtual extends Equipment_Interface_Abstract
	{
		const INTERFACE_SEPARATOR = '.';

		/**
		  * @var Addon\Dcim\Equipment_Port
		  */
		protected $_port = null;


		/**
		  * @return string
		  */
		abstract protected function _getInterfaceId();

		/**
		  * Conserne seulement l'interface virtuelle
		  * Les labels doivent être délimitées par un séparateur virtuel
		  *
		  * @return array
		  */
		abstract protected function _getInterfaceLabels();

		/**
		  * @return bool
		  */
		public function hasPort()
		{
			return $this->_port !== null;
		}

		/**
		  * @return false|Addon\Dcim\Equipment_Port
		  */
		public function retrievePort()
		{
			if($this->hasPort()) {
				return $this->_port;
			}
			else {
				return false;
			}
		}

		/**
		  * @return string
		  */
		public function getPortKey()
		{
			if($this->hasPort()) {
				$separator = $this->_port::INTERFACE_SEPARATOR;
			}
			else {
				$separator = $this->_equipment::PORT_INTERFACE_SEPARATOR;
			}

			$intKey = $this->getInterfaceKey();
			$intIndex = $this->getInterfaceIndex();

			/**
			  * Un port n'a aucune raison d'avoir des labels (attributs)
			  * Un port se limite donc à portKey et potentiellement portIndex
			  *
			  * Si un port ne respecte pas ce formatage alors il faudra le coder
			  * Ne pas rajouter interface labels qui correspond à des informations virtuelles
			  */

			return ($intIndex !== false) ? ($intKey.$separator.$intIndex) : ($intKey);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'id': {
					return $this->_getInterfaceId();	// Return string
				}
				case 'labels': {
					return $this->_getInterfaceLabels();
				}
				default: {
					return parent::__get($name);
				}
			}
		}
	}