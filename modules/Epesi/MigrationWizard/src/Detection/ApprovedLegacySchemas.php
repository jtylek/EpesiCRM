<?php

namespace Epesi\Modules\MigrationWizard\Detection;

/**
 * The legacy releases the wizard may act on, decided per legacy module (never
 * for the whole database): an exact version plus, for every Core module that
 * has an importer, the exact shape of the tables it owns (LegacyModuleCatalog).
 * Premium, Custom and unknown modules and tables are not part of this and never
 * make an installation unapproved; they are preserved untouched.
 *
 * A module's approved shape is one or more accepted variants. A variant maps
 * each table the module owns to the SchemaFingerprint of that table's column
 * list, so a mismatch is reported per table (missing, or columns differ: an
 * extra column on a Core table, a column the release does not have). Adding an
 * entry is a decision (it unlocks the rename of a real installation), so each
 * names the schema fixture that proves it; a test recomputes them all.
 */
final class ApprovedLegacySchemas
{
    /**
     * Epesi 1.9.1 (revision 20220911), derived from the 268-table
     * tests/Fixtures/legacy-1.9.1-schema.sql.
     *
     * @var array<string, array<string, string>> module => table => table fingerprint
     */
    public const EPESI_191_STOCK = [
        'Base_User' => [
            'user_login' => 'e66268a078a7228d62eb5316bd4d8c53b9577509ba318acda931f10f9ca70518',
        ],
        'CRM_Contacts' => [
            'company_data_1' => 'fb0be722ee20e0658a69ab0d3887aec6075a3c4d9dd3318ae71b0f5ae1a92851',
            'contact_data_1' => '13f6a604b3e0970276ee5de42fe4de8f5630a55b84116796158b58ffe279c6c6',
        ],
        'CRM_Tasks' => [
            'task_data_1' => '985fd207603c2238f19fd3dbc47c6907d03f31738fc8ae5b593d8faf75f47fbb',
        ],
        'CRM_PhoneCall' => [
            'phonecall_data_1' => 'e43e5c06f3b5dd444bbd5424e1b2b496dbc88d9a023fba7c409de24233b5e42a',
        ],
        'CRM_Meeting' => [
            'crm_meeting_data_1' => '1aed07a4022131644750638a527a58507cf991c644d300f17a4470262ce50510',
        ],
        'CRM_Mail' => [
            'rc_mails_data_1' => '0c7cafd91c5484a3a7dcf52d037c62b97f04705e3f8646ef98c575e6c5edb8ca',
            'rc_mail_threads_data_1' => 'b7f44acc1185cb361825bbec59579b084b166d7b99a150abc9207ec3c7cde161',
            'rc_mails_attachments' => '76ec18498b482c31806296d6d8e229a7c2d076f71e0c661721b28b907e9905a2',
            'rc_accounts_data_1' => '751eee2945e6b3c89684bcd10ec7b7366bf4785ef19f970a19d52a28c141862a',
            'rc_multiple_emails_data_1' => '077f42df606b301b041df1f680b05e1556417b5472a3c3cc9631a584f0709de4',
        ],
        'Utils_Attachment' => [
            'utils_attachment_data_1' => '0377a159eee46850eb177c154c173aa5065fe7476c422d1eb595ebd622942f47',
        ],
        'Utils_FileStorage' => [
            'utils_filestorage' => 'dd764ac5d3f091fcf321af64423d78281d8eaecacc4c39fe01c44121286d946b',
            'utils_filestorage_files' => '8e5c9860aa47025773689b89477c00c333413417d8c132a222a4db477555eb0a',
        ],
    ];

    /**
     * CRM_Mail after the legacy repository's own patch
     * 20260629_mail_attachments_to_filestorage.php (it adds
     * rc_mails_attachments.file_id): the stock shape plus that one column. Not
     * proven by a real fixture; derived from the patch, and a test recomputes
     * it from the stock fixture.
     */
    public const CRM_MAIL_PATCHED_ATTACHMENTS = '566874f56f2690d0bd08efe98f9993165f41a61ead82112233847edb4019b475';

    /** @return list<array{version: string, modules: array<string, list<array<string, string>>>}> */
    public function all(): array
    {
        $patchedMail = self::EPESI_191_STOCK['CRM_Mail'];
        $patchedMail['rc_mails_attachments'] = self::CRM_MAIL_PATCHED_ATTACHMENTS;

        $modules = [];
        foreach (self::EPESI_191_STOCK as $module => $tables) {
            $modules[$module] = [$tables];
        }
        $modules['CRM_Mail'][] = $patchedMail;

        return [['version' => '1.9.1', 'modules' => $modules]];
    }

    /** @return list<string> */
    public function versions(): array
    {
        return array_column($this->all(), 'version');
    }
}
