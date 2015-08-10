<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

abstract class Utils_RecordBrowser_CritsInterface
{
    protected static $replace_callbacks = array();

    abstract function normalize();
    abstract function to_words();
    abstract function to_sql($callback);
    abstract function replace_value($search, $replace, $deactivate = false);

    public static function register_special_value_callback($callback)
    {
        self::$replace_callbacks[] = $callback;
    }

    public function replace_special_values()
    {
        $user = Base_AclCommon::get_user();
        $replace_values = self::get_replace_values($user);
        /** @var Utils_RecordBrowser_ReplaceValue $rv */
        foreach ($replace_values as $rv) {
            $this->replace_value($rv->get_value(), $rv->get_replace(), $rv->get_deactivate());
        }
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

class Utils_RecordBrowser_ReplaceValue
{
    protected $value;
    protected $replace;
    protected $deactivate;
    protected $priority;

    /**
     * Utils_RecordBrowser_ReplaceValue constructor.
     *
     * @param      $value
     * @param      $replace
     * @param bool $deactivate
     * @param int  $priority
     */
    public function __construct($value, $replace, $deactivate = false, $priority = 1)
    {
        $this->value = $value;
        $this->replace = $replace;
        $this->deactivate = $deactivate;
        $this->priority = $priority;
    }

    /**
     * @return mixed
     */
    public function get_value()
    {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function get_replace()
    {
        return $this->replace;
    }

    /**
     * @return boolean
     */
    public function get_deactivate()
    {
        return $this->deactivate;
    }

    /**
     * @return int
     */
    public function get_priority()
    {
        return $this->priority;
    }

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

    /**
     * @return string
     */
    public function get_field()
    {
        return $this->field;
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

    public function to_sql($callback)
    {
        if ($this->is_active() == false) {
            return array('', array());
        }
        $this->transform_meta_operators_to_sql();
        $ret = call_user_func($callback, $this);
        return $ret;
    }

    protected function transform_meta_operators_to_sql()
    {
        if ($this->operator == 'LIKE') {
            $this->operator = DB::like();
        } else if ($this->operator == 'NOT LIKE') {
            $this->operator = 'NOT ' . DB::like();
        }
    }

    public function to_words()
    {
        if ($this->is_active() == false) {
            return '';
        }
        $value = is_array($this->value) ? implode(', ', $this->value) : $this->value;
        $ret = "{$this->field} {$this->operator} {$value}";
        if ($this->negation) {
            $ret = __('Not') . "($ret)";
        }
        return $ret;
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

    public function to_sql($callback)
    {
        if ($this->is_active() == false) {
            return array('', array());
        }
        $sql = $this->get_negation() ? $this->negation_sql : $this->sql;
        return array($sql, $this->vals);
    }

    public function to_words()
    {
        if ($this->is_active() == false) {
            return '';
        }
        $sql = $this->get_negation() ? $this->negation_sql : $this->sql;
        $value = implode(', ', $this->vals);
        $ret = "{$sql} ({$value})";
        return $ret;
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
        if ($crits) {
            if (is_array($crits)) {
                $builder = new Utils_RecordBrowser_CritsBuilder();
                $crits = $builder->build_single($crits);
                $this->component_crits = $crits;
            } else {
                $this->component_crits[] = $crits;
            }
            if (count($crits) > 1) {
                $this->join_operator = $or ? 'OR' : 'AND';
            }
        }
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

    public function is_empty()
    {
        return count($this->component_crits) == 0;
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

    public function to_sql($callback)
    {
        if ($this->is_active() == false) {
            return array('', array());
        }
        $vals = array();
        $sql = array();
        foreach ($this->component_crits as $c) {
            list($s, $v) = $c->to_sql($callback);
            if ($s) {
                $vals = array_merge($vals, $v);
                $sql[] = "($s)";
            }
        }
        $glue = ' ' . $this->join_operator . ' ';
        $sql_str = implode($glue, $sql);
        if ($this->negation && $sql_str) {
            $sql_str = "NOT ($sql_str)";
        }
        return array($sql_str, $vals);
    }

    public function to_words()
    {
        if ($this->is_active() == false) {
            return '';
        }
        $parts = array();
        foreach ($this->component_crits as $c) {
            $s = $c->to_words();
            if ($s) {
                $parts[] = $s;
            }
        }
        if (!$parts) {
            return '';
        }
        $glue = ' ' . _V($this->join_operator) . ' ';
        $neg = $this->negation ? ' ' . __('Not') : '';
        $str = $neg . " (" . implode($glue, $parts) . ") ";
        return $str;
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
        $builder = new Utils_RecordBrowser_CritsBuilder();
        $ret = $builder->build_from_array($crits);
        return $ret;
    }
}

class Utils_RecordBrowser_CritsBuilder
{

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
        if (!$crits) {
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
