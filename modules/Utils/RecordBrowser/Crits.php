<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_RecordBrowser_CritsInterface
{
    protected static $replace_callbacks = array();

    /**
     * Make sure that all crits do not use negation. Reverse operators and logic
     * operators according to De Morgan's laws
     *
     * @return mixed
     */
    abstract function normalize();

    /**
     * Replace crits value to other value or disable crits that uses this value.
     *
     * Object will be changed! Clone it before use if you'd like to hold
     * original one.
     *
     * @param mixed $search
     * @param mixed $replace
     * @param bool  $deactivate pass true and null as replace to disable crit
     */
    abstract function replace_value($search, $replace, $deactivate = false);

    /**
     * Method to lookup in crits for certain fields crits or crits objects
     *
     * @param string|object $key key to find or crits object
     *
     * @return array Crits objects in array that matches $key
     */
    abstract function find($key);

    public static function register_special_value_callback($callback)
    {
        self::$replace_callbacks[] = $callback;
    }

    /**
     * Replace all registered special values.
     *
     * Object will be cloned. Current object will not be changed.
     *
     * @param bool $human_readable Use special value or it's human readable form
     *
     * @return Utils_RecordBrowser_CritsInterface New object with replaced values
     */
    public function replace_special_values($human_readable = false)
    {
        $new = clone $this;
        $user = Base_AclCommon::get_user();
        $replace_values = self::get_replace_values($user);
        /** @var Utils_RecordBrowser_ReplaceValue $rv */
        foreach ($replace_values as $rv) {
            $replacement = $human_readable ? $rv->get_human_readable() : $rv->get_replace();
            $deactivate = $human_readable ? false : $rv->get_deactivate();
            $new->replace_value($rv->get_value(), $replacement, $deactivate);
        }
        return $new;
    }

    protected static function get_replace_values($user)
    {
        static $replace_values_cache = array();
        if (!isset($replace_values_cache[$user])) {
            $replace_values_cache[$user] = array();
            foreach (self::$replace_callbacks as $callback) {
                $ret = call_user_func($callback);
                if (!is_array($ret)) {
                    $ret = array($ret);
                }
                /** @var Utils_RecordBrowser_ReplaceValue $rv */
                foreach ($ret as $rv) {
                    if (!isset($replace_values_cache[$user][$rv->get_value()])
                        || $replace_values_cache[$user][$rv->get_value()]->get_priority() < $rv->get_priority()
                    ) {
                        $replace_values_cache[$user][$rv->get_value()] = $rv;
                    }
                }
            }
        }
        return $replace_values_cache[$user];
    }

    /**
     * @return boolean
     */
    public function get_negation()
    {
        return $this->negation;
    }

    /**
     * @param boolean $negation
     */
    public function set_negation($negation = true)
    {
        $this->negation = $negation;
    }

    /**
     * Negate this crit object
     */
    public function negate()
    {
        $this->set_negation(!$this->get_negation());
    }

    /**
     * @param bool $active
     */
    public function set_active($active = true)
    {
        $this->active = ($active == true);
    }

    /**
     * @return bool
     */
    public function is_active()
    {
        return $this->active;
    }

    protected $negation = false;
    protected $active = true;
}

class Utils_RecordBrowser_CritsSingle extends Utils_RecordBrowser_CritsInterface
{
    protected $field;
    protected $value;
    protected $operator;
    protected $negation = false;
    protected $raw_sql_value = false; // noquotes

    function __construct($field, $operator, $value, $negation = false, $raw_sql_value = false)
    {
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
        $this->negation = $negation;
        $this->raw_sql_value = $raw_sql_value;
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }

    public static function parse_subfield($field)
    {
        $field = explode('[', $field);
        $sub_field = isset($field[1]) ? trim($field[1], ']') : false;
        $field = $field[0];
        return array($field, $sub_field);
    }

    /**
     * @return string
     */
    public function get_field()
    {
        return $this->field;
    }
    
    public function set_field($key)
    {
    	$this->field = $key;
    	
    	return $this;
    }

    /**
     * @return mixed
     */
    public function get_value()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function get_operator()
    {
        return $this->operator;
    }

    /**
     * Normalize to remove negation
     */
    public function normalize()
    {
        if ($this->get_negation()) {
            $this->set_negation(false);
            $this->operator = self::opposite_operator($this->operator);
        }
    }

