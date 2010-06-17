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
    $this->add_hook('address_sources', array($this, 'address_sources'));
    $this->add_hook('get_address_book', array($this, 'get_address_book'));

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
    $p['sources'][$this->contacts_abook] = array('id' => $this->contacts_abook, 'name' => 'Epesi Contacts', 'readonly' => true);
    $p['sources'][$this->companies_abook] = array('id' => $this->companies_abook, 'name' => 'Epesi Companies', 'readonly' => true);
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

}
