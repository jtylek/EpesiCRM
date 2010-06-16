<?php

/**
 * epesi backend class for a custom address book
 *
 * This one just holds a static list of address records
 *
 * @author Thomas Bruederli
 */
class epesi_addressbook_backend extends rcube_addressbook
{
  public $primary_key = 'ID';
  public $readonly = true;

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
    //contacts
    $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\' ORDER BY field');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT CONCAT(\'P/'.$k.'/\',id) as ID, f_first_name as firstname, f_last_name as surname, CONCAT(f_last_name,\' \',f_first_name) as name, '.$f.' as email FROM contact_data_1 WHERE active=1 AND '.$f.'!=""  AND '.$f.' is not null AND (f_permission<2 OR created_by='.$E_SESSION['user'].'))';
    }
    //companies
    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\' ORDER BY field');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT CONCAT(\'C/'.$k.'/\',id) as ID, \'\' as firstname, \'\' as lastname, f_company_name as name, '.$f.' as email FROM company_data_1 WHERE active=1 AND '.$f.'!=""  AND '.$f.' is not null AND (f_permission<2 OR created_by='.$E_SESSION['user'].'))';
    }

    $ret = DB::Execute(implode(' UNION ',$queries).'ORDER BY name LIMIT '.$start_row.','.$length);
    while($row = $ret->FetchRow()) {
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

  public function search($fields, $value, $strict=false, $select=true)
  {
    global $E_SESSION;
    $this->result = $this->count();

    //contacts
    $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\'');
    $m_cols = array();
    foreach($fields as $k=>$f) {
        $m_cols[] = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
    }
    if($m_cols) {
        if($strict)
            $ret = DB::Execute('SELECT id as ID,f_first_name as firstname, f_last_name as surname, '.implode(', ',$m_cols).' FROM contact_data_1 WHERE active=1 AND (f_permission<2 OR created_by=%d) AND '.implode('!="" AND ',$m_cols).'!="" AND  '.implode(' is not null AND ',$m_cols).' is not null AND ('.implode('='.DB::qstr($value).' OR ',$m_cols).'='.DB::qstr($value).' OR f_first_name=%s OR f_last_name=%s) ORDER BY f_last_name,f_first_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value));
        else
            $ret = DB::Execute('SELECT id as ID,f_first_name as firstname, f_last_name as surname, '.implode(', ',$m_cols).' FROM contact_data_1 WHERE active=1 AND (f_permission<2 OR created_by=%d) AND '.implode('!="" AND ',$m_cols).'!="" AND  '.implode(' is not null AND ',$m_cols).' is not null AND ('.implode(' LIKE CONCAT("%%",'.DB::qstr($value).',"%%") OR ',$m_cols).' LIKE CONCAT("%%",'.DB::qstr($value).',"%%") OR f_first_name LIKE CONCAT("%%",%s,"%%") OR f_last_name LIKE CONCAT("%%",%s,"%%")) ORDER BY f_last_name,f_first_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value));
        while($row = $ret->FetchRow()) {
            $row2 = array('ID'=>'P/'.$row['ID'], 'name'=>$row['surname'].' '.$row['firstname']);
            foreach ($m_cols as $m) {
                $row2['email'] = $row[$m];
                $this->result->add($row2);
            }
        }
    }

    //companies
    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\'');
    $m_cols = array();
    foreach($fields as $k=>$f) {
        $m_cols[] = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
    }
    if($m_cols) {
        if($strict)
            $ret = DB::Execute('SELECT id as ID,f_company_name, '.implode(', ',$m_cols).' FROM company_data_1 WHERE active=1 AND (f_permission<2 OR created_by=%d) AND '.implode('!="" AND ',$m_cols).'!="" AND  '.implode(' is not null AND ',$m_cols).' is not null AND ('.implode('='.DB::qstr($value).' OR ',$m_cols).'='.DB::qstr($value).' OR f_company_name=%s OR f_short_name=%s) ORDER BY f_company_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value));
        else
            $ret = DB::Execute('SELECT id as ID,f_company_name, '.implode(', ',$m_cols).' FROM company_data_1 WHERE active=1 AND (f_permission<2 OR created_by=%d) AND '.implode('!="" AND ',$m_cols).'!="" AND  '.implode(' is not null AND ',$m_cols).' is not null AND ('.implode(' LIKE CONCAT("%%",'.DB::qstr($value).',"%%") OR ',$m_cols).' LIKE CONCAT("%%",'.DB::qstr($value).',"%%") OR f_company_name LIKE CONCAT("%%",%s,"%%") OR f_short_name LIKE CONCAT("%%",%s,"%%")) ORDER BY f_company_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value));
        while($row = $ret->FetchRow()) {
            $row2 = array('ID'=>'C/'.$row['ID'], 'name'=>$row['f_company_name']);
            foreach ($m_cols as $m) {
                $row2['email'] = $row[$m];
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

    //contacts
    $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\'');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT count(id) FROM contact_data_1 WHERE active=1 AND '.$f.'!=""  AND '.$f.' is not null AND (f_permission<2 OR created_by='.$E_SESSION['user'].'))';
    }
    //companies
    $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\'');
    foreach($fields as $k=>$f) {
        $f = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($f));
        $queries[] = '(SELECT count(id) FROM company_data_1 WHERE active=1 AND '.$f.'!=""  AND '.$f.' is not null AND (f_permission<2 OR created_by='.$E_SESSION['user'].'))';
    }

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
    @list($type,$pos,$id) = explode('/',$id);
    if(!isset($id)) {
        $id = $pos;
        $pos = 0;
    }
    if($type=='P') {
        $fields = DB::GetCol('SELECT field FROM contact_field WHERE field LIKE \'%mail%\' ORDER BY field');
        if(!$fields) return false;
        if(!isset($fields[$pos])) $pos = 0;
        $m = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($fields[$pos]));
        $ret = DB::GetRow('SELECT id as ID,f_first_name as firstname, f_last_name as surname, '.$m.' as email FROM contact_data_1 WHERE active=1 AND id=%d AND '.$m.'!=""  AND '.$m.' is not null AND (f_permission<2 OR created_by=%d)',array($id,$E_SESSION['user']));
        if(!$ret) return false;
        $ret['name'] = $ret['surname'].' '.$ret['firstname'];
        $this->result = new rcube_result_set(1);
        $this->result->add($ret);
        if($assoc)
            return $ret;
        return $this->result;
    } elseif($type=='C') {
        $fields = DB::GetCol('SELECT field FROM company_field WHERE field LIKE \'%mail%\' ORDER BY field');
        if(!$fields) return false;
        if(!isset($fields[$pos])) $pos = 0;
        $m = 'f_'.preg_replace('/[^a-z0-9]/','_',strtolower($fields[$pos]));
        $ret = DB::GetRow('SELECT id as ID,\'\' as firstname, \'\' as surname, f_company_name as name, '.$m.' as email FROM company_data_1 WHERE active=1 AND id=%d AND '.$m.'!=""  AND '.$m.' is not null AND (f_permission<2 OR created_by=%d)',array($id,$E_SESSION['user']));
        if(!$ret) return false;
        $this->result = new rcube_result_set(1);
        $this->result->add($ret);
        if($assoc)
            return $ret;
        return $this->result;
    }
    return false;
  }

}