    public function find($key)
    {
        if ($this->field == $key) {
            return $this;
        }
        return null;
    }

    /**
     * @return boolean
     */
    public function get_raw_sql_value()
    {
        return $this->raw_sql_value;
    }

    /**
     * @param boolean $raw_sql_value
     */
    public function set_raw_sql_value($raw_sql_value = true)
    {
        $this->raw_sql_value = $raw_sql_value;
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        $deactivate = $deactivate && ($replace === null);
        if (is_array($this->value)) {
            $found = false;
            foreach ($this->value as $k => $v) {
                if ($v === $search) {
                    $found = true;
                    unset($this->value[$k]);
                }
            }
            if ($found) {
                if ($deactivate) {
                    if (empty($this->value)) {
                        $this->set_active(false);
                    }
                } else {
                    if (!is_array($replace)) {
                        $replace = array($replace);
                    }
                    $this->value = array_merge($this->value, $replace);
                }
            }
        } elseif ($this->value === $search) {
            if ($deactivate) {
                $this->set_active(false);
            } else {
                $this->value = $replace;
            }
        }
    }

    public function __clone()
    {
        if (is_object($this->value)) {
            $this->value = clone $this->value;
        } elseif (is_array($this->value)) {
            foreach ($this->value as $k => $v) {
                if (is_object($v)) {
                    $this->value[$k] = clone $v;
                }
            }
        }
    }


    public static function opposite_operator($operator)
    {
        switch ($operator) {
            case '=' : return '!=';
            case '!=': return '=';
            case '>=': return '<';
            case '<' : return '>=';
            case '<=': return '>';
            case '>': return '<=';
            case 'LIKE': return 'NOT LIKE';
            case 'NOT LIKE': return 'LIKE';
            case 'IN': return 'NOT IN';
            case 'NOT IN': return 'IN';
        }
    }
}

class Utils_RecordBrowser_CritsRawSQL extends Utils_RecordBrowser_CritsInterface
{
    protected $sql;
    protected $negation_sql;
    protected $vals;

    function __construct($sql, $negation_sql = false, $values = array())
    {
        $this->sql = $sql;
        $this->negation_sql = $negation_sql;
        if (!is_array($values)) {
            $values = array($values);
        }
        $this->vals = $values;
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }

    /**
     * @return mixed
     */
    public function get_sql()
    {
        return $this->sql;
    }

    /**
     * @return boolean
     */
    public function get_negation_sql()
    {
        return $this->negation_sql;
    }

    /**
     * @return array
     */
    public function get_vals()
    {
        return $this->vals;
    }

    public function normalize()
    {
        if ($this->get_negation()) {
            if ($this->negation_sql !== false) {
                $this->set_negation(false);
                $tmp_sql = $this->negation_sql;
                $this->negation_sql = $this->sql;
                $this->sql = $tmp_sql;
            } else {
                throw new ErrorException('Cannot normalize RawSQL crits without negation_sql param!');
            }
        }
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        $deactivate = $deactivate && ($replace === null);
        if (is_array($this->vals)) {
            foreach ($this->vals as $k => $v) {
                if ($v === $search) {
                    if ($deactivate) {
                        $this->set_active(false);
                    } else {
                        $this->vals[$k] = $replace;
                    }
                }
            }
        } elseif ($this->vals === $search) {
            if ($deactivate) {
                $this->set_active(false);
            } else {
                $this->vals = $replace;
            }
        }
    }

    public function find($key)
    {
        return null;
    }

}

class Utils_RecordBrowser_Crits extends Utils_RecordBrowser_CritsInterface
{
    protected $negation = false;
    protected $join_operator = null;

    /** @var Utils_RecordBrowser_CritsInterface[] $component_crits */
    protected $component_crits = array();

    public static function where($field, $operator, $value)
    {
        $crits_obj = new self();
        $crits = new Utils_RecordBrowser_CritsSingle($field, $operator, $value);
        $crits_obj->component_crits[]= $crits;
        return $crits_obj;
    }

    public function __construct($crits = null, $or = false)
    {
        if ($crits && !is_bool($crits)) {
            if (is_array($crits)) {
                $crits = Utils_RecordBrowser_CritsBuilder::create()->build_single($crits);
                $this->component_crits = $crits;
            } else {           	
                $this->component_crits[] = $crits;
            }
            if (is_array($crits) && count($crits) > 1) {
                $this->join_operator = $or ? 'OR' : 'AND';
            }
        }
    }
    
