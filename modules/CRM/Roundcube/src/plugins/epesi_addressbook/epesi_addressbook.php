<?php

/**
 * Sample plugin to add a new address book
 * with just a static list of contacts
 */
class epesi_addressbook extends rcube_plugin
{
  private $contacts_abook = 'epesi_contacts';
  private $companies_abook = 'epesi_companies';

  public function init()
  {
    $this->add_hook('addressbooks_list', array($this, 'address_sources'));
    $this->add_hook('addressbook_get', array($this, 'get_address_book'));
    $this->add_hook('contact_create', array($this, 'create_contact'));

    // use this address book for autocompletion queries
    // (maybe this should be configurable by the user?)
    $config = rcmail::get_instance()->config;
    $sources = $config->get('autocomplete_addressbooks', array('sql'));
    if (!in_array($this->contacts_abook, $sources)) {
      $sources[] = $this->contacts_abook;
    }
    if (!in_array($this->companies_abook, $sources)) {
      $sources[] = $this->companies_abook;
    }
    $config->set('autocomplete_addressbooks', $sources);
  }

  public function address_sources($p)
  {
    global $RCMAIL;
    $p['sources'][$this->contacts_abook] = array('id' => $this->contacts_abook, 'name' => 'Epesi Contacts', 'readonly' => true);
    $p['sources'][$this->companies_abook] = array('id' => $this->companies_abook, 'name' => 'Epesi Companies', 'readonly' => true);
    if($RCMAIL->task == "addressbook")
        unset($p['sources'][0]);
    return $p;
  }

  public function get_address_book($p)
  {
    if ($p['id'] === $this->contacts_abook) {
      require_once(dirname(__FILE__) . '/epesi_contacts_addressbook_backend.php');
      $p['instance'] = new epesi_contacts_addressbook_backend;
    } elseif($p['id'] === $this->companies_abook) {
      require_once(dirname(__FILE__) . '/epesi_companies_addressbook_backend.php');
      $p['instance'] = new epesi_companies_addressbook_backend;    
    }

    return $p;
  }


  public function create_contact($r) {
    global $OUTPUT;
    $mail = $r['record']['email'];
    require_once(dirname(__FILE__) . '/epesi_contacts_addressbook_backend.php');
    $contacts = new epesi_contacts_addressbook_backend();
    $ret = $contacts->search(null,$mail,true,false);
    if(count($ret->records)) {
      $OUTPUT->show_message('contactexists'.print_r($ret,true), 'warning');
    } else {
      require_once(dirname(__FILE__) . '/epesi_companies_addressbook_backend.php');
      $companies = new epesi_companies_addressbook_backend();    
      $ret = $companies->search(null,$mail,true,false);
      if(count($ret->records)) {
        $OUTPUT->show_message('contactexists', 'warning');      
      } else {
        if(isset($r['record']['firstname']) && $r['record']['firstname']!=="" && isset($r['record']['surname']) && $r['record']['surname']!=="")
            $name = array($r['record']['firstname'],$r['record']['surname']);
        else
            $name = explode(' ',$r['record']['name'],2);
        if(count($name)<2) {
          $OUTPUT->show_message('errorsavingcontact', 'warning');
        } else {
          $loc = Base_RegionalSettingsCommon::get_default_location();
          Utils_RecordBrowserCommon::new_record('contact',array('first_name'=>$name[0],'last_name'=>$name[1],'email'=>$mail,'permission'=>0,'country'=>$loc['country']));
          $OUTPUT->show_message('addedsuccessfully', 'confirmation');
        }
      }
    }
    $OUTPUT->send();
//    return array('abort'=>true);
  }
  
}
