<?php

class Utils_RecordBrowser_Search
{
    protected $search_tabs;
    protected $limit = 100;
    protected $offset = 0;

    protected $tabs;
    protected $access_cache = array();
    protected $cols_cache = array();

    /**
     * Utils_RecordBrowser_Search constructor.
     *
     * @param $tabs
     * @param $fields
     */
    public function __construct($tabs, $fields = array())
    {
        $this->tabs = DB::GetAssoc('SELECT id,tab,search_priority FROM recordbrowser_table_properties WHERE search_include>0');
        $this->set_search_tabs($tabs, $fields);
    }

    public function set_search_tabs($tabs, $fields = array())
    {
        $tab_ids_by_name = array_combine(array_column($this->tabs, 'tab'), array_keys($this->tabs));
        // allows single tab
        if (!is_array($tabs)) {
            $fields = array($tabs => $fields);
            $tabs = array($tabs);
        }
        $this->search_tabs = array();
        foreach ($tabs as $t) {
            // map table name to id
            if (is_string($t) && isset($tab_ids_by_name[$t])) {
                $tab_id = $tab_ids_by_name[$t];
            } else {
                $tab_id = $t;
            }
            // only if searchable
            if (isset($this->tabs[$tab_id])) {
                $tab = $this->tabs[$tab_id]['tab'];
                $fields_for_tab = array();
                // check fields by tab name/id
                if (isset($fields[$tab])) {
                    $fields_for_tab = $fields[$tab];
                }
                // check again for fields by id
                if (isset($fields[$tab_id])) {
                    $fields_for_tab = $fields[$tab_id];
                }
                // map fields to ids
                foreach ($fields_for_tab as $k => $value) {
                    $fields_for_tab[$k] = is_numeric($value) ? $value : $this->get_field_pid($tab, $value);
                }
                $this->search_tabs[$tab_id] = array_filter($fields_for_tab);
            }
        }
    }

    public function search_results($string)
    {
        $sorted_results = $this->get_sorted_results($string);
        return $this->format_results($sorted_results);
    }

    protected function get_sorted_results($string)
    {
        $query_array = $this->prepare_query_array($string);
        $results = $this->get_results($query_array);
        $sort_function = function ($a, $b) {
            if ($a['priority'] != $b['priority']) {
                return $a['priority'] < $b['priority'] ? 1 : -1;
            } elseif ($a['found_words'] != $b['found_words']) {
                return $a['found_words'] < $b['found_words'] ? 1 : -1;
            } elseif ($a['max_in_field'] != $b['max_in_field']) {
                return $a['max_in_field'] < $b['max_in_field'] ? 1 : -1;
            } else {
                $diff = count($b['fields']) - count($a['fields']);
                return $diff ? $diff / abs($diff) : 0;
            }
        };
        uasort($results, $sort_function);
        return $results;
    }

    protected function prepare_query_array($search)
    {
        $texts = array_unique(array_filter(preg_split('/\p{Z}/u', $search)));
        $ret = array_combine($texts, $texts);
        $ret = array_map(function ($txt) { return "%$txt%"; }, $ret);
        return $ret;
    }

    protected function get_results($query_array)
    {
        $total_results = array();
        foreach ($query_array as $word => $query) {
            $result = $this->get_result_for_one_word($query, $query_array);
            foreach ($result as $r) {
                $tab_id = $r['tab_id'];
                $tab = $this->tabs[$tab_id]['tab'];
                $record_id = $r['record_id'];
                $r_token = $tab . '/' . $record_id;
                $field_id = $r['field_id'];

                if (!$this->has_access($tab, $record_id, $field_id)) {
                    continue;
                }

                if (!isset($total_results[$r_token])) {
                    $total_results[$r_token] = array('found_words' => 0, 'words' => array(), 'fields' => array(), 'max_in_field' => 0, 'priority' => $this->tabs[$tab_id]['search_priority']);
                }
                $rres = &$total_results[$r_token];
                if (!in_array($word, $rres['words'])) {
                    $rres['words'][] = $word;
                    $rres['found_words'] = count($rres['words']);
                }
                if (!isset($rres['fields'][$field_id])) {
                    $rres['fields'][$field_id] = array();
                }
                if (!in_array($word, $rres['fields'][$field_id])) {
                    $rres['fields'][$field_id][] = $word;
                }
                $rres['max_in_field'] = max($rres['max_in_field'], count($rres['fields'][$field_id]));
            }
        }
        return $total_results;
    }