    public static function __set_state($array)
    {
    	$crits = new static();
    	
    	foreach ($array as $key => $value) {
    		$crits->{$key} = $value;
    	}
    	
    	return $crits;
    }

    public function normalize()
    {
        if ($this->get_negation()) {
            $this->set_negation(false);
            $this->join_operator = $this->join_operator == 'OR' ? 'AND' : 'OR';
            foreach ($this->component_crits as $c) {
                $c->negate();
            }
        }
        foreach ($this->component_crits as $c) {
            $c->normalize();
        }
    }

    public function find($key)
    {
        $ret = array();
        foreach ($this->get_component_crits() as $cc) {
            if (is_object($key)) {
                if ($cc == $key) {
                    $ret[] = $cc;
                } elseif ($cc instanceof Utils_RecordBrowser_Crits) {
                    $crit = $cc->find($key);
                    if (is_array($crit)) {
                        $ret = array_merge($ret, $crit);
                    }
                }
            } else {
                $crit = $cc->find($key);
                if (is_array($crit)) {
                    $ret = array_merge($ret, $crit);
                } elseif (!is_null($crit)) {
                    $ret[] = $crit;
                }
            }
        }
        return $ret ? $ret : null;
    }

    public function is_empty()
    {
        return empty($this->component_crits);
    }

    public function __clone()
    {
        foreach ($this->component_crits as $k => $v) {
            $this->component_crits[$k] = clone $v;
        }
    }

    protected function __op($operator, $crits)
    {
        $ret = $this;
        $crits_count = count($this->component_crits);
        if ($crits_count == 0) {
            $this->component_crits[] = $crits;
        } elseif ($crits_count == 1) {
            $this->join_operator = $operator;
            $this->component_crits[] = $crits;
        } else {
            if ($this->join_operator == $operator) {
                $this->component_crits[] = $crits;
            } else {
                $new = new self($this);
                $new->__op($operator, $crits);
                $ret = $new;
            }
        }
        return $ret;
    }

    public function _and($crits)
    {
        return $this->__op('AND', $crits);
    }

    public function _or($crits)
    {
        return $this->__op('OR', $crits);
    }

    /**
     * @return null|string
     */
    public function get_join_operator()
    {
        return $this->join_operator;
    }

    /**
     * @return Utils_RecordBrowser_CritsInterface[]
     */
    public function get_component_crits()
    {
        return $this->component_crits;
    }

    public function replace_value($search, $replace, $deactivate = false)
    {
        foreach ($this->component_crits as $c) {
            $c->replace_value($search, $replace, $deactivate);
        }
    }

    /**
     * @param array $crits Legacy array crits
     *
     * @return Utils_RecordBrowser_Crits new object like crits
     */
    public static function from_array($crits)
    {
        return Utils_RecordBrowser_CritsBuilder::create()->build_from_array($crits);
    }
    
    public static function merge($a = array(), $b = array(), $or = false)
    {
    	if (is_array($a)) {
    		$a = self::from_array($a);
    	}
    	if (!($a instanceof self)) {
    		$a = new self($a);
    	}
    	if (is_array($b)) {
    		$b = self::from_array($b);
    	}
    	if (!($b instanceof self)) {
    		$b = new self($b);
    	}
    	if ($a->is_empty()) {
    		return clone $b;
    	}
    	if ($b->is_empty()) {
    		return clone $a;
    	}
    	$a = clone $a;
    	$b = clone $b;
    	return $or ? $a->_or($b) : $a->_and($b);
	}
	
	public static function and($crits, $_ = null)
    {
		$ret = [];		
		foreach (func_get_args() as $crits) {
			$ret = self::merge($ret, $crits);
		}
    	
    	return $ret;
	}
	
	public static function or($crits, $_ = null)
    {
    	$ret = [];    	
    	foreach (func_get_args() as $crits) {
    		$ret = self::merge($ret, $crits, true);
    	}
    	
    	return $ret;
	}
}

class Utils_RecordBrowser_CritsBuilder
{
	public static function create() {
		return new static();
	}
	
