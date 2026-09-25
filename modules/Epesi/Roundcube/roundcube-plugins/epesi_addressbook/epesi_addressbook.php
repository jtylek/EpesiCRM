<?php

require_once __DIR__.'/epesi_addressbook_backend.php';

/**
 * Epesi's contacts and companies as a read-only "CRM" address book, also used
 * for autocomplete in compose — the port of Epesi's epesi_addressbook plugin,
 * with its two books (CRM Contacts, CRM Companies) folded into one.
 */
class epesi_addressbook extends rcube_plugin
{
    public const SOURCE = 'epesi_crm';

    public function init()
    {
        $this->add_hook('addressbooks_list', [$this, 'address_sources']);
        $this->add_hook('addressbook_get', [$this, 'get_address_book']);

        $config = rcmail::get_instance()->config;
        $sources = (array) $config->get('autocomplete_addressbooks', ['sql']);

        if (! in_array(self::SOURCE, $sources, true)) {
            $sources[] = self::SOURCE;
            $config->set('autocomplete_addressbooks', $sources);
        }
    }

    public function address_sources($args)
    {
        $args['sources'][self::SOURCE] = [
            'id' => self::SOURCE,
            'name' => 'CRM',
            'readonly' => true,
            'groups' => false,
        ];

        return $args;
    }

    public function get_address_book($args)
    {
        if ($args['id'] === self::SOURCE) {
            $args['instance'] = new epesi_addressbook_backend(rcmail::get_instance()->get_dbh(), $_SESSION['epesi'] ?? []);
        }

        return $args;
    }
}
