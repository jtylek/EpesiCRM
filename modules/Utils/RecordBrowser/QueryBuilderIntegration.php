<?php

class Utils_RecordBrowser_QueryBuilderIntegration
{

    protected $tab;
    protected $fields;

    function __construct($tab)
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($this->tab);
    }

    public function get_builder_module(Module $module, $crits)
    {
        /** @var Utils_QueryBuilder $qb */
        $qb = $module->init_module('Utils/QueryBuilder');
        $operators = array(
            array('type' => 'equal', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string', 'number', 'datetime', 'boolean')),
            array('type' => 'not_equal', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string', 'number', 'datetime', 'boolean')),
            array('type' => 'less', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('number', 'datetime')),
            array('type' => 'less_or_equal', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('number', 'datetime')),
            array('type' => 'greater', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('number', 'datetime')),
            array('type' => 'greater_or_equal', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('number', 'datetime')),
            array('type' => 'begins_with', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'not_begins_with', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'contains', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'not_contains', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'ends_with', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'not_ends_with', 'nb_inputs' => 1, 'multiple' => false, 'apply_to' => array('string')),
            array('type' => 'is_null', 'nb_inputs' => 0, 'multiple' => false, 'apply_to' => array('string', 'number', 'datetime', 'boolean')),
            array('type' => 'is_not_null', 'nb_inputs' => 0, 'multiple' => false, 'apply_to' => array('string', 'number', 'datetime', 'boolean')),
        );
        $qb->set_option('operators', $operators);
        $filters = $this->get_filters();
        $qb->set_filters($filters);
        $rules = $this->get_rules($crits);
        $qb->set_rules($rules);
        return $qb;
    }

    public function get_filters()
    {
        $ret = $this->get_default_record_filters();
        foreach ($this->fields as $f) {
            $def = self::map_rb_field_to_query_builder_filters($this->tab, $f);
            if ($def) {
                $ret = array_merge($ret, $def);
            }
        }
        return $ret;
    }

    public function get_default_record_filters()
    {
        $ret = array();
        $empty = array(''=>'['.__('Empty').']');
        if ($this->tab == 'contact') {
            $ret[] = array(
                'id' => 'id',
                'label' => __('ID'),
                'type' => 'boolean',
                'input' => 'select',
                'values' => array('USER'=>__('User Contact'))
            );
        }
        if ($this->tab == 'company') {
            $ret[] = array(
                'id' => 'id',
                'label' => __('ID'),
                'type' => 'boolean',
                'input' => 'select',
                'values' => array('USER_COMPANY'=>__('User Company'))
            );
        }
        $ret[] = array(
            'id' => ':Created_by',
            'label' => __('Created by'),
            'type' => 'boolean',
            'input' => 'select',
            'values' => array('USER_ID' => __('User Login'))
        );
        $ret[] = array(
            'id' => ':Created_on',
            'label' => __('Created on'),
            'type' => 'date',
            'input' => 'select',
            'values' => Utils_RecordBrowserCommon::$date_values
        );
        $ret[] = array(
            'id' => ':Edited_on',
            'label' => __('Edited on'),
            'type' => 'date',
            'input' => 'select',
            'values' => Utils_RecordBrowserCommon::$date_values
        );
        return $ret;
    }

    public function get_rules($crits)
    {
        if (is_array($crits)) {
            $crits = \Utils_RecordBrowser_Crits::from_array($crits);
        }
        /** @var Utils_RecordBrowser_Crits $crits */
        $ret = $this->crits_to_json($crits);
        return $ret;
    }

    public static function map_rb_field_to_query_builder_filters($tab, $f, $in_depth = true, $prefix = '', $sufix = '', $label_prefix = '')
    {
        $filters = array();
        $type = null;
        $values = null;
        $input = null;
        $opts = array();
        $filters[] = &$opts;
        switch ($f['type']) {
            case 'text':
                $type = 'string';
                break;
            case 'multiselect':
            case 'select':
                $param = explode(';', $f['param']);
                $ref = explode('::', $param[0]);

                $tabs = $ref[0];
                if ($tabs == '__RECORDSETS__') {
                    $single_tab = false;
                } else {
                    $tabs = explode(',', $tabs);
                    $single_tab = count($tabs) == 1;
                }
                $type = 'boolean';
                $input = 'select';
                $values = self::permissions_get_field_values($tab, $f);
                if ($in_depth && $single_tab) {
                    $one_tab = reset($tabs);
                    if (Utils_RecordBrowserCommon::check_table_name($one_tab, false, false)) {
                        $fields = Utils_RecordBrowserCommon::init($one_tab);
                        foreach ($fields as $k => $v) {
                            if ($v['type'] == 'calculated' || $v['type'] == 'hidden') {
                            } else {
                                $new_label_prefix = _V($f['name']) . ' ' .  __('is set to record where') . ' ';
                                $sub_filter = self::map_rb_field_to_query_builder_filters($tab, $v, false, $f['id'] . '[', ']', $new_label_prefix);
                                if ($sub_filter) {
                                    $sub_filter = reset($sub_filter);
                                    $sub_filter['optgroup'] = $new_label_prefix;
                                    $filters[] = $sub_filter;
                                }
                            }
                        }
                    }
                }
                break;
            case 'commondata':
                $type = 'boolean';
                $input = 'select';
                $array_id = is_array($f['param']) ? $f['param']['array_id'] : $f['ref_table'];
                $values = array('' => '['.__('Empty').']');
                if (strpos($array_id, '::') === false) {
                    $values = $values + Utils_CommonDataCommon::get_translated_array($array_id, is_array($f['param']) ? $f['param']['order_by_key'] : false);
                }
                break;
            case 'integer':     $type = 'integer'; break;
            case 'float':       $type = 'double'; break;
            case 'timestamp':
            case 'date':
                $type = 'string';
                $input = 'select';
                $values = self::permissions_get_field_values($tab, $f);
                break;
            case 'time': $f = DB::dict()->ActualType('T'); break;
            case 'long text':   $type = 'string'; $input = 'textarea'; break;
            case 'hidden': break;
            case 'calculated': break;
            case 'checkbox':    $type = 'boolean'; break;
            case 'currency': $f = DB::dict()->ActualType('C').'(128)'; break;
            case 'autonumber': break;
        }
        if ($type) {
            $opts['id'] = $prefix . $f['id'] . $sufix;
            $opts['label'] = $label_prefix . _V($f['name']);
            $opts['type'] = $type;
            if ($values) {
                $opts['values'] = $values;
            }
            if ($input) {
                $opts['input'] = $input;
            }
            return $filters;
        }
        return null;
    }

    private static function permissions_get_field_values($tab, $args) {
        $arr = array(''=>'['.__('Empty').']');
        $field = $args['id'];
        switch (true) {
            case $args['type']=='text' && $args['filter']:
                $arr_add = @DB::GetAssoc('SELECT f_'.$args['id'].', f_'.$args['id'].' FROM '.$tab.'_data_1 GROUP BY f_'.$args['id'].' ORDER BY count(*) DESC LIMIT 20');
                if($arr_add) $arr += $arr_add;
                break;
            case $args['commondata']:
                $array_id = is_array($args['param']) ? $args['param']['array_id'] : $args['ref_table'];
                if (strpos($array_id, '::')===false)
                    $arr = $arr + Utils_CommonDataCommon::get_translated_array($array_id, is_array($args['param'])?$args['param']['order_by_key']:false);
                break;
            case $tab=='contact' && $field=='login' ||
                 $tab=='rc_accounts' && $field=='epesi_user': // just a quickfix, better solution will be needed
                $arr = $arr + array('USER_ID'=>__('User Login'));
                break;
            case $args['type']=='date' || $args['type']=='timestamp':
                $arr = $arr + Utils_RecordBrowserCommon::$date_values;
                break;
            case ($args['type']=='multiselect' || $args['type']=='select') && (!isset($args['ref_table']) || !$args['ref_table']):
                $arr = $arr + array('USER'=>__('User Contact'));
                $arr = $arr + array('USER_COMPANY'=>__('User Company'));
                break;
            case $args['type']=='checkbox':
                $arr = array('1'=>__('Yes'),'0'=>__('No'));
                break;
            case ($args['type']=='select' || $args['type']=='multiselect') && isset($args['ref_table']):
                if ($args['ref_table']=='contact') $arr = $arr + array('USER'=>__('User Contact'));
                if ($args['ref_table']=='company') $arr = $arr + array('USER_COMPANY'=>__('User Company'));
                break;
        }
        return $arr;
    }


    public function crits_to_json(Utils_RecordBrowser_CritsInterface $crits)
    {
        $crits->normalize();
        if ($crits instanceof Utils_RecordBrowser_Crits) {
            $cc = $crits->get_component_crits();
            $condition = $crits->get_join_operator();
            if (!$condition) $condition = 'AND';
            $ret = array('condition' => $condition);
            $rules = array();
            foreach ($cc as $c) {
                $rr = $this->crits_to_json($c);
                if ($rr) {
                    $rules[] = $rr;
                }
            }
            $ret['rules'] = $rules;
            return $ret;
        } elseif ($crits instanceof Utils_RecordBrowser_CritsSingle) {
            list($operator, $value) = self::map_crits_operator_to_query_builder($crits->get_operator(), $crits->get_value());
            $ret = array(
                'id' => $crits->get_field(),
                'field' => $crits->get_field(),
                'operator' => $operator,
                'value' => $value
            );
            return $ret;
        } elseif ($crits instanceof Utils_RecordBrowser_CritsRawSQL) {

        } else {
            throw new Exception("crits to json exporter: unsupported class: " . get_class($crits));
        }
    }

    public function json_to_crits($json)
    {
        $array = json_decode($json, true);
        return $this->array_to_crits($array);
    }

    public function array_to_crits($arr)
    {
        $ret = null;
        if (isset($arr['condition']) && isset($arr['rules'])) {
            $rules = array();
            foreach ($arr['rules'] as $rule) {
                $crit = $this->array_to_crits($rule);
                if ($crit) {
                    $rules[] = $crit;
                }
            }
            if (!empty($rules)) {
                $ret = $arr['condition'] == 'AND' ? rb_and($rules) : rb_or($rules);
            }
        } elseif (isset($arr['field']) && isset($arr['operator']) && array_key_exists('value', $arr)) {
            $field = $arr['field'];
            list($operator, $value) = self::map_query_builder_operator_to_crits($arr['operator'], $arr['value']);
            $ret = new Utils_RecordBrowser_CritsSingle($field, $operator, $value);
        }
        return $ret;
    }

    public static function map_crits_operator_to_query_builder($operator, $value)
    {
        if (($operator == '=' || $operator == '!=' ) && $value == '' && !is_numeric($value)) {
            $operator = $operator == '=' ? 'is_null' : 'is_not_null';
        } elseif ($operator == 'LIKE' || $operator == 'NOT LIKE') {
            if (preg_match('/^%.*%$/', $value)) {
                $operator = 'contains';
                $value = trim($value, '%');
            } elseif (preg_match('/^.*%$/', $value)) {
                $operator = 'begins_with';
                $value = rtrim($value, '%');
            } elseif (preg_match('/^%.*/', $value)) {
                $operator = 'ends_with';
                $value = ltrim($value, '%');
            }
            $value = self::unescape_like_value($value);
            if ($operator == 'NOT LIKE') {
                $operator = "not_$operator";
            }
        } else {
            if (isset(self::$operator_map[$operator])) {
                $operator = self::$operator_map[$operator];
            } else {
                throw new Exception("Unsupported operator: $operator");
            }
        }
        return array($operator, $value);
    }

    public static function map_query_builder_operator_to_crits($operator, $value)
    {
        static $flipped;
        if (!$flipped) $flipped = array_flip(self::$operator_map);
        if ($operator == 'is_null') {
            $operator = '=';
            $value = null;
        } elseif ($operator == 'is_not_null') {
            $operator = '!=';
            $value = null;
        } elseif ($operator == 'begins_with') {
            $operator = 'LIKE';
            $value = self::escape_like_value($value);
            $value = "$value%";
        } elseif ($operator == 'not_begins_with') {
            $operator = 'NOT LIKE';
            $value = self::escape_like_value($value);
            $value = "$value%";
        } elseif ($operator == 'ends_with') {
            $operator = 'LIKE';
            $value = self::escape_like_value($value);
            $value = "%$value";
        } elseif ($operator == 'not_ends_with') {
            $operator = 'NOT LIKE';
            $value = self::escape_like_value($value);
            $value = "%$value";
        } elseif ($operator == 'contains') {
            $operator = 'LIKE';
            $value = self::escape_like_value($value);
            $value = "%$value%";
        } elseif ($operator == 'not_contains') {
            $operator = 'NOT LIKE';
            $value = self::escape_like_value($value);
            $value = "%$value%";
        } else {
            if (isset($flipped[$operator])) {
                $operator = $flipped[$operator];
            } else {
                throw new Exception("Unsupported operator: $operator");
            }
        }
        return array($operator, $value);
    }

    public static function escape_like_value($value)
    {
        $value = str_replace(array('_', '%'), array('\\_', '\\%'), $value);
        return $value;
    }

    public static function unescape_like_value($value)
    {
        $value = str_replace(array('\\_', '\\%'), array('_', '%'), $value);
        return $value;
    }

    protected static $operator_map = array(
        '' => '',
        '=' => 'equal',
        '!=' => 'not_equal',
        '>=' => 'greater_or_equal',
        '<' => 'less',
        '<=' => 'less_or_equal',
        '>' => 'greater',
        'LIKE' => 'like',
        'NOT LIKE' => 'not_like',
        'IN' => 'in',
        'NOT IN' => 'not_in'
    );
}