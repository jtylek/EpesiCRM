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

class Utils_Tray_Slot {
	/**
	 * @var Utils_Tray_Box
	 */
	protected $box;
	protected $module;
	protected $function = null;
	protected $tab;
	protected $id;
	protected $name;
	protected $weight = 5;
	protected $filters = [];
	protected $transCallbacks = [];
	protected $crits = null;
	protected $count = null;
	protected $visible = true;
	protected $ignoreLimit = false;
	
	public function __construct(Utils_Tray_Box $box, $module, $tab, $settings, $transCallbacks=[]) {
		$this->setBox($box);
		$this->setModule($module);
		$this->setTab($tab);
		$this->setTransCallbacks($transCallbacks);
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
	
	public function isVisible($hideEmpty = true) {
		return $this->visible && (!$this->isEmpty() || !$hideEmpty);
	}
	
	public function isEmpty() {
		return empty($this->getCount());
	}
	
	public function getCount() {
		return $this->count?? $this->count = Utils_RecordBrowserCommon::get_records_count($this->getTab(), $this->getCrits());
	}

	public function getBox() {
		return $this->box;
	}

	public function setBox($box) {
		$this->box = $box;
		return $this;
	}

	public function getId() {
		return $this->id;
	}

	public function setId($id) {
		$this->id = Utils_RecordBrowserCommon::get_field_id($id);;
		return $this;
	}

	public function getName() {
		return $this->name;
	}

	public function setName($name) {
		$this->setId($this->name = $name);
		
		return $this;
	}

	public function getWeight() {
		return $this->weight;
	}

	public function setWeight($weight) {
		$this->weight = $weight;
		return $this;
	}

	public function getFilters() {
		return $this->filters;
	}

	public function setFilters($filters) {
		$this->filters = $filters;
		return $this;
	}

	public function getCrits() {
		if ($this->crits) {
			return is_callable($this->crits)? call_user_func($this->crits): $this->crits;
		}
		
		$this->count = null;
		
		$crits = [];
		foreach ($this->getFilters() as $field=>$val) {
			$trans_callback = $this->getTransCallback($field);

			$record_crits = is_callable($trans_callback)? call_user_func($trans_callback, $val, $field, $this->getTab()): [$field=>$val];

			$crits = Utils_RecordBrowserCommon::merge_crits($crits, $record_crits);
		}
		
// 		foreach ($crits as $k=>$c) if ($c==='__PERSPECTIVE__') {
// 			$crits[$k] = explode(',',trim(CRM_FiltersCommon::get(),'()'));
// 			if (isset($crits[$k][0]) && $crits[$k][0]=='') unset($crits[$k]);
// 		}

		return $this->crits = $crits;
	}

	public function setCrits($crits) {
		$this->crits = $crits;
		return $this;
	}
	
	public function getTransCallback($field) {
		return $this->transCallbacks[$field]?? [];
	}

	public function getTransCallbacks() {
		return $this->transCallbacks;
	}

	public function setTransCallbacks($transCallbacks) {
		$this->transCallbacks = $transCallbacks;
		return $this;
	}
	
	public function getHtml() {
		$tray_count_width = ($this->getCount() > 99)? 'style="width:28px;"':'';
		
		return '<a '.Base_BoxCommon::create_href(null, $this->getModule(), $this->getFunction(), [$this->getTab()], null, ['tray_slot'=>$this->getId()]).'><div class="Utils_Tray__slot">'.
				Utils_TooltipCommon::create('<img src="'.$this->getIcon().'">
					<div class="Utils_Tray__slot_count" '  .$tray_count_width . '>' . $this->getCount().'</div><div class="Utils_Tray__slot_name">'._V($this->getName()).'</div>', $this->getTip()).'</div></a>';
	}
	
	public function getTip() {
		return __('Click to view %s items from %s<br><br>%d item(s)',[_V($this->getName()),_V($this->getBox()->getTitle()), $this->getCount()]);
	}
	
	public function getIcon() {
		$limits = [10=>'pile3', 5=>'pile2', 1=>'pile1', 0=>'pile0'];
		
		foreach ($limits as $limit=>$file) {
			if ($this->getCount() >= $limit) break;
		}
		
		return Base_ThemeCommon::get_template_file('Utils_Tray', $file.'.png');
	}

	public function getModule() {
		return $this->module;
	}

	public function setModule($module) {
		if (is_array($module)) {
			$this->setFunction($module[1]);
			$module = $module[0];			
		}
		$this->module = $module;
		return $this;
	}
	
	public function getFunction() {
		return $this->function;
	}

	public function setFunction($function) {
		$this->function = $function;
		return $this;
	}

	public function getTab() {
		return $this->tab;
	}

	public function setTab($tab) {
		$this->tab = $tab;
		return $this;
	}
	
	public function filtersExist() {
		return $this->filters? true: false;		
	}
		
	public function filtersMatch() {
		if (!$this->filtersExist()) return false;
		
		$filtered = false;
		foreach ($_REQUEST as $id=>$value) {
			if (stripos($id, 'filter__')!==false) {
				$filtered = true;
				break;
			}
		}
		
		if (!$filtered) return false;
		
		$filter_changed = false;
		foreach ($this->getFilters() as $id=>$value) {
			if ($_REQUEST['filter__'.$id]!= $value) {
				$filter_changed=true;
				break;
			}
		}
		
		return !$filter_changed;
	}

	public function getIgnoreLimit() {
		return $this->ignoreLimit;
	}
	
	public function setIgnoreLimit($ignoreLimit) {
		$this->ignoreLimit = $ignoreLimit;
		return $this;
	}
	
	public function setVisible($visible) {
		$this->visible = $visible;
		
		return $this;
	}
}

?>
