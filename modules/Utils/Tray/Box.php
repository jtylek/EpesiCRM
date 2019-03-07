<?php 

/*
* @author Georgi Hristov <ghristov@gmx.de>
* @copyright Copyright &copy; 2019, Georgi Hristov
* @license MIT
* @version 2.0
* @package epesi-tray
*
*/

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_Tray_Box {
	protected $title;
	protected $weight = 5;
	/**
	 * @var Utils_Tray_Slot[]
	 */
	protected $slots = [];
	protected $slotsLimit = false;
	protected $limitSlotsDisabled = false;
	protected $ignoreLimit = false;
	
	public function __construct($settings) {
		$this->setPropertiesFromArray($settings);
	}
	
	public function setPropertiesFromArray($settings) {
		if (!is_array($settings)) return;
		
		foreach ($settings as $key=>$value) {
			$key = str_ireplace(' ', '', ucwords(str_ireplace('_', ' ', trim($key, '__'))));
			
			if (is_callable([$this, 'set' . $key]))
				call_user_func([$this, 'set' . $key], $value);
		}
	}

	public function getTitle() {
		return $this->title;
	}

	public function setTitle($title) {
		$this->title = $title;
		return $this;
	}

	public function getWeight() {
		return $this->weight;
	}

	public function setWeight($weight) {
		$this->weight = $weight;
		return $this;
	}

	/**
	 * @return Utils_Tray_Slot[]
	 */
	public function getSlots() {
		if (!$this->getSlotsLimit()) return $this->slots;
		
		$ret = [];
		$count = 0;
		foreach ($this->slots as $slot) {
			if ($count >= $this->getSlotsLimit() && !$slot->getIgnoreLimit()) continue;
			
			$count++;
			
			$ret[] = $slot;
		}
		return $ret;
	}

	public function setSlots($module, $tab, $slotDefinitions, $transCallbacks= []) {
		foreach ($slotDefinitions as $definition) {
			$slotModule = $definition['__module__']?? $module;
			if ($slotFunction = $definition['__func__']?? null) {
				$slotModule = [$slotModule, $slotFunction];
			}		
			
			$slot = new Utils_Tray_Slot($this, $slotModule, $tab, $definition, $transCallbacks);
			
			$this->slots[] = $slot;
		}
		
		uasort($this->slots, function (Utils_Tray_Slot $a, Utils_Tray_Slot $b) {
			return $a->getWeight() - $b->getWeight();
		});

		return $this;
	}	
	
	public function getSlotsLimit() {
		if ($this->limitSlotsDisabled) return false;
		
		return $this->slotsLimit;
	}

	public function setSlotsLimit($slotsLimit) {
		$this->slotsLimit = is_numeric($slotsLimit)? $slotsLimit: false;
		return $this;
	}

	public function getLimitSlotsDisabled() {
		return $this->limitSlotsDisabled;
	}

	public function setLimitSlotsDisabled($limitSlotsDisabled) {
		$this->limitSlotsDisabled = $limitSlotsDisabled;
		return $this;
	}

	public function getIgnoreLimit() {
		return $this->ignoreLimit;
	}

	public function setIgnoreLimit($ignoreLimit) {
		$this->ignoreLimit = $ignoreLimit;
		return $this;
	}
}

?>
