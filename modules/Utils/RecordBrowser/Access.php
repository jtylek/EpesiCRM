<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_Access
{
	protected $tab;
	protected $action;
	protected $record;
	protected $ruleCrits;
	protected $activeGrantRules;
	protected static $ruleCritsCache = [];
	protected static $ruleBlockedFieldsCache = [];
	
	public static function create($tab, $action, $record = null)
	{
		return new self($tab, $action, $record);
	}
	
	public function __construct($tab, $action, $record = null)
	{
		$this->setTab($tab);
		$this->setAction($action);
		$this->setRecord($record);
	}

	protected function setTab($tab) 
	{
		$this->tab = $tab;
		return $this;
	}
	
	public function getTab()
	{
		return $this->tab;
	}

	protected function setAction($action) 
	{
		$this->action = $action;
		return $this;
	}

	protected function setRecord($record) 
	{
		$this->record = is_numeric($record)? Utils_RecordBrowserCommon::get_record($this->getTab(), $record): $record;

		return $this;
	}
	
	public function getUserAccess($adminMode = false) 
	{
		// access inactive records only in admin mode
		if (! $this->getRecordInactiveAccess() && ! ($adminMode && Acl::i_am_admin())) return false;

		if ($this->isFullDeny()) return false;
		
		if ($this->action === 'browse') return $this->getCritsRaw() !== null && $this->getCritsRaw() !== false ? true: false;

		if ($this->getActiveGrantRules() === false) return false;
		
		if ($this->action === 'delete') return true;
		
		return $this->getAccessFields();
	}
	
	public function getCrits() 
	{
		if(!$this->getRecordInactiveAccess()) return false;

		return $this->getCritsRaw();
	}

	protected function getCritsRaw() 
	{
		if ($this->isFullDeny()) return false;
		
		if ($this->isFullGrant()) return true;
		
		$ret = null;
		
		$ruleCrits = $this->getRuleCrits();
		
		foreach ( $ruleCrits as $ruleId => $c ) {
			if ($ruleId === 'restrict') continue;
			
			if (! $c instanceof Utils_RecordBrowser_CritsInterface) continue;
			
			// if crit is empty, then we have access to all records
			if ($c->is_empty()) $ret = $c;
			
			if ($ret instanceof Utils_RecordBrowser_Crits && $ret->is_empty()) continue;
			
			$ret = Utils_RecordBrowserCommon::merge_crits($ret, $c, true);
		}
		
		// if there is any access granted - limit it based on restrict crits
		if ($ret !== null && $ruleCrits['restrict'] instanceof Utils_RecordBrowser_Crits) $ret = Utils_RecordBrowserCommon::merge_crits($ret, $ruleCrits['restrict']);

		return $ret?: false;
	}
	
	protected function getRecordInactiveAccess() 
	{
		if(!Utils_RecordBrowserCommon::is_record_active($this->record) && ($this->action=='edit' || $this->action=='delete'))
			return false;
			
		return true;
	}
	
	public function isFullGrant() 
	{
		$ruleCrits = $this->getRuleCrits();
		
		return ($ruleCrits['restrict']!==true && $ruleCrits['grant']===true);
	}
	public function isFullDeny() 
	{
		$ruleCrits = $this->getRuleCrits();
		
		return $ruleCrits['restrict']===true;
	}
	
	public function getRuleCrits()
	{
		if (isset($this->ruleCrits)) return $this->ruleCrits;
		
		$cache_key = "{$this->tab}__USER_" . Acl::get_user();
		
		$action = ($this->action == 'browse')? 'view': $this->action;
		
		if (!isset(self::$ruleCritsCache[$cache_key])) {
			Utils_RecordBrowserCommon::check_table_name($this->tab);
			
			$user_clearance = Acl::get_clearance();
			
			$r = DB::Execute('SELECT * FROM '.$this->tab.'_access AS acs WHERE NOT EXISTS (SELECT * FROM '.$this->tab.'_access_clearance WHERE rule_id=acs.id AND '.implode(' AND ',array_fill(0, count($user_clearance), 'clearance!=%s')).')', array_values($user_clearance));
			
			$ruleCrits = [
					'view' => [],
					'edit' => [],
					'delete' => [],
					'add' => [],
					'print' => [],
					'export' => [],
					'selection' => []
			];
			
			while ($row = $r->FetchRow())
				$ruleCrits[$row['action']][$row['id']] = $this->parseAccessCrits($row['crits']);
				
			$ruleCrits['selection'] = $ruleCrits['selection']?: $ruleCrits['view'];
				
			self::$ruleCritsCache[$cache_key] = $ruleCrits;
		}
		
		$ruleCrits = self::$ruleCritsCache[$cache_key];
		
		return $this->ruleCrits = $ruleCrits[$action] + $this->callCustomAccessCallbacks();
	}
	
	public function parseAccessCrits($str, $human_readable = false) 
	{
		$ret = Utils_RecordBrowserCommon::unserialize_crits($str);
		if (!is_object($ret)) {
			$ret = Utils_RecordBrowser_Crits::from_array($ret);
		}
		return $ret->replace_special_values($human_readable);
	}
	
	protected function callCustomAccessCallbacks()
	{
		$ret = [
				'grant' => null,
				'restrict' => null
		];
		foreach ( Utils_RecordBrowserCommon::get_custom_access_callbacks($this->tab) as $callback ) {
			if (!is_callable($callback)) continue;
			
			$callbackCrits = call_user_func($callback, $this->action, $this->record, $this->tab);
			
			if (is_bool($callbackCrits)) {
				$ret[$callbackCrits ? 'grant': 'restrict'] = true;
				break;
			}
			
			if ($callbackCrits === null) continue;
			
			// if callback return is crits or crits array use it by default in restrict mode for backward compatibility
			$crits = [
					'grant' => null,
					'restrict' => $callbackCrits
			];
			
			if (is_array($callbackCrits) && (isset($callbackCrits['grant']) || isset($callbackCrits['restrict']))) {
				// if restrict rules are not set make sure the restrict crits are clean
				if (! isset($callbackCrits['restrict'])) $callbackCrits['restrict'] = null;
				$crits = array_merge($crits, $callbackCrits);
			}
			
			if (! $crits['grant']) $crits['grant'] = null;
			
			foreach ( $crits as $mode => $c ) {
				$c = is_array($c) ? Utils_RecordBrowser_Crits::from_array($c): $c;
				
				if ($c instanceof Utils_RecordBrowser_Crits) $ret[$mode] = ($ret[$mode] !== null) ? Utils_RecordBrowserCommon::merge_crits($ret[$mode], $c, $mode === 'grant'): $c;
				elseif (is_bool($c)) $ret[$mode] = $c;
			}
		}
		
		return $ret;
	}

	protected function getActiveGrantRules() 
	{
		if (isset($this->activeGrantRules)) return $this->activeGrantRules;
		
		if ($this->isFullDeny()) return false;
		
		if ($this->isFullGrant()) return ['grant'];
		
		$ruleCrits = $this->getRuleCrits();
		
		if ($this->record != null && $this->action !== 'add' && $ruleCrits['restrict'] instanceof Utils_RecordBrowser_CritsInterface && ! Utils_RecordBrowserCommon::check_record_against_crits($this->tab, $this->record, $ruleCrits['restrict'])) {
			return false;
		}
		
		$ret = [];
		foreach ( $ruleCrits as $rule_id => $c ) {
			if ($rule_id === 'restrict') continue;
			
			if (! $c instanceof Utils_RecordBrowser_CritsInterface) continue;
			
			if ($this->record != null && ! Utils_RecordBrowserCommon::check_record_against_crits($this->tab, $this->record, $c)) continue;
			
			$ret[] = $rule_id;
		}
		
		return $this->activeGrantRules = $ret ?: false;
	}
	
	protected function getAccessFields() 
	{
		$grant_rule_ids = $this->getActiveGrantRules();
		
		$access_rule_blocked_fields = [];
		
		foreach ( $grant_rule_ids as $rule_id )
			$access_rule_blocked_fields[$rule_id] = $this->getRuleBlockedFields($rule_id);
		
		$fields = Utils_RecordBrowserCommon::init($this->tab);
		
		$blocked_fields = count($access_rule_blocked_fields) > 1 ? call_user_func_array('array_intersect', $access_rule_blocked_fields): reset($access_rule_blocked_fields);
		
		$full_field_access = array_fill_keys(array_column($fields, 'id'), true);
		
		$blocked_field_access = $blocked_fields? array_fill_keys($blocked_fields, false):[];
		
		return array_merge($full_field_access, $blocked_field_access);
	}
	
	protected function getRuleBlockedFields($ruleId) 
	{
		if (!is_numeric($ruleId)) return [];
		
		if (!isset(self::$ruleBlockedFieldsCache[$this->tab])) {
			$r = DB::Execute('SELECT * FROM '.$this->tab.'_access_fields');
			
			$fields = array();
			while ($row = $r->FetchRow()) {
				$fields[$row['rule_id']][] = $row['block_field'];
			}
			
			self::$ruleBlockedFieldsCache[$this->tab] = $fields;
		}
		
		return isset(self::$ruleBlockedFieldsCache[$this->tab][$ruleId])? self::$ruleBlockedFieldsCache[$this->tab][$ruleId]: [];
	}
}
