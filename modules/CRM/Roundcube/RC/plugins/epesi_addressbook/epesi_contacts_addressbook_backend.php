<?php

/**
 * epesi backend class for a custom address book
 *
 * This one just holds a static list of address records
 *
 * @author Paul Bukowski
 */
class epesi_contacts_addressbook_backend extends rcube_addressbook
{
  public $primary_key = 'ID';
  public $readonly = true;

  private $cache;
  private $filter;
  private $result;
  private $name;

  public function __construct($name)
  {
    $this->ready = true;
    $this->name = $name;
  }

  public function get_name()
  {
    return $this->name;
  }

  public function set_search_set($filter)
  {
    $this->filter = $filter;
  }

  public function get_search_set()
  {
    return $this->filter;
  }

  public function reset()
  {
    $this->result = null;
    $this->filter = null;
    $this->cache = null;
  }

  public function list_records($cols=null, $subset=0, $nocount=false)
  {
    if(isset($this->cache['search']) && $this->cache['search']) {
	unset($this->cache['search']);
	return $this->result;
    }

    global $E_SESSION;
    if ($nocount || $this->list_page <= 1) {
        // create dummy result, we don't need a count now
        $this->result = new rcube_result_set();
    } else {
        // count all records
        $this->result = $this->count();
    }
    if(!is_numeric($E_SESSION['user'])) return $this->result;
    
    $start_row = $subset < 0 ? $this->result->first + $this->page_size + $subset : $this->result->first;
    $length = $subset != 0 ? abs($subset) : $this->page_size;

      foreach ($this->_list_records(false, $length, $start_row) as $row) {
          $this->result->add($row);
      }

    $cnt = count($this->result->records);

    // update counter
    if ($nocount)
        $this->result->count = $cnt;
    else if ($this->list_page <= 1) {
        if ($cnt < $this->page_size && $subset == 0)
            $this->result->count = $cnt;
        else if (isset($this->cache['count']))
            $this->result->count = $this->cache['count'];
        else
            $this->result->count = $this->_count();
    }

    return $this->result;
  }

  public function search($fields_to_search, $value, $strict=false, $select=true, $nocount=false, $required=array())
  {
    if($fields_to_search==='ID') {
        return $this->get_record($value);
    }
    
    $vals = array('name'=>'-=-=-=-','firstname'=>'-=-=-=-','surname'=>'-=-=-=-','email'=>'-=-=-=-');
    if($fields_to_search==='*') $fields_to_search = array('name','firstname','surname','email');
    elseif(!is_array($fields_to_search)) $fields_to_search = array($fields_to_search);
    foreach($fields_to_search as $i=>$field)
	$vals[$field] = is_array($value)?$value[$i]:$value;
  
    $this->result = $this->count();

      foreach ($this->_search($vals, $strict) as $row) {
          $this->result->add($row);
      }

    $this->cache['search']=1;
    return $this->result;
  }

  public function count()
  {
    $count = isset($this->cache['count']) ? $this->cache['count'] : $this->_count();
    return new rcube_result_set($count, ($this->list_page-1) * $this->page_size);
  }

  private function _count()
  {
    global $E_SESSION;
    if(!is_numeric($E_SESSION['user'])) return 0;

      $this->cache['count'] = $this->_list_records(true);

    return $this->cache['count'];
  }

  public function get_result()
  {
    return $this->result;
  }

