<?php

namespace Tests\Unit;

use App\Services\LegacyImport\LegacyCustomFieldPlanner;
use App\Services\LegacyImport\LegacyStockColumns;
use PHPUnit\Framework\TestCase;

/**
 * The type mapping of AI-shared/recordbrowser/fields.md: every extra column of a legacy recordset
 * becomes a custom field; none is dropped.
 */
class LegacyCustomFieldPlannerTest extends TestCase
{
    private function plan(array $extraColumns, array $fieldRows, array $callbacks = [], array $sql = []): array
    {
        return (new LegacyCustomFieldPlanner)->plan('company', [...LegacyStockColumns::COLUMNS['company_data_1'], ...$extraColumns], $sql, $fieldRows, $callbacks);
    }

    private static function row(string $name, string $type, string $param = '', array $more = []): array
    {
        return $more + ['field' => $name, 'caption' => null, 'type' => $type, 'param' => $param, 'visible' => 0, 'required' => 0, 'filter' => 0, 'export' => 1, 'active' => 1, 'position' => 20, 'help' => null];
    }

    public function test_the_three_extras_of_the_viking_company_table(): void
    {
        $fields = $this->plan(
            ['f_account_manager', 'f_default_transactions_markup', 'f_invoice_email'],
            [
                self::row('Default Transactions Markup', 'float'),
                self::row('Account Manager', 'select', 'contact::Last Name|First Name;CRM_ContactsCommon::contact_format_no_company;CRM_Contacts_AccountManagerCommon::crits_accountmanager', ['visible' => 1, 'filter' => 1]),
                self::row('Invoice Email', 'text', '128'),
            ],
            ['Invoice Email' => ['CRM_ContactsCommon::display_email', 'CRM_ContactsCommon::QFfield_email']],
        );

        $this->assertSame(['account_manager', 'default_transactions_markup', 'invoice_email'], array_column($fields, 'name'));
        [$manager, $markup, $email] = $fields;
        $this->assertSame('relation', $manager['type']);
        $this->assertSame(['recordset' => 'contact'], $manager['params']);
        $this->assertSame('relation', $manager['fill']);
        $this->assertSame('Contact link', $manager['type_label']);
        $this->assertTrue($manager['show_in_table']);
        $this->assertTrue($manager['filterable']);
        $this->assertSame('decimal', $markup['type']);
        $this->assertSame('email', $email['type']);
        $this->assertSame(['length' => 128], $email['params']);
        $this->assertSame([null, null, null], array_column($fields, 'pending'));
    }

    public function test_stock_columns_and_the_importers_own_extra_column_are_not_extras(): void
    {
        $this->assertSame([], $this->plan(['f_memo'], []));
    }

    public function test_a_column_no_field_row_declares_is_typed_from_the_column(): void
    {
        $fields = $this->plan(['f_a', 'f_b', 'f_c', 'f_d', 'f_e'], [], [], [
            'f_a' => ['type_name' => 'varchar', 'type' => 'varchar(80)'],
            'f_b' => ['type_name' => 'int', 'type' => 'int(11)'],
            'f_c' => ['type_name' => 'datetime', 'type' => 'datetime'],
            'f_d' => ['type_name' => 'tinyint', 'type' => 'tinyint(1)'],
            'f_e' => ['type_name' => 'blob', 'type' => 'blob'],
        ]);

        $this->assertSame(['text', 'integer', 'datetime', 'boolean', 'long_text'], array_column($fields, 'type'));
        $this->assertSame([80], [$fields[0]['params']['length']]);
        $this->assertSame([false, false, false, false, false], array_column($fields, 'declared'));
        $this->assertSame('A', $fields[0]['label']);
    }

