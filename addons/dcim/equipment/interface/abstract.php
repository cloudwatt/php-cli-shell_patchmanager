<?php
	namespace Addon\Dcim;

	use Core as C;

	abstract class Equipment_Interface_Abstract
	{
		const INT_ALLOWED_REGEXP = array();

		protected $_attributes = null;
		protected $_datas = array();


		abstract protected function _getKey();

		protected function _skipInt()
		{
			if(count(static::INT_ALLOWED_REGEXP) > 0)
			{
				$intKey = $this->_getKey();

				foreach(static::INT_ALLOWED_REGEXP as $regexp) {
					if(preg_match('#'.$regexp.'#i', $intKey)) return false;
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
				$regex = "^([a-z]*)-?(([0-9]{1,4}/){0,}([0-9]{1,4}))(".preg_quote(static::INT_SEPARATOR, '#')."([0-9]))?$";

				$matches = array();
				preg_match('#'.$regex.'#i', $this->_getKey(), $matches);

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
	}