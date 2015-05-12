<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

interface Utils_RecordBrowser_CritsInterface
{
    function to_sql($callback);
    function to_words();
}

class Utils_RecordBrowser_CritsSingle implements Utils_RecordBrowser_CritsInterface
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

    public function get_value_as_array()
    {
        $ret = $this->value;
        if (!is_array($ret)) {
            $ret = array($ret);
        }
        return $ret;
    }

    /**
     * @return string
     */
    public function get_operator()
    {
        return $this->operator;
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
        $ret = call_user_func($callback, $this);
        return $ret;
    }

    public function to_words()
    {
        $value = is_array($this->value) ? implode(', ', $this->value) : $this->value;
        $ret = "{$this->field} {$this->operator} {$value}";
        if ($this->negation) {
            $ret = __('Not') . "($ret)";
        }
        return $ret;
    }
}

class Utils_RecordBrowser_CritsRaw implements Utils_RecordBrowser_CritsInterface
{
    protected $sql;
    protected $vals;

    function __construct($sql, $values = array())
    {
        $this->sql = $sql;
        if (!is_array($values)) {
            $values = array($values);
        }
        $this->vals = $values;
    }

    public function to_sql($callback)
    {
        return array($this->sql, $this->vals);
    }

    public function to_words()
    {
        $value = implode(', ', $this->vals);
        $ret = "{$this->sql} ({$value})";
        return $ret;
    }
}

class Utils_RecordBrowser_Crits implements Utils_RecordBrowser_CritsInterface
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

    public function to_sql($callback)
    {
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
        $parts = array();
        foreach ($this->component_crits as $c) {
            $s = $c->to_words();
            if ($s) {
                $parts[] = $s;
            }
        }
        $glue = ' ' . _V($this->join_operator) . ' ';
        $neg = $this->negation ? ' ' . __('Not') : '';
        $str = $neg . " (" . implode($glue, $parts) . ") ";
        return $str;
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
                if ($k[0] == '~') $operator = DB::like();
                // parse >= and <=
                if ($k[1] == '=' && $operator != DB::like()) {
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
                if ($k[0]=='~') $operator = DB::like();
                if ($k[0]=='^') $group_or_start = true;
                // parse >= and <=
                if ($k[1]=='=' && $operator!=DB::like()) {
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
