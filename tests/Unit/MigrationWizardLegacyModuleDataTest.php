<?php

use Epesi\Modules\MigrationWizard\Detection\LegacyModuleData;
use PHPUnit\Framework\TestCase;

/** Which installed legacy modules own recordsets (ten tables each) or plain tables with data. */
class MigrationWizardLegacyModuleDataTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/legacy-module-data-'.getmypid().'-'.uniqid();
        $this->write('CRM/Contacts/ContactsInstall.php', <<<'PHP'
<?php
class CRM_ContactsInstall {
    function install() {
        Utils_RecordBrowserCommon::install_new_recordset('company', $fields);
        // Utils_RecordBrowserCommon::install_new_recordset('ghost_commented_out', $fields);
        Utils_RecordBrowserCommon::install_new_recordset("contact", $fields);
    }
}
PHP);
        $this->write('Base/User/UserInstall.php', <<<'PHP'
<?php
class Base_UserInstall {
    function install() {
        $ret = DB::CreateTable('user_login', "id I AUTO KEY");
        DB::CreateTable(Base_UserCommon::table_name, "id I");
        DB::CreateTable('user_unused_empty', "id I");
    }
}
PHP);
        $this->write('Base/User/UserCommon.php', "<?php\nclass Base_UserCommon { const table_name = 'user_extra'; }\n");
        $this->write('Base/About/AboutInstall.php', "<?php\nclass Base_AboutInstall { function install() { return true; } }\n");
        $this->write('Premium/Invoice/InvoiceInstall.php', "<?php\nclass Premium_InvoiceInstall { function install() {\n Utils_RecordBrowserCommon::install_new_recordset('premium_invoice', \$f);\n} }\n");
        $this->write('Premium/Invoice/patches/20141118_related.php', "<?php\nUtils_RecordBrowserCommon::install_new_recordset('premium_invoice_related', \$f);\nUtils_RecordBrowserCommon::install_new_recordset('premium_shared_by_patch', \$f);\n");
        $this->write('Premium/Other/OtherInstall.php', "<?php\nUtils_RecordBrowserCommon::install_new_recordset('premium_shared_by_patch', \$f);\n");
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    private function write(string $module, string $code): void
    {
        $path = $this->root.'/modules/'.$module;
        is_dir(dirname($path)) || mkdir(dirname($path), 0777, true);
        file_put_contents($path, $code);
    }

    /** @return array<string, int> table => rows: ten tables per recordset, plus plain and unowned tables */
    private function tables(): array
    {
        $tables = ['user_login' => 10, 'user_extra' => 0, 'user_unused_empty' => 0, 'session' => 5, 'modules' => 100];
        foreach (['company', 'contact', 'premium_invoice', 'premium_invoice_related', 'premium_shared_by_patch', 'premium_orphan_thing'] as $tab) {
            foreach (LegacyModuleData::RECORDSET_SUFFIXES as $suffix) {
                $tables[$tab.'_'.$suffix] = $suffix === 'data_1' ? 50 : 1;
            }
        }

        return $tables;
    }

    private function compose(): array
    {
        $installed = array_map(static fn (string $name): array => ['name' => $name, 'version' => '1'], ['Base_About', 'Base_User', 'CRM_Contacts', 'Premium_Invoice', 'Premium_Orphan', 'Premium_Other', 'Utils_RecordBrowser_CustomRecordsets']);
        $data = new LegacyModuleData;
        $owners = $data->sourceOwners($this->root, array_column($installed, 'name'));
        $counter = static fn (string $kind, string $name): array => match ($name) {
            'company' => [120, true],
            'contact' => [300, true],
            'premium_invoice' => [400000, false],
            'user_login' => [10, true],
            default => [0, true],
        };

        return $data->compose($installed, $owners, $this->tables(), array_merge(['custom_report'], ['company', 'contact', 'premium_invoice', 'premium_invoice_related', 'premium_shared_by_patch', 'premium_orphan_thing']), ['custom_report'], $counter);
    }

    public function test_ownership_is_read_from_install_files_and_patches_without_comments(): void
    {
        $owners = (new LegacyModuleData)->sourceOwners($this->root, ['CRM_Contacts', 'Base_User', 'Base_About', 'Premium_Invoice', 'Missing_Module']);

        $this->assertSame(['company', 'contact'], $owners['CRM_Contacts']['recordsets'], 'a commented-out call is ignored');
        $this->assertSame(['user_login', 'user_unused_empty', 'user_extra'], $owners['Base_User']['tables'], 'a class constant is resolved from the module source');
        $this->assertSame(['recordsets' => [], 'tables' => [], 'primary_recordsets' => [], 'primary_tables' => []], $owners['Base_About']);
        $this->assertSame(['premium_invoice'], $owners['Premium_Invoice']['primary_recordsets']);
        $this->assertContains('premium_invoice_related', $owners['Premium_Invoice']['recordsets']);
        $this->assertNull($owners['Missing_Module'], 'a module without readable source is unknown, not empty');
    }

    public function test_modules_with_data_are_listed_alphabetically_with_recordsets_collapsed(): void
    {
        $result = $this->compose();
        $modules = array_column($result['modules'], null, 'name');

        $this->assertSame(['Base_User', 'CRM_Contacts', 'Premium_Invoice', 'Premium_Orphan', 'Premium_Other', 'Utils_RecordBrowser_CustomRecordsets'], array_column($result['modules'], 'name'));
        $this->assertArrayNotHasKey('Base_About', $modules, 'a module with no tables is not listed');
        $this->assertSame(['Core Epesi', 'Premium'], [$modules['CRM_Contacts']['kind'], $modules['Premium_Invoice']['kind']]);
        $this->assertSame([['table' => 'user_login', 'rows' => 10, 'exact' => true]], $modules['Base_User']['tables'], 'empty plain tables are not shown');
        $this->assertSame([], $modules['Base_User']['recordsets']);
        $this->assertSame([['tab' => 'company', 'records' => 120, 'exact' => true, 'via' => 'source'], ['tab' => 'contact', 'records' => 300, 'exact' => true, 'via' => 'source']], $modules['CRM_Contacts']['recordsets']);
        $this->assertSame(['premium_invoice', 'premium_invoice_related'], array_column($modules['Premium_Invoice']['recordsets'], 'tab'), 'the related recordset is a recordset of its own');
        $this->assertFalse($modules['Premium_Invoice']['recordsets'][0]['exact'], 'a big table is an estimate');
    }

    public function test_a_recordset_belongs_to_the_module_whose_install_file_creates_it_then_to_fallbacks(): void
    {
        $modules = array_column($this->compose()['modules'], null, 'name');

        $this->assertSame('premium_shared_by_patch', $modules['Premium_Other']['recordsets'][0]['tab'], 'an install file wins over another module\'s patch');
        $this->assertSame('source', $modules['Premium_Other']['recordsets'][0]['via']);
        $this->assertSame([['tab' => 'premium_orphan_thing', 'records' => 0, 'exact' => true, 'via' => 'name']], $modules['Premium_Orphan']['recordsets'], 'last resort: the module name prefixes the tab');
        $this->assertSame('custom', $modules['Utils_RecordBrowser_CustomRecordsets']['recordsets'][0]['via'], 'user-made recordsets belong to the custom-recordsets module');
    }

    public function test_tables_are_accounted_per_recordset_and_the_rest_is_counted(): void
    {
        $result = $this->compose();

        // 6 recordsets present in the database x 10 tables + user_login/user_extra/user_unused_empty of the listed Base_User.
        $this->assertSame(63, $result['accounted_tables']);
        $this->assertSame(['session', 'modules'], $result['unaccounted_table_names']);
        $this->assertSame(count($this->tables()) - 63, $result['unaccounted_tables']);
        $this->assertSame(0, $result['unowned_recordsets']);
        $this->assertSame(7, $result['installed_modules']);
        $this->assertSame(5, $result['source_modules_read'], 'two installed modules have no readable source');
    }
}