    public function test_every_legacy_type_has_a_port_type(): void
    {
        $rows = [
            self::row('T', 'text', '300'), self::row('L', 'long text'), self::row('I', 'integer'), self::row('F', 'float'),
            self::row('C', 'checkbox'), self::row('D', 'date'), self::row('Ts', 'timestamp'), self::row('Tm', 'time'),
            self::row('Cd', 'commondata', '1__CRM/Access'), self::row('Cm', 'multiselect', '__COMMON__::Contacts_Groups::value'),
            self::row('Cs', 'select', '__COMMON__::Companies_Groups'), self::row('Rel', 'select', 'company::Company Name;::'),
            self::row('Rels', 'multiselect', 'contact::Last Name;::'), self::row('Any', 'multiselect', '__RECORDSETS__'),
            self::row('Num', 'autonumber'), self::row('Fi', 'file'), self::row('Cur', 'currency'), self::row('Hid', 'hidden'), self::row('Calc', 'calculated'),
        ];
        $columns = array_map(fn (array $row): string => 'f_'.LegacyCustomFieldPlanner::fieldId($row['field']), $rows);
        $sql = ['f_hid' => ['type_name' => 'varchar', 'type' => 'varchar(32)'], 'f_calc' => ['type_name' => 'decimal', 'type' => 'decimal(10,2)']];

        $types = array_column($this->plan($columns, $rows, [], $sql), 'type', 'name');

        $this->assertSame([
            't' => 'long_text', 'l' => 'long_text', 'i' => 'integer', 'f' => 'decimal', 'c' => 'boolean', 'd' => 'date', 'ts' => 'datetime', 'tm' => 'time',
            'cd' => 'commondata', 'cm' => 'commondata', 'cs' => 'commondata', 'rel' => 'relation', 'rels' => 'relations', 'any' => 'related',
            'num' => 'autonumber', 'fi' => 'file', 'cur' => 'currency', 'hid' => 'text', 'calc' => 'decimal',
        ], $types);
    }

    public function test_a_select_over_several_recordsets_becomes_one_field_per_target(): void
    {
        $fields = $this->plan(['f_owner'], [self::row('Owner', 'select', 'contact,company::Name;::')]);

        $this->assertSame(['owner_contact', 'owner_company'], array_column($fields, 'name'));
        $this->assertSame(['contact', 'company'], array_column($fields, 'relation_tab'));
        $this->assertSame([true, true], array_column($fields, 'typed'));
        $this->assertSame(['f_owner', 'f_owner'], array_column($fields, 'column'));
    }

    public function test_a_shared_list_choice_keeps_its_list_and_multiplicity(): void
    {
        [$field] = $this->plan(['f_groups'], [self::row('Groups', 'multiselect', '__COMMON__::Contacts_Groups::value')]);

        $this->assertSame(['array' => 'Contacts_Groups', 'multiple' => true], $field['params']);
        $this->assertSame(['a', 'b'], LegacyCustomFieldPlanner::decode($field, '__a__b__'));
    }

    public function test_the_fields_that_are_added_but_not_copied_yet_say_why(): void
    {
        $fields = $this->plan(['f_scan', 'f_link'], [self::row('Scan', 'file'), self::row('Link', 'select', 'premium_warehouse_items::Name;::')]);

        $this->assertSame('file', $fields[0]['type']);
        $this->assertStringContainsString('file ledger', $fields[0]['pending']);
        $this->assertStringContainsString("'premium_warehouse_items'", $fields[1]['pending']);
        $this->assertNotNull($fields[1]['type']);
    }

    public function test_a_name_starting_with_a_digit_is_prefixed_and_names_stay_unique(): void
    {
        $fields = $this->plan(['f_2nd_phone', 'f_x_1', 'f_x__1'], [self::row('2nd phone', 'text', '30'), self::row('X 1', 'text'), self::row('X-1', 'text')]);

        $this->assertSame('f_2nd_phone', $fields[0]['name']);
        $this->assertCount(3, array_unique(array_column($fields, 'name')));
    }

    public function test_values_are_decoded_for_the_custom_column(): void
    {
        $decode = fn (string $type, mixed $raw, array $params = []) => LegacyCustomFieldPlanner::decode(['type' => $type, 'params' => $params], $raw);

        $this->assertSame('A&J', $decode('text', 'A&amp;J', ['length' => 10]));
        $this->assertSame('abc', $decode('text', 'abcdef', ['length' => 3]));
        $this->assertNull($decode('text', ''));
        $this->assertSame(12, $decode('integer', '12'));
        $this->assertSame('12.5000', $decode('decimal', '12.5', ['decimals' => 4]));
        $this->assertNull($decode('decimal', 'n/a'));
        $this->assertTrue($decode('boolean', '1'));
        $this->assertFalse($decode('boolean', null));
        $this->assertSame('2020-03-04', $decode('date', '2020-03-04'));
        $this->assertNull($decode('date', '0000-00-00'));
        $this->assertSame('10:30:00', $decode('time', '10:30:00'));
        $this->assertSame('KEY', $decode('commondata', ' KEY ', ['multiple' => false]));
    }
}
