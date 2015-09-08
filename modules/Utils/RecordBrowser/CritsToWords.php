<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_CritsToWords
{
    protected $tab;
    protected $fields;
    protected $fields_by_id;

    protected $html_decoration = true;
    protected static $empty_c = array('str' => '');


    function __construct($tab)
    {
        $this->tab = $tab;
        $this->fields = Utils_RecordBrowserCommon::init($tab);
        $this->fields_by_id = Utils_RecordBrowserCommon::$hash;
    }

    public function enable_html_decoration($val = true)
    {
        $this->html_decoration = $val;
    }

    public function to_words(Utils_RecordBrowser_CritsInterface $crits)
    {
        $ret = $this->to_words_internal($crits);
        return $ret['str'];
    }

    protected function cc($str)
    {
        return "($str)";
    }

    protected function to_words_internal(Utils_RecordBrowser_CritsInterface $crits)
    {
        if ($crits->is_active() == false) {
            return self::$empty_c;
        }
        if ($crits instanceof Utils_RecordBrowser_CritsSingle) {
            return $this->build_single_crit_to_words($crits);
        } elseif ($crits instanceof Utils_RecordBrowser_Crits) {
            return $this->build_compound_crits_to_words($crits);
        } elseif ($crits instanceof Utils_RecordBrowser_CritsRawSQL) {
            return $this->build_raw_sql_crits_to_words($crits);
        }
        return self::$empty_c;
    }

    protected function build_compound_crits_to_words(Utils_RecordBrowser_Crits $crits)
    {
        $parts = array();
        foreach ($crits->get_component_crits() as $c) {
            $words = $this->to_words_internal($c);
            if ($words['str']) {
                $parts[] = $words;
            }
        }
        if (!$parts) {
            return self::$empty_c;
        }
        $multiple = (count($parts) > 1);
        foreach ($parts as $k => $p) {
            $parts[$k] = ($multiple && $p['multiple']) ? $this->cc($p['str']) : $p['str'];
        }
        $join_operator = strtolower($crits->get_join_operator());
        $glue = ' ' . _V($join_operator) . ' ';
        $neg = $crits->get_negation() ? ' ' . __('Not') : '';
        $str = implode($glue, $parts);
        if ($neg) {
            if ($multiple) $str = $this->cc($str);
            $str = "$neg $str";
            $multiple = false;
        }
        return array('str' => $str, 'multiple' => $multiple);
    }

    protected function build_raw_sql_crits_to_words(Utils_RecordBrowser_CritsRawSQL $crits)
    {
        $sql = $crits->get_negation() ? $crits->get_negation_sql() : $crits->get_sql();
        $value = implode(', ', $crits->get_vals());
        $ret = __('Raw SQL') . ': ' . "'{$sql}'" . __('with values') . ': ' . "({$value})";
        return array('str' => $ret, 'multiple' => true);
    }

    protected function build_single_crit_to_words(Utils_RecordBrowser_CritsSingle $crits)
    {
        $value = $crits->get_value();
        $operator = $crits->get_operator();
        list($field, $subfield) = $this->parse_subfield_from_field($crits->get_field());
        $negation = $crits->get_negation();
        $field_definition = $this->get_field_definition($field);
        $subquery_generated = false;

        if ($subfield) {
            $tab2 = isset($field_definition['ref_table']) ? $field_definition['ref_table'] : false;
            $single_tab = !($tab2 == '__RECORDSETS__' || count(explode(',', $tab2)) > 1);
            if ($tab2 && $single_tab) {
                $cb = new self($tab2);
                $cb->enable_html_decoration($this->html_decoration);
                $value = $cb->to_words(new Utils_RecordBrowser_CritsSingle($subfield, $operator, $value, $negation, $crits->get_raw_sql_value()));
                $subquery_generated = true;
            }
        }

        if (!is_array($value)) $value = array($value);
        foreach ($value as $k => $v) {
            if ($v === '') {
                $value[$k] = __('empty');
            } elseif (is_bool($v)) {
                $value[$k] = $v ? __('true') : __('false');
            } else {
                if ($field == ':Created_on' || $field == ':Edited_on') {
                    if (isset(Utils_RecordBrowserCommon::$date_values[$v])) {
                        $value[$k] = Utils_RecordBrowserCommon::$date_values[$v];
                    } else {
                        $value[$k] = Base_RegionalSettingsCommon::time2reg($v);
                    }
                } elseif ($field == ':Created_by') {
                    if (is_numeric($v)) {
                        $value[$k] = Base_UserCommon::get_user_login($v);
                    }
                } elseif ($field_definition) {
                    if (is_numeric($v) || $field_definition['commondata'] || !isset($field_definition['ref_table'])) {
                        $new_val = Utils_RecordBrowserCommon::get_val($this->tab, $field, array($field => $v), true);
                        if ($new_val) {
                            $value[$k] = $new_val;
                        }
                    }
                }
            }
            if ($this->html_decoration) {
                if (!$subquery_generated) {
                    $value[$k] = '<strong>' . $value[$k] . '</strong>';
                }
            }
        }
        if ($operator == '!=') {
            $negation ^= $operator == '!=';
            $operator = '=';
        }
        switch ($field) {
            case ':Fav':  $field = __('Favorite status'); break;
            case ':Recent': $field = __('Recently viewed'); break;
            case ':Sub': $field = __('Subscription status'); break;
            case ':Created_by': $field = __('Created by'); break;
            case ':Created_on': $field = __('Created on'); break;
            case ':Edited_on': $field = __('Edited on'); break;
            case 'id': $field = __('ID'); break;
            default:
                if ($field_definition) {
                    $field = _V($field_definition['name']);
                }
        }
        if ($this->html_decoration) {
            $field = "<strong>$field</strong>";
        }

        $operand = ($negation ? __('is not') : __('is') ) . ' ';
        if ($subquery_generated) {
            $operand = __('is set to record where');
        } else {
            switch ($operator) {
                case '<' : $operand .= __('smaller than'); break;
                case '<=' : $operand .= __('smaller or equal to'); break;
                case '>' : $operand .= __('greater than'); break;
                case '>=' : $operand .= __('greater or equal to'); break;
                case 'LIKE' : $operand .= __('like'); break;
                default:
                    $operand .= __('equal to');
            }
        }

        $value_str = implode(' ' . __('or') . ' ', $value);
        if (count($value) > 1) {
            $value_str = "($value_str)";
        }
        $ret = "{$field} {$operand} {$value_str}";
        return array('str' => $ret, 'multiple' => false);
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

}