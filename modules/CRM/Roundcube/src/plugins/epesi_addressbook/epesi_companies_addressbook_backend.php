<?php

/**
 * epesi backend class for a custom address book
 *
 * This one just holds a static list of address records
 *
 * @author Paul Bukowski
 */
class epesi_companies_addressbook_backend extends rcube_addressbook
{
  public $primary_key = 'ID';
  public $readonly = true;

  private $cache;
  private $filter;
  private $result;

  public function __construct()
  {
    $this->ready = true;
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

    $queries = array();
    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\' ORDER BY field');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT '.DB::concat('id',DB::qstr('/'.$k)).' as ID, \'\' as firstname, \'\' as lastname, f_company_name as name, '.$f.' as email FROM company_data_1 WHERE active=1 AND '.$f.'!=\'\'  AND '.$f.' is not null AND (CAST(f_permission AS decimal)<2 OR created_by='.$E_SESSION['user'].'))';
    }

    $ret = DB::SelectLimit(implode(' UNION ',$queries).' UNION (SELECT '.DB::concat('cd.id',DB::qstr("/-"),'me.id').' as ID, \'\' as firstname, \'\' as lastname, f_company_name as name, me.f_email as email FROM company_data_1 cd INNER JOIN rc_multiple_emails_data_1 me ON (me.f_record_id=cd.id AND me.f_record_type=\'company\') WHERE cd.active=1 AND me.active=1 AND (CAST(cd.f_permission AS decimal)<2 OR cd.created_by='.$E_SESSION['user'].')) ORDER BY name',$length,$start_row);
    while($row = $ret->FetchRow()) {
        if(!isset($row['ID']) && isset($row['id'])) $row['ID'] = $row['id'];
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

  public function search($fields_to_search, $value, $strict=false, $select=true)
  {
    if($fields_to_search==='ID') {
        return $this->get_record($value);
    }

    global $E_SESSION;
    $this->result = $this->count();

    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\'');
    $m_cols = array();
    $m_cols2 = array();
    foreach($fields as $k=>$f) {
        $i = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $m_cols[] = 'c.'.$i;
        $m_cols2[] = $i;
    }
    if($m_cols) {
        if($strict) {
            $ret = DB::Execute('SELECT c.id as ID,c.f_company_name, m.f_email as memails, m.id as mid, '.implode(', ',$m_cols).' FROM company_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=\'company\') WHERE c.active=1 AND (CAST(c.f_permission AS decimal)<2 OR c.created_by=%d) AND ('.implode('='.DB::qstr($value).' OR ',$m_cols).'='.DB::qstr($value).' OR c.f_company_name=%s OR c.f_short_name=%s OR m.f_email=%s) ORDER BY c.f_company_name',array($E_SESSION['user'],$value,$value,$value));
        } else {
            $ret = DB::Execute('SELECT c.id as ID,c.f_company_name, m.f_email as memails, m.id as mid, '.implode(', ',$m_cols).' FROM company_data_1 c LEFT JOIN rc_multiple_emails_data_1 m ON (m.f_record_id=c.id AND m.f_record_type=\'company\') WHERE c.active=1 AND (CAST(c.f_permission AS decimal)<2 OR c.created_by=%d) AND ('.implode(' LIKE '.DB::concat(DB::qstr("%%"),DB::qstr($value),DB::qstr("%%")).' OR ',$m_cols).' LIKE '.DB::concat(DB::qstr("%%"),DB::qstr($value),DB::qstr("%%")).' OR c.f_company_name LIKE '.DB::concat(DB::qstr("%%"),'%s',DB::qstr("%%")).' OR c.f_short_name LIKE '.DB::concat(DB::qstr("%%"),'%s',DB::qstr("%%")).' OR m.f_email LIKE '.DB::concat(DB::qstr("%%"),'%s',DB::qstr("%%")).') ORDER BY c.f_company_name',array($E_SESSION['user'],$value,$value,$value));
        }
        $done_ids = array();
        while($row = $ret->FetchRow()) {
            if(!isset($row['ID']) && isset($row['id'])) $row['ID'] = $row['id'];
            $row2 = array('name'=>$row['f_company_name']);
            $id = $row['ID'];
            if(!isset($done_ids[$id])) {
                $done_ids[$id] = 1;
                foreach ($m_cols2 as $k=>$m) {
                    if(!$row[$m]) continue;
                    $row2['email'] = $row[$m];
                    $row2['ID'] = $id.'/'.$k;
                    $this->result->add($row2);
                }
            }
            if($row['memails']) {
                $row2['email'] = $row['memails'];
                $row2['ID'] = $id.'/'.(-$row['mid']);
                $this->result->add($row2);            
            }
        }
    }

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

    $queries = array();

    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\'');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT count(id) FROM company_data_1 WHERE active=1 AND '.$f.'!=\'\'  AND '.$f.' is not null AND (CAST(f_permission AS decimal)<2 OR created_by='.$E_SESSION['user'].'))';
    }
    $queries[] = '(SELECT count(me.id) FROM company_data_1 cd INNER JOIN rc_multiple_emails_data_1 me ON (me.f_record_id=cd.id AND me.f_record_type=\'company\') WHERE cd.active=1 AND me.active=1 AND (CAST(cd.f_permission AS decimal)<2 OR cd.created_by='.$E_SESSION['user'].'))';

    $ret = DB::GetOne('SELECT '.implode('+',$queries));

    $this->cache['count'] = (int) $ret;

    return $this->cache['count'];
  }

  public function get_result()
  {
    return $this->result;
  }

  public function get_record($id, $assoc=false)
  {
    global $E_SESSION;
    @list($id,$pos) = explode('/',$id);
    if(!isset($pos)) {
        $pos = 0;
    }
    if($pos>=0) {
        $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\' ORDER BY field');
        if(!$fields) return false;
        if(!isset($fields[$pos])) $pos = 0;
        $m = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($fields[$pos]));
        $ret = DB::GetRow('SELECT id as ID,\'\' as firstname, \'\' as surname, f_company_name as name, '.$m.' as email FROM company_data_1 WHERE active=1 AND id=%d AND '.$m.'!=\'\'  AND '.$m.' is not null AND (CAST(f_permission AS decimal)<2 OR created_by=%d)',array($id,$E_SESSION['user']));
    } else {
        $ret = DB::GetRow('SELECT id as ID,\'\' as firstname, \'\' as surname, f_company_name as name, (SELECT me.f_email FROM rc_multiple_emails_data_1 me WHERE me.id=%d) as email FROM company_data_1 WHERE active=1 AND id=%d AND (CAST(f_permission AS decimal)<2 OR created_by=%d)',array(-$pos,$id,$E_SESSION['user']));
    }
    if(!$ret) return false;
    if(!isset($ret['ID']) && isset($ret['id'])) $ret['ID'] = $ret['id'];
    $this->result = new rcube_result_set(1);
    $this->result->add($ret);
    if($assoc)
        return $ret;
    return $this->result;
  }

}
