<?php

/**
 * Every e-mail address the Epesi user may see: contacts' and companies' own,
 * plus the extra addresses (epesi_mail_addresses) of those records. Ids are
 * prefixed by kind — c<contact>, k<company>, a<extra address>.
 *
 * Visibility repeats HasOwnershipVisibility (modules/Epesi/RecordBrowser/src/
 * Models/Concerns/HasOwnershipVisibility.php) and the Contact/Company hooks
 * in SQL, since Roundcube can't run Eloquent: super_admin and manager see
 * everything; anyone else sees what isn't private (permission 2), what they
 * created, their own contact and their own company. A change to that rule has
 * to be made here as well.
 */
class epesi_addressbook_backend extends rcube_addressbook
{
    public $primary_key = 'ID';

    public $readonly = true;

    public $groups = false;

    /** @var rcube_db */
    private $db;

    /** @var array<string, mixed> the Epesi login ($_SESSION['epesi']) */
    private $epesi;

    /** @var string|null SQL condition on the listing's `t` rows */
    private $filter;

    /** @var rcube_result_set|null */
    private $result;

    /**
     * @param  array<string, mixed>  $epesi
     */
    public function __construct(rcube_db $db, array $epesi)
    {
        $this->db = $db;
        $this->epesi = $epesi;
        $this->ready = true;
    }

    public function get_name()
    {
        return 'CRM';
    }

    public function set_search_set($filter): void
    {
        $this->filter = $filter;
    }

    public function get_search_set()
    {
        return $this->filter;
    }

    public function reset(): void
    {
        $this->result = null;
        $this->filter = null;
    }

    public function list_records($cols = null, $subset = 0, $nocount = false)
    {
        $offset = ($this->list_page - 1) * $this->page_size;
        $limit = $this->page_size;

        // A subset is part of the current page: its first (positive) or
        // last (negative) records.
        if ($subset < 0) {
            $offset += $this->page_size + $subset;
            $limit = -$subset;
        } elseif ($subset > 0) {
            $limit = $subset;
        }

        $query = $this->db->limitquery('SELECT * FROM ('.$this->rows().') t WHERE '.$this->where().' ORDER BY t.name, t.email', $offset, $limit);

        $this->result = new rcube_result_set(0, $offset);

        while ($row = $this->db->fetch_assoc($query)) {
            $this->result->add($row);
        }

        $this->result->count = $nocount ? count($this->result->records) : $this->count()->count;

        return $this->result;
    }

    public function search($fields, $value, $mode = 0, $select = true, $nocount = false, $required = [])
    {
        if ($fields === $this->primary_key || $fields === [$this->primary_key]) {
            $ids = array_map([$this->db, 'quote'], (array) (is_string($value) ? explode(',', $value) : $value));
            $where = $ids === [] ? '1 = 0' : 't.ID IN ('.implode(', ', $ids).')';
        } else {
            $value = trim(is_array($value) ? implode(' ', $value) : (string) $value);
            $where = $value === '' ? '1 = 1' : '('.$this->db->ilike('t.name', $this->pattern($value, (int) $mode))
                .' OR '.$this->db->ilike('t.email', $this->pattern($value, (int) $mode)).')';
        }

        $this->set_search_set($where);

        return $select ? $this->list_records(null, 0, $nocount) : $this->count();
    }

    public function count()
    {
        $row = $this->db->fetch_assoc($this->db->query('SELECT COUNT(*) AS n FROM ('.$this->rows().') t WHERE '.$this->where()));

        return new rcube_result_set((int) ($row['n'] ?? 0), ($this->list_page - 1) * $this->page_size);
    }

    public function get_result()
    {
        return $this->result;
    }

    public function get_record($id, $assoc = false)
    {
        $row = $this->db->fetch_assoc($this->db->query('SELECT * FROM ('.$this->rows().') t WHERE t.ID = ?', (string) $id));

        if ($assoc) {
            return $row ?: null;
        }

        $this->result = new rcube_result_set($row ? 1 : 0);

        if ($row) {
            $this->result->add($row);
        }

        return $this->result;
    }

    private function where(): string
    {
        return $this->filter ?: '1 = 1';
    }

    private function pattern(string $value, int $mode): string
    {
        $value = strtr($value, ['%' => '\\%', '_' => '\\_']);

        if ($mode & rcube_addressbook::SEARCH_STRICT) {
            return $value;
        }

        return ($mode & rcube_addressbook::SEARCH_PREFIX) ? $value.'%' : '%'.$value.'%';
    }

    /** The UNION of every visible address, as rows of ID, name, firstname, surname, email. */
    private function rows(): string
    {
        if (empty($this->epesi['user_id'])) {
            return "SELECT '' AS ID, '' AS name, '' AS firstname, '' AS surname, '' AS email FROM contacts WHERE 1 = 0";
        }

        $contactName = 'TRIM('.$this->db->concat("COALESCE(c.first_name, '')", "' '", "COALESCE(c.last_name, '')").')';
        $contacts = 'c.deleted_at IS NULL AND '.$this->visible('c', 'c.user_id = '.(int) $this->epesi['user_id']);
        $companies = 'k.deleted_at IS NULL AND '.$this->visible('k', 'k.id = '.(int) ($this->epesi['company_id'] ?? 0));

        return implode(' UNION ALL ', [
            'SELECT '.$this->db->concat("'c'", 'c.id').' AS ID, '.$contactName.' AS name, c.first_name AS firstname, c.last_name AS surname, c.email AS email'
                ." FROM contacts c WHERE c.email IS NOT NULL AND c.email <> '' AND ".$contacts,
            'SELECT '.$this->db->concat("'k'", 'k.id').' AS ID, k.company_name AS name, NULL AS firstname, NULL AS surname, k.email AS email'
                ." FROM companies k WHERE k.email IS NOT NULL AND k.email <> '' AND ".$companies,
            'SELECT '.$this->db->concat("'a'", 'a.id').' AS ID, '.$contactName.' AS name, c.first_name AS firstname, c.last_name AS surname, a.email AS email'
                ." FROM epesi_mail_addresses a INNER JOIN contacts c ON a.addressable_type = 'contact' AND c.id = a.addressable_id WHERE ".$contacts,
            'SELECT '.$this->db->concat("'a'", 'a.id').' AS ID, k.company_name AS name, NULL AS firstname, NULL AS surname, a.email AS email'
                ." FROM epesi_mail_addresses a INNER JOIN companies k ON a.addressable_type = 'company' AND k.id = a.addressable_id WHERE ".$companies,
        ]);
    }

    /** HasOwnershipVisibility's rule; $own is the model's extra "yours" condition. */
    private function visible(string $alias, string $own): string
    {
        if (! empty($this->epesi['sees_all'])) {
            return '1 = 1';
        }

        return "({$alias}.permission <> 2 OR {$alias}.created_by = ".(int) $this->epesi['user_id']." OR {$own})";
    }
}
