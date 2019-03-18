<?php
	namespace Addon\Dcim;

	use ArrayObject;

	class Connector_Rest_Writer extends Connector_Rest_Reader
	{
		const SIDE_FRONT = 'front';
		const SIDE_REAR = 'rear';

		const FIBER_LC = 'fiber_lc';
		const FIBER_SC = 'fiber_sc';
		const FIBER_MM = 'fiber_multimode';
		const FIBER_SM = 'fiber_monomode';

		const ETHERNET_RJ45 = 'ethernet_rj45';
		const ETHERNET_CAT6 = 'ethernet_cat6';

		const CABLE_SIMPLEX = 'cable_simplex';
		const CABLE_DUPLEX = 'cable_duplex';


		// ---------- Template -----------
		public function templateExists($templateName)
		{
			$args = $this->getArgs(array($templateName));
			$result = $this->_soapInstances['resolver']->templateExist($args)->return;
			return ($this->isValidReturn($result) && $result === 'true');
		}
		// -------------------------------
		
		// ---------- Equipment ----------
		public function addEquipmentToCabinetId($cabinetId, $side, $positionU, $positionX, $templateName, $label = null, $description = null)
		{
			if($label === null) {
				$label = $templateName;
			}

			if($description === null) {
				$description = '';
			}

			$args = $this->getArgs(array($cabinetId, $templateName, $side, $label, $description, $positionU, $positionX));
			return $this->_soapInstances['equipments']->addEquipmentInCabinet($args)->return;	// return equipmentId
		}

		public function addEquipmentToSlotId($slotId, $templateName, $label = null, $description = null, $side = self::SIDE_FRONT)
		{
			if($label === null) {
				$label = $templateName;
			}

			if($description === null) {
				$description = '';
			}

			$side = ($side === self::SIDE_FRONT) ? (self::SIDE_REAR) : (self::SIDE_FRONT);		// voir documentation

			$args = $this->getArgs(array($slotId, $templateName, $label, $description, $side));
			return $this->_soapInstances['equipments']->addEquipmentInSlot($args)->return;		// return equipmentId
		}

		public function updateEquipmentInfos($equipmentId, $label, $description = null)
		{
			if($description === null) {
				$description = '';
			}

			$args = $this->getArgs(array($equipmentId, $label, $description));
			$result = $this->_soapInstances['equipments']->modifyEquipment($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}

		public function removeEquipment($equipmentId)
		{
			$args = $this->getArgs(array($equipmentId));
			$result = $this->_soapInstances['equipments']->deleteEquipmentCascade($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}
		// -------------------------------

		// ------------ Slot -------------
		public function updateSlotInfos($slotId, $label = null)
		{
			/**
			  * Modifies a slot's label. If the value of label is null then no
			  * change is made to the label.
			  */

			$args = $this->getArgs(array($slotId, $label));
			$result = $this->_soapInstances['equipments']->modifySlot($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}
		// -------------------------------

		// ------------ Port -------------
		public function updatePortInfos($portId, $label = null, $color = null)
		{
			/**
			  * Modifies a port's label and/or color. If the value of label is
			  * null then no change is made to the label . If the value of color
			  * is null then no change is made to the color.
			  */

			$args = $this->getArgs(array($portId, $label, $color));
			return $this->_soapInstances['equipments']->modifyPort($args)->return;
		}

		public function disconnectPort($portId)
		{
			$args = $this->getArgs(array($portId));
			return $this->_soapInstances['cables']->disconnectPort($args)->return;
		}
		// -------------------------------

		// ------------ Cable ------------
		public function addCable($locationId, $templateName, $label, $description = null)
		{
			if($description === null) {
				$description = '';
			}

			$args = $this->getArgs(array($locationId, $templateName, $label, $description));
			return $this->_soapInstances['cables']->addCable($args)->return;
		}

		public function connectCable($cableId, $portId)
		{
			$args = $this->getArgs(array($cableId, $portId));
			$result = $this->_soapInstances['cables']->connectCable($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}

		public function updateCableInfos($cableId, $label, $description = '')
		{
			$args = $this->getArgs(array($cableId, $label, $description));
			return $this->_soapInstances['cables']->updateCable($args)->return;
		}

		// -------------------------------

		// ---------- User Attrs ---------
		public function setUserAttrByEquipmentId($equipmentId, $userAttrName, $userAttrValue)
		{
			if($userAttrValue === null) {
				$userAttrValue = "";
			}
			elseif(is_array($userAttrValue)) {
				$userAttrValue = implode(self::USER_ATTR_LIST_SEPARATOR, $userAttrValue);
			}

			$args = $this->getArgs(array('Equipment', $equipmentId, $userAttrName, $userAttrValue));
			$result = $this->_soapInstances['userAttrs']->setAttribute($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}

		public function setUserAttrByPortId($portId, $userAttrName, $userAttrValue)
		{
			if($userAttrValue === null) {
				$userAttrValue = "";
			}
			elseif(is_array($userAttrValue)) {
				$userAttrValue = implode(self::USER_ATTR_LIST_SEPARATOR, $userAttrValue);
			}
			
			$args = $this->getArgs(array($portId, $userAttrName, $userAttrValue));
			$result = $this->_soapInstances['userAttrs']->setPortAttribute($args)->return;
			return ($result === 'success') ? (true) : ($result);
		}
		// -------------------------------
	}