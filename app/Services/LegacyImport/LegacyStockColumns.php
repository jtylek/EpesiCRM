<?php

namespace App\Services\LegacyImport;

/**
 * The column lists of the tables of stock Epesi 1.9.1 (revision 20220911), derived from
 * tests/Fixtures/legacy-1.9.1-schema.sql (a test recomputes them from it and checks them against the
 * wizard's approved table hashes). Lists, not only hashes, so that "what this installation has beyond
 * stock" can be computed: every column of a RecordBrowser data table that is not here is a field added
 * by another module or by custom changes (LegacyCustomFieldPlanner turns it into a custom field).
 */
final class LegacyStockColumns
{
    /**
     * Recordset data tables whose extra columns the importers carry over as custom fields:
     * table => recordset tab. A recordset is listed only when its importer (Importer::carriesCustomFields())
     * fills the custom fields and reports what it did not carry.
     */
    public const RECORDSET_TABLES = [
        'company_data_1' => 'company',
        'contact_data_1' => 'contact',
        'task_data_1' => 'task',
        'phonecall_data_1' => 'phonecall',
        'crm_meeting_data_1' => 'crm_meeting',
    ];

    /** Columns every importer reads without tolerance: a table lacking one cannot be migrated. */
    public const REQUIRED = ['id', 'created_on'];

    /** Columns the importers read although stock 1.9.1 lacks them (an older or patched install has them): never extras. */
    public const IMPORTER_COLUMNS = [
        'company_data_1' => ['f_memo'],
        'contact_data_1' => ['f_memo'],
    ];

    /** @var array<string, list<string>> */
    public const COLUMNS = [
        'user_login' => ['id', 'login', 'active', 'admin'],
        'company_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_company_name', 'f_short_name', 'f_phone', 'f_fax', 'f_email', 'f_web_address', 'f_group', 'f_permission', 'f_address_1', 'f_address_2', 'f_city', 'f_country', 'f_zone', 'f_postal_code', 'f_tax_id'],
        'contact_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_last_name', 'f_first_name', 'f_company_name', 'f_related_companies', 'f_group', 'f_title', 'f_work_phone', 'f_mobile_phone', 'f_fax', 'f_email', 'f_web_address', 'f_address_1', 'f_address_2', 'f_city', 'f_country', 'f_zone', 'f_postal_code', 'f_permission', 'f_home_phone', 'f_home_address_1', 'f_home_address_2', 'f_home_city', 'f_home_country', 'f_home_zone', 'f_home_postal_code', 'f_birth_date', 'f_login', 'f_access'],
        'rc_mails_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_subject', 'f_contacts', 'f_employee', 'f_related', 'f_date', 'f_headers_data', 'f_body', 'f_from', 'f_to', 'f_thread', 'f_message_id', 'f_references'],
        'rc_mail_threads_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_subject', 'f_contacts', 'f_first_date', 'f_last_date'],
        'rc_mails_attachments' => ['mail_id', 'type', 'name', 'mime_id', 'attachment'],
        'rc_accounts_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_epesi_user', 'f_email', 'f_account_name', 'f_server', 'f_login', 'f_password', 'f_security', 'f_smtp_server', 'f_smtp_auth', 'f_smtp_login', 'f_smtp_password', 'f_smtp_security', 'f_default_account', 'f_archive_on_sending', 'f_use_epesi_archive_directories', 'f_imap_root', 'f_imap_delimiter'],
        'rc_multiple_emails_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_record_type', 'f_record_id', 'f_nickname', 'f_email'],
        'utils_attachment_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_edited_on', 'f_title', 'f_note', 'f_files', 'f_permission', 'f_sticky', 'f_crypted', 'f_attached_to'],
        'utils_filestorage' => ['id', 'filename', 'link', 'backref', 'created_on', 'created_by', 'deleted', 'file_id'],
        'utils_filestorage_files' => ['id', 'hash', 'deleted', 'size', 'type'],
        'task_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_title', 'f_description', 'f_employees', 'f_customers', 'f_status', 'f_priority', 'f_permission', 'f_longterm', 'f_deadline', 'f_timeless', 'f_related'],
        'phonecall_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_subject', 'f_customer', 'f_other_customer', 'f_other_customer_name', 'f_permission', 'f_employees', 'f_status', 'f_priority', 'f_phone', 'f_other_phone', 'f_other_phone_number', 'f_date_and_time', 'f_description', 'f_related'],
        'crm_meeting_data_1' => ['id', 'created_on', 'created_by', 'indexed', 'active', 'f_title', 'f_description', 'f_date', 'f_time', 'f_duration', 'f_employees', 'f_customers', 'f_status', 'f_priority', 'f_permission', 'f_recurrence_type', 'f_recurrence_end', 'f_recurrence_hash', 'f_related'],
    ];

    /** @return list<string>|null */
    public static function for(string $table): ?array
    {
        return self::COLUMNS[$table] ?? null;
    }
}
