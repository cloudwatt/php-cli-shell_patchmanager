<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Interface_Abstract implements \ArrayAccess, \IteratorAggregate, \Countable
	{
		const INT_ALLOWED_REGEXP = array();

		/**
		  * @var Addon\Dcim\Equipment
		  */
		protected $_equipment = null;

		/**
		  * @var array
		  */
		protected $_attributes = null;

		/**
		  * @var array
		  */
		protected $_datas = array();


		/**
		  * @param Addon\Dcim\Equipment $equipment
		  * @return $this
		  */
		public function __construct(Equipment $equipment)
		{
			$this->_equipment = $equipment;
		}

		/**
		  * @return string
		  */
		abstract protected function _getInterfaceKey();

		/**
		  * Conserne à la fois l'interface physique et virtuelle
		  * L'index doit être délimité par un séparateur physique
		  *
		  * @return false|int
		  */
		abstract protected function _getInterfaceIndex();

		/**
		  * @return string
		  */
		abstract protected function _getInterfaceName();

		/**
		  * @return Addon\Dcim\Equipment
		  */
		public function getEquipment()
		{
			return $this->_equipment;
		}

		/**
		  * @param null|string $name
		  * @return string
		  */
		protected function _nameToKey($name = null)
		{
			if($name === null) {
				$name = $this->_getInterfaceName();
			}

			return mb_strtolower($name);
		}

		/**
		  * @return array
		  */
		public function getDataKeys()
		{
			return array_keys($this->getDatas());
		}

		/**
		  * @return bool
		  */
		protected function _skipInterface()
		{
			if(count(static::INT_ALLOWED_REGEXP) > 0)
			{
				$interfaceKey = $this->_getInterfaceKey();

				foreach(static::INT_ALLOWED_REGEXP as $regexp)
				{
					if(preg_match('#'.$regexp.'#i', $interfaceKey)) {
						return false;
					}
				}

				return true;
			}
			else {
				return false;
			}
		}

		/**
		  * /!\ Le traitement est insensible à la casse donc on peut utiliser le nom ou la clé
		  */
		public function getAttributes()
		{
			if($this->_attributes === null)
			{
				$regex = "^([a-z]*)-?(([0-9]{1,4}/){0,}([0-9]{1,4}))(".preg_quote(static::INTERFACE_SEPARATOR, '#')."([0-9]))?$";

				$matches = array();
				preg_match('#'.$regex.'#i', $this->_getInterfaceKey(), $matches);

				$intInfos = array();
				$intInfos['intType'] = (isset($matches[1])) ? ($matches[1]) : (null);

				$intInfos['intId'] = (isset($matches[2])) ? ($matches[2]) : (null);
				if(isset($matches[5])) { $intInfos['intId'] .= $matches[5]; }

				if(isset($matches[6])) {
					$index = $matches[6];
				}
				elseif(isset($matches[4])) {
					$index = $matches[4];
				}

				$intInfos['intIndex'] = (isset($index)) ? ($index) : (null);
				$intInfos['intIndex2'] = (isset($index)) ? (str_pad($index, 2, 0, STR_PAD_LEFT)) : (null);

				$this->_attributes = $intInfos;
				C\Tools::merge($this->_datas, $intInfos);
			}

			return $this->_attributes;
		}

		public function offsetSet($offset, $value)
		{
		}

		public function offsetExists($offset)
		{
			return isset($this->{$offset});
		}

		public function offsetUnset($offset)
		{
		}

		public function offsetGet($offset)
		{
			/**
			  * Permet d'éviter de retourner les variables qu'expose __get comme key ou name
			  */
			$datas = $this->getDatas();
			$key = $this->_nameToKey($offset);
			return (array_key_exists($key, $datas)) ? ($datas[$key]) : (null);
		}

		public function getIterator()
		{
			$datas = $this->getDatas();
			return new \ArrayIterator($datas);
		}

		public function count()
		{
			$datas = $this->getDatas();
			return count($datas);
		}

		protected function _indexIsValid($index)
		{
			return C\Tools::is('int&&>=0', $index);
		}

		public function __isset($name)
		{
			$datas = $this->getDatas();
			$key = $this->_nameToKey($name);
			return array_key_exists($key, $datas);
		}

		public function __get($name)
		{
			switch($name)
			{
				case 'key': {
					return $this->_getInterfaceKey();
				}
				case 'index': {
					return $this->_getInterfaceIndex();
				}
				case 'name': {
					return $this->_getInterfaceName();
				}
				case 'equipment': {
					return $this->_equipment;
				}
				default: {
					$datas = $this->getDatas();
					$key = $this->_nameToKey($name);
					return (array_key_exists($key, $datas)) ? ($datas[$key]) : (false);
				}
			}
		}
	}