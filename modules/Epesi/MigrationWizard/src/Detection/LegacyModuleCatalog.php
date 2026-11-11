<?php

namespace Epesi\Modules\MigrationWizard\Detection;

/**
 * Which legacy module owns which table and file area, exactly (never a prefix
 * or directory-name guess). Ownership is only taken by modules that are in the
 * installation's own `modules` registry (see ModuleCoverage). Anything not
 * listed here stays unknown and is preserved.
 *
 * Verified against the legacy source: Base_User creates `user_login`;
 * CRM_Contacts creates the `company` and `contact` recordsets (their
 * `*_data_1` tables); CRM_Mail creates the rc_mails, rc_mail_threads,
 * rc_accounts and rc_multiple_emails recordsets and the rc_mails_attachments
 * table (stock files under data/CRM_Mail); Utils_Attachment the notes
 * recordset; Utils_FileStorage the utils_filestorage tables and
 * data/Utils_FileStorage. The recordsets' companion tables (`*_field`,
 * `*_edit_history`, ...) are deliberately not listed.
 */
final class LegacyModuleCatalog
{
    /** @return list<array{legacy_module: string, destination_module: string, source_tables: list<string>, source_file_areas: list<string>}> */
    public function knownModules(): array
    {
        return [
            [
                'legacy_module' => 'Base_User',
                'destination_module' => 'App',
                'source_tables' => ['user_login'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'CRM_Contacts',
                'destination_module' => 'Epesi/CRM/Contacts',
                'source_tables' => ['company_data_1', 'contact_data_1'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'CRM_Tasks',
                'destination_module' => 'Epesi/CRM/Tasks',
                'source_tables' => ['task_data_1'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'CRM_Meeting',
                'destination_module' => 'Epesi/CRM/Meetings',
                'source_tables' => ['crm_meeting_data_1'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'CRM_PhoneCall',
                'destination_module' => 'Epesi/CRM/PhoneCalls',
                'source_tables' => ['phonecall_data_1'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'CRM_Mail',
                'destination_module' => 'Epesi/Mail',
                'source_tables' => ['rc_mails_data_1', 'rc_mail_threads_data_1', 'rc_mails_attachments', 'rc_accounts_data_1', 'rc_multiple_emails_data_1'],
                'source_file_areas' => ['data/CRM_Mail'],
            ],
            [
                'legacy_module' => 'Utils_Attachment',
                'destination_module' => 'Epesi/Attachments',
                'source_tables' => ['utils_attachment_data_1'],
                'source_file_areas' => [],
            ],
            [
                'legacy_module' => 'Utils_FileStorage',
                'destination_module' => 'App',
                'source_tables' => ['utils_filestorage', 'utils_filestorage_files'],
                'source_file_areas' => ['data/Utils_FileStorage'],
            ],
        ];
    }
}
