<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_QueryBuilder
{
    protected $tab;
    protected $fields;
    protected $fields_by_id;

    protected $applied_joins = array();
    protected $final_tab;

    function __construct($tab)
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($tab);
        $this->fields_by_id = Utils_RecordBrowserCommon::$hash;
    }

    public function build_query($crits, $order = array(), $admin_filter = '')
    {
        $this->final_tab = $this->tab.'_data_1 AS r';

        $callback = array($this, 'build_single_crit_query');
        list($having, $vals) = $crits->to_sql($callback);

        if (!$having) $having = 'true';

        $this->final_tab = str_replace('('.$this->tab.'_data_1 AS r'.')', $this->tab.'_data_1 AS r', $this->final_tab);
        $sql = ' ' . $this->final_tab . ' WHERE ' . $admin_filter . "($having)";

        $order_sql = $this->build_order_part($order);

        return array('sql' => $sql, 'vals' => $vals, 'order' => $order_sql);
    }

    protected function build_order_part($order)
    {
        foreach ($order as $k => $v) {
            if (!is_string($k)) {
                break;
            }
            if ($k[0] == ':') {
                $order[] = array('column' => $k, 'order' => $k, 'direction' => $v);
            } else {
                $field_label = isset($this->fields_by_id[$k])
                    ?
                    $this->fields_by_id[$k]
                    :
                    $k;
                if (isset($this->fields[$field_label])) {
                    $order[] = array('column' => $field_label, 'order' => $field_label, 'direction' => $v);
                }
            }
            unset($order[$k]);
        }

        $orderby = array();
        $user_id = Base_AclCommon::get_user();

        foreach ($order as $v) {
            if ($v['order'][0] != ':' && !isset($this->fields[$v['order']])) continue;
            if ($v['order'][0] == ':') {
                switch ($v['order']) {
                    case ':id':
                        $orderby[] = ' id ' . $v['direction'];
                        break;
                    case ':Fav' :
                        $orderby[] = ' (SELECT COUNT(*) FROM '.$this->tab.'_favorite WHERE '.$this->tab.'_id=r.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Visited_on'  :
                        $orderby[] = ' (SELECT MAX(visited_on) FROM '.$this->tab.'_recent WHERE '.$this->tab.'_id=r.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Edited_on'   :
                        $orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id=r.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id=r.id) ELSE created_on END) '.$v['direction'];
                        break;
                    default     :
                        $orderby[] = ' '.substr($v['order'], 1).' ' . $v['direction'];
                }
            } else {
                $field_def = $this->get_field_definition($v['order']);
                $field_sql_id = 'f_' . $field_def['id'];
                if (isset($field_def['ref_table']) && $field_def['ref_table'] != '__COMMON__') {
                    $tab2 = $field_def['ref_table'];
                    $cols2 = $field_def['ref_field'];
                    $cols2 = explode('|', $cols2);
                    $cols2 = $cols2[0];
                    $field_id = Utils_RecordBrowserCommon::get_field_id($cols2);
                    $val = '(SELECT rdt.f_'.$field_id.' FROM '.$this->tab.'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.'.$field_sql_id.' WHERE r.id=rd.id)';
                    $orderby[] = ' '.$val.' '.$v['direction'];
                } else {
                    if ($field_def['type'] == 'currency') {
                        if (DB::is_mysql()) {
                            $val = "CAST($val as DECIMAL(64,5))";
                        } elseif (DB::is_postgresql()) {
                            $val = "CAST(split_part($val, '__', 1) as DECIMAL)";
                        }
                    }
                    $orderby[] = ' '.$field_sql_id.' '.$v['direction'];
                }
            }
        }

        if (!empty($orderby)) $orderby = ' ORDER BY'.implode(', ',$orderby);
        else $orderby = '';

        return $orderby;
    }

    public function build_single_crit_query(Utils_RecordBrowser_CritsSingle $crit)
    {
        $special_ret = $this->handle_special_field_crit($crit);
        if ($special_ret) {
            return $special_ret;
        }

        list($field, $sub_field) = $this->parse_subfield_from_field($crit->get_field());

        $field_def = $this->get_field_definition($field);
        if (!$field_def) {
            return array('', array());
        }

        list($sql, $value) = $this->handle_normal_field_crit($field_def, $crit);
        if (!is_array($value)) $value = array($value);
        return array($sql, $value);
    }

    protected function handle_special_field_crit(Utils_RecordBrowser_CritsSingle $crit)
    {
        $field = $crit->get_field();
        $operator = $crit->get_operator();
        $value = $crit->get_value();
        $negation = $crit->get_negation();

        $special = $field[0] == ':' || $field == 'id';
        if ($special) {
            $sql = '';
            $vals = array();
            switch ($field) {
                case ':id' :
                case 'id' :
                    if (empty($value)) {
                        $sql = 'false';
                    } else {
                        if (!is_array($value)) {
                            $value = array($value);
                        }
                        if (count($value) > 1) {
                            $operator = $negation ? "NOT IN" : "IN";
                            $sql = "r.id $operator (" . implode(',', $value) . ")";
                        } else {
                            $sql = "r.id $operator %d";
                            $vals[] = reset($value);
                        }
                    }
                    break;
                case ':Fav' :
                    $fav = ($value == true);
                    if ($negation) $fav = !$fav;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_favorite AS fav ON fav.' . $this->tab . '_id=r.id AND fav.user_id='. Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $fav ? 'IS NOT NULL' : 'IS NULL';
                    $sql= "fav.fav_id $rule";
                    break;
                case ':Sub' :
                    $sub = ($value == true);
                    if ($negation) $sub = !$sub;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN utils_watchdog_subscription AS sub ON sub.internal_id=r.id AND sub.category_id=' . Utils_WatchdogCommon::get_category_id($this->tab) . ' AND sub.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $sub ? 'IS NOT NULL' : 'IS NULL';
                    $sql = "sub.internal_id $rule";
                    break;
                case ':Recent'  :
                    $rec = ($value == true);
                    if ($negation) $rec = !$rec;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_recent AS rec ON rec.' . $this->tab . '_id=r.id AND rec.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $rec ? 'IS NOT NULL' : 'IS NULL';
                    $sql = "rec.user_id $rule";
                    break;
                case ':Created_on'  :
                    $vals[] = Base_RegionalSettingsCommon::reg2time($value, false);
                    $sql = 'r.created_on ' . $operator . '%T';
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Created_by'  :
                    $vals[] = $value;
                    $sql = 'r.created_by = %d';
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Edited_on'   :
                    $inj = $operator . '%T';
                    $sql = '(((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id=r.id) ' . $inj . ') OR ' .
                               '((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id=r.id) IS NULL AND created_on ' . $inj . '))';
                    $timestamp = Base_RegionalSettingsCommon::reg2time($value, false);
                    if ($negation) {
                        $sql = "NOT (COALESCE($sql, FALSE))";
                    }
                    $vals[] = $timestamp;
                    $vals[] = $timestamp;
                    break;
            }
            return array($sql, $vals);
        }
        return false;
    }

    protected function get_field_definition($field_id_or_label)
    {
        $field_def = null;
        if (isset($this->fields[$field_id_or_label])) {
            $field_def = $this->fields[$field_id_or_label];
        } elseif (isset($this->fields_by_id[$field_id_or_label])) {
            $field_label = $this->fields_by_id[$field_id_or_label];
            $field_def = $this->fields[$field_label];
        }
        return $field_def;
    }

    protected function parse_subfield_from_field($field)
    {
        $field = explode('[', $field);
        $sub_field = isset($field[1]) ? trim($field[1], ']') : false;
        $field = $field[0];
        return array($field, $sub_field);
    }

    protected function get_field_sql($field_name, $cast = null)
    {
        return "r.f_{$field_name}";
    }

    protected function hf_text($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_integer($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %d";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_float($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %f";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_boolean($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            if ($operator == '=') {
                $sql = "$field IS NULL OR $field=%b";
            } else {
                $sql = "$field IS NOT NULL OR $field!=%b";
            }
            $vals[] = false;
        } else {
            $sql = "$field $operator %b";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_date($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %D $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_timestamp($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $value = Base_RegionalSettingsCommon::reg2time($value, false);
            $sql = "($field $operator %T $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_time($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL";
        } else {
            $field = "CAST($field as time)";
            $sql = "$field $operator %s";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_currency($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            if (DB::is_postgresql()) $field .= '::varchar';
            return array("$field $operator %s", array($value));
        }
        $vals = array();
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $null_part = ($operator == '<' || $operator == '<=') ?
                " OR $field IS NULL" :
                " AND $field IS NOT NULL";
            $field_as_int = DB::is_postgresql() ?
                "CAST(split_part($field, '__', 1) AS DECIMAL)" :
                "CAST($field AS DECIMAL(64,5))";
            $value_with_cast = DB::is_postgresql() ?
                "CAST(%s AS DECIMAL)" :
                "CAST(%s AS DECIMAL(64,5))";
            $sql = "($field_as_int $operator $value_with_cast $null_part)";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_multiselect($field, $operator, $value, $raw_sql_val, $field_def)
    {
        $commondata = isset($field_def['commondata']) && $field_def['commondata'];
        if ($commondata) {
            return $this->hf_commondata($field, $operator, $value, $raw_sql_val, $field_def);
        }

        $sql = '';
        $vals = array();
        list($field, $sub_field) = $this->parse_subfield_from_field($field);
        $multiselect = ($field_def['type'] == 'multiselect');
        $tab2 = isset($field_def['ref_table']) ? $field_def['ref_table'] : false;

        $single_tab = !($tab2 == '__RECORDSETS__' || count(explode(',', $tab2)) > 1);

        if ($sub_field && $single_tab && $tab2) {
            $col2 = explode('|', $sub_field);
            $CB = new Utils_RecordBrowser_QueryBuilder($tab2);
            $crits = new Utils_RecordBrowser_Crits();
            foreach ($col2 as $col) {
                $col = Utils_RecordBrowserCommon::get_field_id(trim($col));
                if ($col) {
                    $crits->_or(new Utils_RecordBrowser_CritsSingle($col, DB::like(), $value, false, $raw_sql_val));
                }
            }
            if (!$crits->is_empty()) {
                $subquery = $CB->build_query($crits);
                $subresult = DB::GetCol("SELECT id FROM{$subquery['sql']}", $subquery['vals']);
                if (!count($subresult)) {
                    $sql = "false";
                } else {
                    $crit = new Utils_RecordBrowser_CritsSingle($field_def['id'], '=', $subresult);
                    $ret = $this->hf_multiple($crit, array($this, 'hf_multiselect'), $field_def);
                    return $ret;
                }
            }
        } else {
            if ($raw_sql_val) {
                $sql = "$field $operator $value";
            } elseif (!$value) {
                $sql = "$field IS NULL";
                if (!$single_tab || $multiselect) {
                    $sql .= " OR $field=''";
                }
            } else {
                if ($single_tab && !$multiselect && $operator != DB::like()) {
                    $operand = '%d';
                } else {
                    if (DB::is_postgresql()) {
                        $field .= '::varchar';
                    }
                    $operand = '%s';
                }
                if ($multiselect) {
                    $value = "%__{$value}__%";
                    $operator = DB::like();
                }
                $sql = "($field $operator $operand AND $field IS NOT NULL)";
                $vals[] = $value;
            }
        }
        return array($sql, $vals);
    }

    protected function hf_commondata($field, $operator, $value, $raw_sql_val, $field_def)
    {
        list($field, $sub_field) = $this->parse_subfield_from_field($field);
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($value === null || $value === false || $value === '') {
            return array("$field IS NULL OR $field=''", array());
        }

        if (!isset($field_def['ref_table'])) { // commondata type doesn't have this, only select/multiselect
            $field_def['ref_table'] = $field_def['param']['array_id'];
        }

        if ($sub_field !== false) { // may be empty string for value lookup with field[]
            $commondata_table = $field_def['ref_table'];
            $ret = Utils_CommonDataCommon::get_translated_array($commondata_table);
            $val_regex = $operator == DB::like() ?
                '/' . preg_quote($value, '/') . '/i' :
                '/^' . preg_quote($value, '/') . '$/i';
            $final_vals = array_keys(preg_grep($val_regex, $ret));
            if ($operator == DB::like()) {
                $operator = '=';
            }
        } else {
            $final_vals = array($value);
        }

        $multiselect = ($field_def['type'] == 'multiselect');
        if ($multiselect) {
            $operator = DB::like();
        }

        $sql = array();
        $vals = array();
        foreach ($final_vals as $val) {
            $sql[] = "$field $operator %s";
            if ($multiselect) {
                $val = "%__{$val}__%";
            }
            $vals[] = $val;
        }
        $sql_str = implode(' OR ', $sql);
        return array($sql_str, $vals);
    }


    protected function hf_multiple(Utils_RecordBrowser_CritsSingle $crit, $callback, $field_def = null)
    {
        $sql = array();
        $vals = array();

        $field_sql_id = $this->get_field_sql($crit->get_field());
        $operator = $crit->get_operator();
        $raw_sql_val = $crit->get_raw_sql_value();
//        if ($operator == DB::like()) $field_sql_id .= '::varchar';
        foreach ($crit->get_value_as_array() as $w) {
            $args = array($field_sql_id, $operator, $w, $raw_sql_val, $field_def);
            list($sql2, $vals2) = call_user_func_array($callback, $args);
            if ($sql2) {
                $sql[] = $sql2;
                $vals = array_merge($vals, $vals2);
            }
        }
        $sql_str = implode(' OR ', $sql);
        if ($sql_str && $crit->get_negation()) {
            $sql_str = "NOT ($sql_str)";
        }
        return array($sql_str, $vals);

    }

    protected function handle_normal_field_crit($field_def, Utils_RecordBrowser_CritsSingle $crit)
    {
        $ret = array('', array());

        switch ($field_def['type']) {
            case 'autonumber':
            case 'text':
            case 'long text':
                $ret = $this->hf_multiple($crit, array($this, 'hf_text'));
                break;

            case 'integer':
                $ret = $this->hf_multiple($crit, array($this, 'hf_integer'));
                break;

            case 'float':
                $ret = $this->hf_multiple($crit, array($this, 'hf_float'));
                break;

            case 'checkbox':
                $ret = $this->hf_multiple($crit, array($this, 'hf_boolean'));
                break;

            case 'select':
            case 'multiselect':
                $ret = $this->hf_multiple($crit, array($this, 'hf_multiselect'), $field_def);
                break;

            case 'commondata':
                $ret = $this->hf_multiple($crit, array($this, 'hf_commondata'), $field_def);
                break;

            case 'currency':
                $ret = $this->hf_multiple($crit, array($this, 'hf_currency'));
                break;

            case 'date':
                $ret = $this->hf_multiple($crit, array($this, 'hf_date'));
                break;

            case 'timestamp':
                $ret = $this->hf_multiple($crit, array($this, 'hf_timestamp'));
                break;

            case 'time':
                $ret = $this->hf_multiple($crit, array($this, 'hf_time'));
                break;

            default:
                $ret = $this->hf_multiple($crit, array($this, 'hf_text'));
        }

        return $ret;

        list($field, $sub_field) = $this->parse_subfield_from_field($crit->get_field());
        $operator = $crit->get_operator();
        $value = $crit->get_value();
        $negation = $crit->get_negation();
        $raw_sql_val = $crit->get_raw_sql_value();

        // ret
        $sql = '';
        $vals = array();

        if ($sub_field) {
            $commondata = $field_def['commondata'];
            if (is_array($field_def['param'])) {
                if (isset($field_def['param']['array_id']))
                    $field_def['ref_table'] = $field_def['param']['array_id'];
                else
                    $field_def['ref_table'] = $field_def['param'][1];
            }
            if (!isset($field_def['ref_table'])) trigger_error('Invalid crits, field '.$field.' is not a reference;', E_USER_ERROR);
            $tab2 = $field_def['ref_table'];
            if (!is_array($value)) $value = array($value);
            if ($commondata) {
                $ret = Utils_CommonDataCommon::get_translated_array($tab2);
                $allowed_cd = array();
                foreach ($ret as $kkk=>$vvv) {
                    foreach ($value as $w) {
                        if ($w != '') {
                            if ($operator == DB::like()) {
                                $w = '/' . preg_quote($w, '/') . '/i';
                            } else {
                                $w = '/^' . preg_quote($w, '/') . '$/i';
                            }
                            if (preg_match($w, $vvv) !== 0) {
                                $allowed_cd[] = $kkk;
                                break;
                            }
                        }
                    }
                }
            } else {
                $table_rows2 = Utils_RecordBrowserCommon::init($tab2);
                $hash2 = Utils_RecordBrowserCommon::$hash;
                $det = explode('/', $sub_field);
                $col2 = explode('|', $det[0]);
                $poss_vals = '';
                $col2s = array();
                $col2m = array();

                $conv = '';
                if (DB::is_postgresql()) $conv = '::varchar';
                foreach ($col2 as $c) {
                    if ($table_rows2[$hash2[$c]]['type'] == 'multiselect')
                        $col2m[] = $c.$conv;
                    else
                        $col2s[] = $c.$conv;
                }

                foreach ($value as $w) {
                    if ($w==='') {
                        $poss_vals .= 'OR f_'.implode(' IS NULL OR f_', $col2);
                        break;
                    } else {
                        if (!$raw_sql_val) $w = DB::qstr($w);
                        if (!empty($col2s)) $poss_vals .= ' OR f_'.implode(' '.DB::like().' '.$w.' OR f_', $col2s).' '.DB::like().' '.$w;
                        if (!empty($col2m)) {
                            $w = DB::Concat(DB::qstr('%'),DB::qstr('\_\_'),$w,DB::qstr('\_\_'),DB::qstr('%'));
                            $poss_vals .= ' OR f_'.implode(' '.DB::like().' '.$w.' OR f_', $col2m).' '.DB::like().' '.$w;
                        }
                    }
                }
                $allowed_cd = DB::GetAssoc('SELECT id, id FROM '.$tab2.'_data_1 WHERE false '.$poss_vals);

            }
            if ($operator==DB::like())
                $operator = '=';
            $v = $allowed_cd;
            $k = $field;

        }

        $sql_arr = array();
        if (!is_array($value)) $value = array($value);
        foreach ($value as $w) {
            if ($field == 'id' || $field_def['type'] == 'integer') {
                $sql_arr[] = "$field $operator " . ($raw_sql_val ? $w : '%d');
                if (!$raw_sql_val) {
                    $vals[] = $w;
                }
            }
            if ($w && $field_def['type'] == 'timestamp' && $operator != DB::like()) {
                $w = Base_RegionalSettingsCommon::reg2time($w, false);
                $w = date('Y-m-d H:i:s', $w);
            } elseif ($w && $field_def['type'] == 'date' && $operator != DB::like()) {
                $w = Base_RegionalSettingsCommon::reg2time($w, false);
                $w = date('Y-m-d', $w);
            }

        }
        $sql = implode(' OR ', $sql_arr);
        if ($negation && $sql) {
            $sql = "NOT ($sql)";
        }

        return array($sql, $vals);
    }
}