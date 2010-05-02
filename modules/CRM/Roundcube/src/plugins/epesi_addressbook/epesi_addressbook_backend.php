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

  public function list_records($cols=null, $subset=0)
  {
    $this->result = $this->count();
    //$this->result->add(array('ID' => '111', 'name' => "epesi Contact", 'firstname' => "epesi", 'surname' => "Contact", 'email' => "epesi@roundcube.net"));
    $ret = DB::Execute('SELECT id as ID,f_first_name as firstname, f_last_name as surname, f_email as email FROM contact_data_1 WHERE active=1 AND f_email!=""  AND f_email is not null AND (f_permission<2 OR created_by=%d) ORDER BY f_last_name,f_first_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user']));
    while($row = $ret->FetchRow()) {
        $row['name'] = $row['surname'].' '.$row['firstname'];
        $this->result->add($row);
    }

    return $this->result;
  }

  public function search($fields, $value, $strict=false, $select=true)
  {
    $this->result = $this->count();
    //$this->result->add(array('ID' => '111', 'name' => "epesi Contact", 'firstname' => "epesi", 'surname' => "Contact", 'email' => "epesi@roundcube.net"));
    if($strict)
        $ret = DB::Execute('SELECT id as ID,f_first_name as firstname, f_last_name as surname, f_email as email FROM contact_data_1 WHERE active=1 AND f_email!=""  AND f_email is not null AND (f_permission<2 OR created_by=%d) AND (f_email=%s OR f_first_name=%s OR f_last_name=%s) ORDER BY f_last_name,f_first_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value,$value));
    else
        $ret = DB::Execute('SELECT id as ID,f_first_name as firstname, f_last_name as surname, f_email as email FROM contact_data_1 WHERE active=1 AND f_email!=""  AND f_email is not null AND (f_permission<2 OR created_by=%d) AND (f_email LIKE CONCAT("%%",%s,"%%") OR f_first_name LIKE CONCAT("%%",%s,"%%") OR f_last_name LIKE CONCAT("%%",%s,"%%")) ORDER BY f_last_name,f_first_name'.($subset?' LIMIT '.$subset:''),array($E_SESSION['user'],$value,$value,$value));
    while($row = $ret->FetchRow()) {
        $row['name'] = $row['surname'].' '.$row['firstname'];
        $this->result->add($row);
    }

    return $this->result;
  }

  public function count()
  {
    return new rcube_result_set(1, ($this->list_page-1) * $this->page_size);
  }

  public function get_result()
  {
    return $this->result;
  }

  public function get_record($id, $assoc=false)
  {
    //$this->list_records();
    $ret = DB::GetRow('SELECT id as ID,f_first_name as firstname, f_last_name as surname, f_email as email FROM contact_data_1 WHERE active=1 AND id=%d AND f_email!=""  AND f_email is not null AND (f_permission<2 OR created_by=%d)',array($id,$E_SESSION['user']));
    if(!$ret) return false;
    $ret['name'] = $ret['surname'].' '.$ret['firstname'];
    $this->result = new rcube_result_set(1);
    $this->result->add($ret);
    if($assoc)
        return $ret;
    return $this->result;
  }

}
