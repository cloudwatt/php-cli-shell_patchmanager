<?php
	class Dcim_Api_Cable extends Dcim_Api_Abstract
	{
		const OBJECT_TYPE = 'cable';
		const REPORT_NAMES = array(
				'label' => 'CW - TOOLS-CLI - CableLabel',
				'equipment' => 'CW - TOOLS-CLI - CableEquipment',
		);


		public function cableIdIsValid($cableId)
		{
			return $this->objectIdIsValid($cableId);
		}

		public function hasCableId()
		{
			return $this->hasObjectId();
		}

		public function getCableId()
		{
			return $this->getObjectId();
		}

		public function cableExists()
		{
			return $this->objectExists();
		}

		public function getCableLabel()
		{
			return $this->getObjectLabel();
		}
	}