    protected function get_result_for_one_word($one_word, $all_words)
    {
        if (!$this->search_tabs) {
            return array();
        }
        $LIKE = DB::like();

        $categories_sql = $this->get_categories_with_fields_sql();
        // select matching records with exact limit, sort by count of distinct fields with value
        $all_words_sql = implode(' OR ', array_fill(0, count($all_words), "text $LIKE %s"));
        $vals = array_values($all_words);
        $inquery1 = "SELECT tab_id, record_id, count(*) as fields FROM recordbrowser_search_index si LEFT JOIN recordbrowser_table_properties pr ON si.tab_id=pr.id WHERE ($categories_sql) AND ($all_words_sql) GROUP BY si.tab_id, si.record_id, pr.search_priority ORDER BY pr.search_priority DESC, fields DESC, si.tab_id ASC, si.record_id ASC LIMIT %d OFFSET %d";
        // select matching records with fields
        $inquery2 = "SELECT tab_id, record_id, field_id FROM recordbrowser_search_index WHERE ($categories_sql) AND text $LIKE %s";
        // join results to retrieve fields from desired count of records
        $joint = "SELECT y.* FROM ($inquery1) x INNER JOIN ($inquery2) y ON x.tab_id=y.tab_id AND x.record_id=y.record_id";
        $vals[] = $this->limit;
        $vals[] = $this->offset;
        $vals[] = $one_word;
        $result = DB::GetAll($joint, $vals);
        return $result;
    }

    protected function get_categories_with_fields_sql()
    {
        $with_fields = array();
        $all_fields = array();
        foreach ($this->search_tabs as $tab_id => $fields_to_search) {
            if ($fields_to_search) {
                $fields = implode(',', $fields_to_search);
                $with_fields[] = "(tab_id = $tab_id AND field_id IN ($fields))";
            } else {
                $all_fields[] = $tab_id;
            }
        }
        if ($all_fields) {
            $with_fields[] = 'tab_id IN (' . implode(',', $all_fields) . ')';
        }
        $ret = implode(' OR ', $with_fields);
        return $ret;
    }

    protected function has_access($tab, $record_id, $field_id)
    {
        $access = $this->get_access($tab, $record_id);
        if (!$access) {
            return false;
        }
        return $access[$this->get_field_name($tab, $field_id)];
    }

    protected function get_access($tab, $id)
    {
        $token = $tab . '/' . $id;
        if (!isset($this->access_cache[$token])) {
            $record = $this->get_record($tab, $id);
            $this->access_cache[$token] = Utils_RecordBrowserCommon::get_access($tab, 'view', $record);
        }
        return $this->access_cache[$token];
    }

    protected function get_record($tab, $id)
    {
        static $cache = array();
        $token = $tab . '/' . $id;
        if (!isset($cache[$token])) {
            $cache[$token] = Utils_RecordBrowserCommon::get_record($tab, $id);
        }
        return $cache[$token];
    }

    protected function get_field_name($tab, $field_id)
    {
        $cols = $this->get_cols($tab);
        $field_name = $cols[$field_id]['id'];
        return $field_name;
    }

    protected function get_field_label($tab, $field_id)
    {
        $cols = $this->get_cols($tab);
        $field_label = $cols[$field_id]['name'];
        return _V($field_label);
    }

    protected function get_field_pid($tab, $field_id_or_name)
    {
        $cols = $this->get_cols($tab);
        foreach ($cols as $field_id => $k) {
            if ($k['id'] == $field_id_or_name || $k['field'] == $field_id_or_name) {
                return $field_id;
            }
        }
        return null;
    }

    protected function get_cols($tab)
    {
        if (!isset($this->cols_cache[$tab])) {
            $table_rows = Utils_RecordBrowserCommon::init($tab);
            $this->cols_cache[$tab] = array();
            foreach ($table_rows as $field => $col) {
                $this->cols_cache[$tab][$col['pkey']] = array('name' => $col['name'], 'id' => $col['id'], 'field' => $field);
            }
        }
        return $this->cols_cache[$tab];
    }

    protected function format_results($sorted_results)
    {
        $ret = array();
        $count = 0;
        foreach ($sorted_results as $r_token => $row) {
            list($tab, $id) = Utils_RecordBrowserCommon::decode_record_token($r_token);

            $search_details = $this->format_search_details($tab, $row);
            //create link with default label
            $ret[] = Utils_RecordBrowserCommon::create_default_linked_label($tab, $id) . $search_details;

            $count++;
            if ($count >= $this->limit) {
                break;
            }
        }
        return $ret;
    }

    protected function format_search_details($tab, $row)
    {
        $map = array();
        foreach ($row['fields'] as $field_id => $words_in_field) {
            $field_label = $this->get_field_label($tab, $field_id);
            foreach ($words_in_field as $word) {
                if (!isset($map[$word])) {
                    $map[$word] = array();
                }
                $map[$word][] = $field_label;
            }
        }
        $ret = array();
        foreach ($map as $word => $fields) {
            $ret[] = '<span style="color:red">' . $word . '</span> <span style="color:gray">(' . implode(', ', $fields) . ')</span>';
        }
        return ' - ' . implode(' ', $ret);
    }

}