    public function build_single($crits)
    {
        $ret = array();

        foreach($crits as $k => $v) {

            if ($v instanceof Utils_RecordBrowser_CritsInterface) {
                $ret[] = $v;
                continue;
            }

            // initiate key modifiers for each crit
            $negative = $noquotes = false;

            // default operator
            $operator = '=';

            // parse and remove modifiers
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
                if ($k[0] == '!') $negative = true;
                if ($k[0] == '"') $noquotes = true;
                if ($k[0] == '<') $operator = '<';
                if ($k[0] == '>') $operator = '>';
                if ($k[0] == '~') $operator = 'LIKE';
                // parse >= and <=
                if ($k[1] == '=' && $operator != 'LIKE') {
                    $operator .= '=';
                    $k = substr($k, 2);
                } else $k = substr($k, 1);

                if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
            }

            $new_crit = new Utils_RecordBrowser_CritsSingle($k, $operator, $v);
            if ($noquotes) $new_crit->set_raw_sql_value();
            if ($negative) $new_crit->set_negation();

            $ret[] = $new_crit;
        }
        return $ret;
    }

    public function build_from_array($crits)
    {
        $CRITS = array(new Utils_RecordBrowser_Crits());
        if (is_bool($crits)) {
            return $crits ? $CRITS[0] : new Utils_RecordBrowser_CritsRawSQL('false', 'true');
        }
        if (!$crits) { // empty array case
            return $CRITS[0];
        }
        $CRITS_cnt = 1;
        /** @var Utils_RecordBrowser_Crits $current_crit */
        $current_crit = $CRITS[0];

        $or_started = $group_or = false;
        $group_or_cnt = null;
        foreach($crits as $k=>$v){
            if ($k == '') continue;

            // initiate key modifiers for each crit
            $negative = $noquotes = $or_start = $or = $group_or_start = false;

            // default operator
            $operator = '=';

            // parse and remove modifiers
            while (($k[0]<'a' || $k[0]>'z') && ($k[0]<'A' || $k[0]>'Z') && $k[0]!=':') {
                if ($k[0]=='!') $negative = true;
                if ($k[0]=='"') $noquotes = true;
                if ($k[0]=='(') $or_start = true;
                if ($k[0]=='|') $or = true;
                if ($k[0]=='<') $operator = '<';
                if ($k[0]=='>') $operator = '>';
                if ($k[0]=='~') $operator = 'LIKE';
                if ($k[0]=='^') $group_or_start = true;
                // parse >= and <=
                if ($k[1]=='=' && $operator != 'LIKE') {
                    $operator .= '=';
                    $k = substr($k, 2);
                } else $k = substr($k, 1);

                if (!isset($k[0])) trigger_error('Invalid criteria in build query: missing word. Crits:'.print_r($crits,true), E_USER_ERROR);
            }

            $new_crit = new Utils_RecordBrowser_CritsSingle($k, $operator, $v);
            if ($noquotes) $new_crit->set_raw_sql_value();
            if ($negative) $new_crit->set_negation();

            if ($group_or_start) {
                $or_started = false; // group or takes precedence
                if ($group_or) {
                    $CRITS_cnt = $group_or_cnt + 1; // return to group or crit
                    $current_crit = $CRITS[$group_or_cnt]; // get grouping crit
                } else {
                    $CC = new Utils_RecordBrowser_Crits();
                    $group_or_cnt = $CRITS_cnt;
                    $CRITS[$CRITS_cnt++] = $CC;
                    $current_crit->_and($CC);
                    $current_crit = $CC;
                    $group_or = true;
                }
                $CC = new Utils_RecordBrowser_Crits();
                $CRITS[$CRITS_cnt++] = $CC;
                $current_crit->_or($CC);
                $current_crit = $CC;
            }
            if ($or_start) {
                if ($or_started) {
                    $CRITS_cnt -= 1; // pop current one
                    $current_crit = $CRITS[$CRITS_cnt - 1]; // get grouping crit
                }
                $CC = new Utils_RecordBrowser_Crits($new_crit);
                $CRITS[$CRITS_cnt++] = $CC;
                $current_crit->_and($CC);
                $current_crit = $CC;
                $or_started = true;
                continue;
            }
            if ($or) {
                $current_crit->_or($new_crit);
            } else {
                if ($or_started) {
                    $CRITS_cnt -= 1; // pop current one
                    $current_crit = $CRITS[$CRITS_cnt - 1]; // get grouping crit
                    $or_started = false;
                }
                $current_crit->_and($new_crit);
            }
        }
        return $CRITS[0];
    }
}