  public function get_record($id, $assoc=false)
  {
    global $E_SESSION;
    @list($id,$pos) = explode('_',$id);
    if(!isset($pos)) {
        $pos = 0;
    }
    if($pos>=0) {
        $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\' ORDER BY field');
        if(!$fields) return false;
        if(!isset($fields[$pos])) $pos = 0;
        $m = 'c.f_'.preg_replace('/[^a-z0-9]/','_',strtolower($fields[$pos]));
        $ret = DB::GetRow('SELECT c.id as ID,c.f_first_name as firstname, c.f_last_name as surname,com.f_company_name, '.$m.' as email FROM contact_data_1 c LEFT JOIN company_data_1 com ON com.id=c.f_company_name WHERE c.active=1 AND c.id=%d AND '.$m.'!=\'\'  AND '.$m.' is not null AND (CAST(c.f_permission as decimal)<2 OR c.created_by=%d)',array($id,$E_SESSION['user']));
    } else {
        $ret = DB::GetRow('SELECT c.id as ID,c.f_first_name as firstname, c.f_last_name as surname,com.f_company_name, (SELECT me.f_email FROM rc_multiple_emails_data_1 me WHERE me.id=%d) as email FROM contact_data_1 c LEFT JOIN company_data_1 com ON com.id=c.f_company_name WHERE c.active=1 AND c.id=%d AND (CAST(c.f_permission as decimal)<2 OR c.created_by=%d)',array(-$pos,$id,$E_SESSION['user']));
    }
    if(!$ret) return false;
    $ret['name'] = $ret['surname'].' '.$ret['firstname'].($ret['f_company_name']?' ('.$ret['f_company_name'].')':'');
    if(!isset($ret['ID']) && isset($ret['id'])) $ret['ID'] = $ret['id'];
    $this->result = new rcube_result_set(1);
    $this->result->add($ret);
    if($assoc)
        return $ret;
    return $this->result;
  }

    private function _list_records($count_mode = false, $length = -1, $start_row = -1)
    {
        $queries = array();
        $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\' ORDER BY field');
        $vals = array();
        foreach($fields as $k=>$f) {
            $f = Utils_RecordBrowserCommon::get_field_id($f);
            $crits = array("!$f" => '');
            $sql_field = "f_$f";
            $query = Utils_RecordBrowserCommon::build_query('contact', $crits);
            $vals = array_merge($vals, $query['vals']);
            $fields_to_select = $count_mode ? 'count(*)' : DB::Concat('id',DB::qstr('_'.$k)).' as ID, f_first_name as firstname, f_last_name as surname, '.DB::Concat('f_last_name',DB::qstr(' '),'f_first_name').' as name, '.$sql_field.' as email';
            $queries[] = '(SELECT ' . $fields_to_select .' FROM' . $query['sql'] . ')';
        }

        $query = Utils_RecordBrowserCommon::build_query('contact',null, false, array(), 'cd');
        $vals = array_merge($vals, $query['vals']);
        $fields_to_select = $count_mode ? 'count(*)' : DB::Concat('cd.id',DB::qstr("_-"),'me.id').' as ID, cd.f_first_name as firstname, cd.f_last_name as lastname, '.DB::Concat('f_last_name',DB::qstr(' '),'f_first_name').' as name, me.f_email as email';
        $queries[] = '(SELECT ' . $fields_to_select . ' FROM contact_data_1 cd INNER JOIN rc_multiple_emails_data_1 me ON (me.f_record_id=cd.id AND me.f_record_type=\'contact\') WHERE me.active=1 AND '. $query['where'] .')';
        if ($count_mode) {
            $ret = DB::GetOne('SELECT ' . implode('+', $queries), $vals);
            return (int) $ret;
        } else {
            $ret = DB::SelectLimit(implode(' UNION ', $queries) . ' ORDER BY name', $length, $start_row, $vals);
            $ret_array = array();
            while($row = $ret->FetchRow()) {
                if(!isset($row['ID']) && isset($row['id'])) $row['ID'] = $row['id'];
                $ret_array[] = $row;
            }
            return $ret_array;
        }
    }

    private function _search($vals, $strict = false)
    {
        $m_cols = array();
        $m_cols2 = array();
        $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\'');
        foreach($fields as $k=>$f) {
            $field_id = 'f_' . Utils_RecordBrowserCommon::get_field_id($f);
            $m_cols[] = 'c.'.$field_id;
            $m_cols2[] = $field_id;
        }
        $contact_query = Utils_RecordBrowserCommon::build_query('contact', null, false, array(), 'c');
        if ($strict) {
            $query_vals = array_merge($contact_query['vals'], array($vals['firstname'], $vals['surname'], $vals['email']));
            $ret = DB::Execute('SELECT c.id as ID,c.f_first_name as firstname, c.f_last_name as surname, m.f_email as memails, m.id as mid,com.f_company_name' . ($m_cols ? ',' . implode(', ', $m_cols) : '') . ' FROM contact_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=\'contact\') LEFT JOIN company_data_1 com ON com.id=c.f_company_name WHERE ' . $contact_query['where'] . ' AND (' . ($m_cols ? implode('=' . DB::qstr($vals['email']) . ' OR ', $m_cols) . '=' . DB::qstr($vals['email']) . ' OR ' : '') . 'c.f_first_name=%s OR c.f_last_name=%s OR m.f_email=%s) ORDER BY c.f_last_name,c.f_first_name', $query_vals);
        } else {
            $query_vals = array_merge($contact_query['vals'], array($vals['firstname'], $vals['surname'], $vals['email'], $vals['name']));
            $ret = DB::Execute('SELECT c.id as ID,c.f_first_name as firstname, c.f_last_name as surname, m.f_email as memails, m.id as mid,com.f_company_name' . ($m_cols ? ',' . implode(', ', $m_cols) : '') . ' FROM contact_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=\'contact\') LEFT JOIN company_data_1 com ON com.id=c.f_company_name WHERE ' . $contact_query['where'] . ' AND (' . ($m_cols ? implode(' LIKE ' . DB::Concat(DB::qstr("%%"), DB::qstr($vals['email']), DB::qstr("%%")) . ' OR ', $m_cols) . ' LIKE ' . DB::Concat(DB::qstr("%%"), DB::qstr($vals['email']), DB::qstr("%%")) . ' OR ' : '') . 'c.f_first_name LIKE ' . DB::Concat(DB::qstr("%%"), '%s', DB::qstr("%%")) . ' OR c.f_last_name LIKE ' . DB::Concat(DB::qstr("%%"), '%s', DB::qstr("%%")) . ' OR m.f_email LIKE ' . DB::Concat(DB::qstr("%%"), '%s', DB::qstr("%%")) . ' OR com.f_company_name LIKE ' . DB::Concat(DB::qstr("%%"), '%s', DB::qstr("%%")) . ') ORDER BY c.f_last_name,c.f_first_name', $query_vals);
        }
        $done_ids = array();
        $search_results = array();
        while($row = $ret->FetchRow()) {
            if(!isset($row['ID']) && isset($row['id'])) $row['ID'] = $row['id'];
            $row2 = array('name'=>$row['surname'].' '.$row['firstname'].($row['f_company_name']?' ('.$row['f_company_name'].')':''));
            $id = $row['ID'];
            if(!isset($done_ids[$id])) {
                $done_ids[$id] = 1;
                foreach ($m_cols2 as $k=>$m) {
                    $row2['email'] = $row[$m];
                    $row2['ID'] = $id.'_'.$k;
                    $search_results[] = $row2;
                }
            }
            if($row['memails']) {
                $row2['email'] = $row['memails'];
                $row2['ID'] = $id.'_'.(-$row['mid']);
                $search_results[] = $row2;
            }
        }
        return $search_results;
    }
}
