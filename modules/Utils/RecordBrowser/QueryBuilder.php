<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_QueryBuilder
{
    protected $tab;
    protected $fields;
    protected $fields_by_id;

    protected $applied_joins = array();
    protected $final_tab;
    protected $tab_alias;
    protected $admin_mode = false;

    function __construct($tab, $tab_alias = 'rest', $admin_mode = false)
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($tab);
        $this->fields_by_id = Utils_RecordBrowserCommon::$hash;
        $this->tab_alias = $tab_alias;
        $this->admin_mode = $admin_mode;
    }

    public function build_query(Utils_RecordBrowser_CritsInterface $crits, $order = array(), $admin_filter = '')
    {
        $crits = $crits->replace_special_values();

        $tab_with_as = $this->tab.'_data_1 AS ' . $this->tab_alias;
        $this->final_tab = $tab_with_as;

        list($having, $vals) = $this->to_sql($crits);

        if (!$having) $having = 'true';

        $this->final_tab = str_replace('('. $tab_with_as .')', $tab_with_as, $this->final_tab);
        $where = $admin_filter . "($having)";
        $sql = ' ' . $this->final_tab . ' WHERE ' . $where;

        $order_sql = $this->build_order_part($order);

        return array('sql' => $sql, 'vals' => $vals, 'order' => $order_sql, 'tab' => $this->final_tab, 'where' => $where);
    }

    public function to_sql(Utils_RecordBrowser_CritsInterface $crits)
    {
        if ($crits->is_active() == false) {
            return array('', array());
        }
        if ($crits instanceof Utils_RecordBrowser_CritsSingle) {
            return $this->build_single_crit_query($crits);
        } elseif ($crits instanceof Utils_RecordBrowser_Crits) {
            $vals = array();
            $sql = array();
            foreach ($crits->get_component_crits() as $c) {
                list($s, $v) = $this->to_sql($c);
                if ($s) {
                    $vals = array_merge($vals, $v);
                    $sql[] = "($s)";
                }
            }
            $glue = ' ' . $crits->get_join_operator() . ' ';
            $sql_str = implode($glue, $sql);
            if ($crits->get_negation() && $sql_str) {
                $sql_str = "NOT ($sql_str)";
            }
            return array($sql_str, $vals);
        } elseif ($crits instanceof Utils_RecordBrowser_CritsRawSQL) {
            $sql = $crits->get_negation() ? $crits->get_negation_sql() : $crits->get_sql();
            return array($sql, $crits->get_vals());
        }
        return array('', array());
    }

    public static function transform_meta_operators_to_sql($operator)
    {
        if ($operator == 'LIKE') {
            $operator = DB::like();
        } else if ($operator == 'NOT LIKE') {
            $operator = 'NOT ' . DB::like();
        }
        return $operator;
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
                        $orderby[] = ' (SELECT COUNT(*) FROM '.$this->tab.'_favorite WHERE '.$this->tab.'_id='.$this->tab_alias.'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Visited_on'  :
                        $orderby[] = ' (SELECT MAX(visited_on) FROM '.$this->tab.'_recent WHERE '.$this->tab.'_id='.$this->tab_alias.'.id AND user_id='.$user_id.') '.$v['direction'];
                        break;
                    case ':Edited_on'   :
                        $orderby[] = ' (CASE WHEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id='.$this->tab_alias.'.id) IS NOT NULL THEN (SELECT MAX(edited_on) FROM '.$this->tab.'_edit_history WHERE '.$this->tab.'_id='.$this->tab_alias.'.id) ELSE ' . $this->tab_alias . '.created_on END) '.$v['direction'];
                        break;
                    default     :
                        $orderby[] = ' '.substr($v['order'], 1).' ' . $v['direction'];
                }
            } else {
                $field_def = $this->get_field_definition($v['order']);
                $field_sql_id = $this->tab_alias . '.f_' . $field_def['id'];
                if (isset($field_def['ref_table']) && !$field_def['commondata']) {
                    $tab2 = $field_def['ref_table'];
                    $cols2 = $field_def['ref_field'];
                    $cols2 = explode('|', $cols2);
                    $val = $field_sql_id;
                    $fields = Utils_RecordBrowserCommon::init($tab2);
                    // search for better sorting than id
                    if ($fields) {
                        foreach ($cols2 as $referenced_col) {
                            if (isset($fields[$referenced_col])) {
                                $n_field = $fields[$referenced_col];
                                if ($n_field['type'] != 'calculated' || $n_field['param'] != '') {
                                    $field_id = Utils_RecordBrowserCommon::get_field_id($referenced_col);
                                    $val = '(SELECT rdt.f_'.$field_id.' FROM '.$tab2.'_data_1 AS rdt WHERE rdt.id='.$field_sql_id.')';
                                    break;
                                }
                            }
                        }
                    }
                    $orderby[] = ' '.$val.' '.$v['direction'];
                } elseif ($field_def['commondata']) {
                    $sort = $field_def['commondata_order'];
                    $sorted = false;
                    if ($sort == 'position' || $sort == 'value') {
                        $sort_field = $sort == 'position' ? 'position' : 'value';
                        $parent_id = Utils_CommonDataCommon::get_id($field_def['commondata_array']);
                        if ($parent_id) {
                            $orderby[] = " (SELECT $sort_field FROM utils_commondata_tree AS uct WHERE uct.parent_id=$parent_id AND uct.akey=$field_sql_id) " . $v['direction'];
                            $sorted = true;
                        }
                    }
                    if ($sorted == false) { // key or if position or value failed
                        $orderby[] = ' '.$field_sql_id.' '.$v['direction'];
                    }
                } elseif ($field_def['type'] == 'calculated') {
                    if (!$field_def['param']) continue;

                    $param = explode('::', $field_def['param']);
                    if (isset($param[1]) && $param[1] != '') {
                        $tab2 = $param[0];
                        $cols = explode('|', $param[1]);
                        $first_col = $cols[0];
                        $first_col = explode('/', $first_col);
                        $data_col = isset($first_col[1]) ? Utils_RecordBrowserCommon::get_field_id($first_col[1]) : $field_def['id'];
                        $field_id = Utils_RecordBrowserCommon::get_field_id($first_col[0]);
                        $val = '(SELECT rdt.f_'.$field_id.' FROM '.$this->tab.'_data_1 AS rd LEFT JOIN '.$tab2.'_data_1 AS rdt ON rdt.id=rd.f_'.$data_col.' WHERE '.$this->tab_alias.'.id=rd.id)';
                    } else {
                        $val = $field_sql_id;
                    }
                    $orderby[] = ' ' . $val . ' ' . $v['direction'];
                } else {
                    if ($field_def['type'] == 'currency') {
                        if (DB::is_mysql()) {
                            $field_sql_id = "CAST($field_sql_id as DECIMAL(64,5))";
                        } elseif (DB::is_postgresql()) {
                            $field_sql_id = "CAST(COALESCE(NULLIF(split_part($field_sql_id, '__', 1),''),'0') as DECIMAL)";
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

        list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($crit->get_field());

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
        $operator = self::transform_meta_operators_to_sql($crit->get_operator());
        $value = $crit->get_value();
        $negation = $crit->get_negation();

        $special = $field[0] == ':' || $field == 'id';
        if ($special) {
            $sql = '';
            $vals = array();
            switch ($field) {
                case ':id' :
                case 'id' :
                    if (!is_array($value)) {
                        $sql = $this->tab_alias.".id $operator %d";
                        $value = preg_replace('/[^0-9-]*/', '', $value);
                        $vals[] = $value;
                    } else {
                        if ($operator != '=' && $operator != '==') {
                            throw new Exception("Cannot use array values for id field operator '$operator'");
                        }
                        $clean_vals = array();
                        foreach ($value as $v) {
                            if (is_numeric($v)) {
                                $clean_vals[] = $v;
                            }
                        }
                        if (empty($clean_vals)) {
                            $sql = 'false';
                        } else {
                            $sql = $this->tab_alias.".id IN (" . implode(',', $clean_vals) . ")";
                        }
                    }
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Fav' :
                    $fav = ($value == true);
                    if ($negation) $fav = !$fav;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_favorite AS '.$this->tab_alias.'_fav ON '.$this->tab_alias.'_fav.' . $this->tab . '_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_fav.user_id='. Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $fav ? 'IS NOT NULL' : 'IS NULL';
                    $sql= $this->tab_alias."_fav.fav_id $rule";
                    break;
                case ':Sub' :
                    $sub = ($value == true);
                    if ($negation) $sub = !$sub;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN utils_watchdog_subscription AS '.$this->tab_alias.'_sub ON '.$this->tab_alias.'_sub.internal_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_sub.category_id=' . Utils_WatchdogCommon::get_category_id($this->tab) . ' AND '.$this->tab_alias.'_sub.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $sub ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->tab_alias."_sub.internal_id $rule";
                    break;
                case ':Recent'  :
                    $rec = ($value == true);
                    if ($negation) $rec = !$rec;
                    if (!isset($this->applied_joins[$field])) {
                        $this->final_tab = '(' . $this->final_tab . ') LEFT JOIN ' . $this->tab . '_recent AS '.$this->tab_alias.'_rec ON '.$this->tab_alias.'_rec.' . $this->tab . '_id='.$this->tab_alias.'.id AND '.$this->tab_alias.'_rec.user_id=' . Acl::get_user();
                        $this->applied_joins[$field] = true;
                    }
                    $rule = $rec ? 'IS NOT NULL' : 'IS NULL';
                    $sql = $this->tab_alias."_rec.user_id $rule";
                    break;
                case ':Created_on'  :
                    $vals[] = Base_RegionalSettingsCommon::reg2time($value, false);
                    $sql = $this->tab_alias.'.created_on ' . $operator . '%T';
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Created_by'  :
                    if (!is_array($value)) {
                        $value = array($value);
                    }
                    $sql = array();
                    foreach ($value as $v) {
                        $vals[] = $v;
                        $sql[] = $this->tab_alias.'.created_by ' . $operator . ' %d';
                    }
                    $sql = implode(' OR ', $sql);
                    if ($negation) {
                        $sql = "NOT ($sql)";
                    }
                    break;
                case ':Edited_on'   :
                    if ($value === null) {
                        if ($operator == '=') {
                            $inj = 'IS NULL';
                        } elseif ($operator == '!=') {
                            $inj = 'IS NOT NULL';
                        } else {
                            throw new Exception('Cannot compare timestamp field null with operator: ' . $operator);
                        }
                    } else {
                        $inj = $operator . '%T';
                        $timestamp = Base_RegionalSettingsCommon::reg2time($value, false);
                        $vals[] = $timestamp;
                        $vals[] = $timestamp;
                    }

                    $sql = '(((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id='.$this->tab_alias.'.id) ' . $inj . ') OR ' .
                               '((SELECT MAX(edited_on) FROM ' . $this->tab . '_edit_history WHERE ' . $this->tab . '_id='.$this->tab_alias.'.id) IS NULL AND '.$this->tab_alias.'.created_on ' . $inj . '))';
                    if ($negation) {
                        $sql = "NOT (COALESCE($sql, FALSE))";
                    }
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

    protected function get_field_sql($field_name, $cast = null)
    {
        return $this->tab_alias.".f_{$field_name}";
    }

    protected function hf_text($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        $vals = array();
        if ($operator == DB::like() && ($value == '%' || $value == '%%')) {
            $sql = 'true';
        } elseif (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_integer($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            $casttype = DB::is_postgresql() ? 'varchar' : 'char';
            return array("CAST($field AS $casttype) $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %d AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_float($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            $casttype = DB::is_postgresql() ? 'varchar' : 'char';
            return array("CAST($field AS $casttype) $operator %s", array($value));
        }
        $vals = array();
        if ($value === '' || $value === null || $value === false) {
            $sql = "$field IS NULL";
        } else {
            $sql = "$field $operator %f AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_boolean($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            $casttype = DB::is_postgresql() ? 'varchar' : 'char';
            return array("CAST($field AS $casttype) $operator %s", array($value));
        }
        if ($operator == '!=') {
            $sql = $value ?
                    "$field IS NULL OR $field!=%b" :
                    "$field IS NOT NULL AND $field!=%b";
        } else {
            $sql = $value ?
                    "$field IS NOT NULL AND $field=%b" :
                    "$field IS NULL OR $field=%b";
        }
        return array($sql, array($value ? true : false));
    }

    protected function hf_date($field, $operator, $value, $raw_sql_val)
    {
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($operator == DB::like()) {
            $casttype = DB::is_postgresql() ? 'varchar' : 'char';
            return array("CAST($field AS $casttype) $operator %s", array($value));
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
        if ($operator == DB::like()) {
            $casttype = DB::is_postgresql() ? 'varchar' : 'char';
            return array("CAST($field AS $casttype) $operator %s", array($value));
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

    protected function hf_select($field, $operator, $value, $raw_sql_val, $field_def)
    {
        $commondata = isset($field_def['commondata']) && $field_def['commondata'];
        if ($commondata) {
            return $this->hf_commondata($field, $operator, $value, $raw_sql_val, $field_def);
        }

        $sql = '';
        $vals = array();
        list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($field);
        $multiselect = ($field_def['type'] == 'multiselect');
        $tab2 = isset($field_def['ref_table']) ? $field_def['ref_table'] : false;

        $single_tab = !($tab2 == '__RECORDSETS__' || count(explode(',', $tab2)) > 1);

        if ($operator == DB::like() && isset($field_def['ref_field'])) {
            $sub_field = $field_def['ref_field'];
        }

        $vv = explode('::', $value, 2);
        $ids = null;
        if(isset($vv[1]) && is_callable($vv)) {
            $handled_with_php = array('true', array());
            if (!$single_tab) return $handled_with_php;
            $callbacks = array(
                'view' => 'Utils_RecordBrowserCommon::get_recursive_view',
                'edit' => 'Utils_RecordBrowserCommon::get_recursive_edit',
                'print' => 'Utils_RecordBrowserCommon::get_recursive_print',
                'delete' => 'Utils_RecordBrowserCommon::get_recursive_delete',
            );
            $action = null;
            foreach ($callbacks as $act => $c) {
                if (strpos($value, $c) !== false) {
                    $action = $act;
                    break;
                }
            }
            if (!$action) return $handled_with_php;

            $access_crits = Utils_RecordBrowserCommon::get_access_crits($tab2, $action);
            $subquery = Utils_RecordBrowserCommon::build_query($tab2, $access_crits, $this->admin_mode);
            if ($subquery) {
                $ids = DB::GetCol("SELECT r.id FROM $subquery[sql]", $subquery['vals']);
            } else {
                $sql = 'false';
            }
        } else if ($sub_field && $single_tab && $tab2 && $tab2 != $this->tab) {
            $col2 = explode('|', $sub_field);
            $crits = new Utils_RecordBrowser_Crits();
            foreach ($col2 as $col) {
                $col = $col[0] == ':' ? $col : Utils_RecordBrowserCommon::get_field_id(trim($col));
                if ($col) {
                    $crits->_or(new Utils_RecordBrowser_CritsSingle($col, $operator, $value, false, $raw_sql_val));
                }
            }
            if (!$crits->is_empty()) {
                $subquery = Utils_RecordBrowserCommon::build_query($tab2, $crits, $this->admin_mode);
                if ($subquery) {
                    $ids = DB::GetCol("SELECT r.id FROM $subquery[sql]", $subquery['vals']);
                } else {
                    $sql = 'false';
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
                // compatibility code to replace old company/contact style
                if (preg_match('/([PC]):([0-9-]+)/', $value, $matches)) {
                    $value = ( $matches[1] == 'C' ? 'company' : 'contact' ) . '/' . $matches[2];
                }
                if ($single_tab) {
                    $value = preg_replace('#.*/#', '', $value); // remove prefix for select from single tab: contact/1 => 1
                }
                if ($single_tab && !$multiselect && $operator != DB::like()) {
                    $operand = '%d';
                } else {
                    if (DB::is_postgresql()) {
                        $field .= '::varchar';
                    }
                    $operand = '%s';
                }
                if ($multiselect) {
                    $value = "%\\_\\_{$value}\\_\\_%";
                    $operator = DB::like();
                }
                $sql = "($field $operator $operand AND $field IS NOT NULL)";
                $vals[] = $value;
            }
        }
        if (is_array($ids)) {
            if (count($ids)) {
                if ($multiselect) {
                    $q = array();
                    foreach ($ids as $id) {
                        $q[] = "$field LIKE '%\\_\\_$id\\_\\_%'";
                    }
                    $q = implode(' OR ', $q);
                } else {
                    $q = implode(',', $ids);
                    $q = "$field IN ($q)";
                }
                $sql = "($field IS NOT NULL AND ($q))";
            } else {
                $sql = 'false';
            }
        }
        return array($sql, $vals);
    }

    protected function hf_commondata($field, $operator, $value, $raw_sql_val, $field_def)
    {
        list($field, $sub_field) = Utils_RecordBrowser_CritsSingle::parse_subfield($field);
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($value === null || $value === false || $value === '') {
            return array("$field IS NULL OR $field=''", array());
        }

        if (!isset($field_def['ref_table'])) { // commondata type doesn't have this, only select/multiselect
            $field_def['ref_table'] = $field_def['param']['array_id'];
        }

        $sql = array();
        $vals = array();
        if ($sub_field !== false) { // may be empty string for value lookup with field[]
            $commondata_table = $field_def['ref_table'];
            $ret = Utils_CommonDataCommon::get_translated_array($commondata_table);
            $val_regex = '/^' . str_replace(array('%', '_'), array('.*', '.'), preg_quote($value, '/')) . '$/i';
            $final_vals = array_keys(preg_grep($val_regex, $ret));
            if ($operator == DB::like()) {
                $operator = '=';
            }
            // add false in statement to force empty set if final_vals are empty
            if (empty($final_vals)) {
                $sql[] = 'false';
            }
        } else {
            $final_vals = array($value);
        }

        $multiselect = ($field_def['type'] == 'multiselect');
        if ($multiselect) {
            $operator = DB::like();
        }

        foreach ($final_vals as $val) {
            $sql[] = "($field $operator %s AND $field IS NOT NULL)";
            if ($multiselect) {
                $val = "%\\_\\_{$val}\\_\\_%";
            }
            $vals[] = $val;
        }
        $sql_str = implode(' OR ', $sql);
        return array($sql_str, $vals);
    }

    protected function hf_calculated($field, $operator, $value, $raw_sql_val, $field_def)
    {
        $param = isset($field_def['param']) ? $field_def['param'] : '';
        if (!$param) {
            return array('false', array());
        }
        if ($raw_sql_val) {
            return array("$field $operator $value", array());
        }
        if ($field_def['style'] == 'currency') {
            return $this->hf_currency($field, $operator, $value, $raw_sql_val);
        }
        $vals = array();
        if (DB::is_postgresql()) $field .= '::varchar';
        if (!$value) {
            $sql = "$field IS NULL OR $field=''";
        } else {
            $sql = "$field $operator %s AND $field IS NOT NULL";
            $vals[] = $value;
        }
        return array($sql, $vals);
    }

    protected function hf_multiple(Utils_RecordBrowser_CritsSingle $crit, $callback, $field_def = null)
    {
        $sql = array();
        $vals = array();

        $field_sql_id = $this->get_field_sql($crit->get_field());
        $operator = $crit->get_operator();
        $raw_sql_val = $crit->get_raw_sql_value();
        $value = is_string($crit->get_value()) && preg_match('/^[A-Za-z]$/',$crit->get_value())
            ? "'%".$crit->get_value()."%'"
            : $crit->get_value();
        $negation = $crit->get_negation();
        if ($operator == 'NOT LIKE') {
            $operator = 'LIKE';
            $negation = !$negation;
        }
        if ($operator == '!=') {
            $operator = '=';
            $negation = !$negation;
        }
        $operator = self::transform_meta_operators_to_sql($operator);
        if (is_array($value)) { // for empty array it will give empty result
            $sql[] = 'false';
        } else {
            $value = array($value);
        }
        foreach ($value as $w) {
            $args = array($field_sql_id, $operator, $w, $raw_sql_val, $field_def);
            list($sql2, $vals2) = call_user_func_array($callback, $args);
            if ($sql2) {
                $sql[] = $sql2;
                $vals = array_merge($vals, $vals2);
            }
        }
        $sql_str = implode(' OR ', $sql);
        if ($sql_str && $negation) {
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
                $ret = $this->hf_multiple($crit, array($this, 'hf_select'), $field_def);
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

            case 'calculated':
                $ret = $this->hf_multiple($crit, array($this, 'hf_calculated'), $field_def);
                break;

            default:
                $ret = $this->hf_multiple($crit, array($this, 'hf_text'));
        }

        return $ret;
    }
}
