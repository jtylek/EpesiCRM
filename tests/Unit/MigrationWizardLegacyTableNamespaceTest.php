<?php

use Epesi\Modules\MigrationWizard\Database\LegacyTableNamespace;
use PHPUnit\Framework\TestCase;

final class MigrationWizardLegacyTableNamespaceTest extends TestCase
{
    private function pdo(array $tables): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach ($tables as $table) {
            $pdo->exec('CREATE TABLE "'.$table.'" (id INTEGER PRIMARY KEY, v TEXT)');
            $pdo->exec('INSERT INTO "'.$table.'" (v) VALUES (\'row-in-'.$table.'\')');
        }

        return $pdo;
    }

    public function test_plan_maps_every_table_to_the_prefixed_name_in_a_stable_order(): void
    {
        $map = (new LegacyTableNamespace)->plan(['modules', 'variables', 'modules'], ['variables', 'modules', 'other']);
        self::assertSame(['modules' => 'legacy_modules', 'variables' => 'legacy_variables'], $map);
    }

    public function test_plan_refuses_ambiguous_or_unsafe_input(): void
    {
        $ns = new LegacyTableNamespace;
        $cases = [
            'collision' => [['modules'], ['modules', 'legacy_modules']],
            'missing table' => [['gone'], ['modules']],
            'already prefixed' => [['legacy_modules'], ['legacy_modules']],
            'unsafe name' => [['a`b'], ['a`b']],
            'too long' => [[str_repeat('a', 60)], [str_repeat('a', 60)]],
        ];
        foreach ($cases as $label => [$tables, $existing]) {
            try {
                $ns->plan($tables, $existing);
                self::fail("plan accepted: {$label}");
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
        $this->expectException(RuntimeException::class);
        $ns->plan(['x'], ['x'], 'bad-prefix');
    }

    public function test_name_at_the_identifier_limit_is_accepted(): void
    {
        $name = str_repeat('a', 64 - strlen('legacy_'));
        self::assertSame(['legacy_'.$name], array_values((new LegacyTableNamespace)->plan([$name], [$name])));
    }

    public function test_apply_renames_tables_keeping_their_rows_and_frees_the_names(): void
    {
        $pdo = $this->pdo(['modules', 'variables']);
        $ns = new LegacyTableNamespace;
        $map = $ns->plan(['modules', 'variables'], $ns->tables($pdo));
        self::assertSame(['renamed_now' => 2, 'already_renamed' => 0], $ns->apply($pdo, $map));
        self::assertEqualsCanonicalizing(['legacy_modules', 'legacy_variables'], $ns->tables($pdo));
        self::assertSame('row-in-modules', $pdo->query('SELECT v FROM legacy_modules')->fetchColumn());
        // The freed name can now be used by the new schema.
        $pdo->exec('CREATE TABLE modules (id INTEGER)');
        $state = $ns->reconcile($map, $ns->tables($pdo));
        self::assertSame(['modules'], $state['conflicts']);
        self::assertSame(['variables'], $state['renamed']);
    }

    public function test_apply_resumes_after_a_partial_run_and_is_idempotent(): void
    {
        $names = [];
        for ($i = 0; $i < 250; $i++) {
            $names[] = 't'.$i;
        }
        $pdo = $this->pdo($names);
        $ns = new LegacyTableNamespace;
        $map = $ns->plan($names, $ns->tables($pdo));

        try {
            $ns->apply($pdo, $map, function (int $done): void {
                throw new RuntimeException('interrupted after '.$done);
            });
            self::fail('expected interruption');
        } catch (RuntimeException $e) {
            self::assertSame('interrupted after 100', $e->getMessage());
        }
        $state = $ns->reconcile($map, $ns->tables($pdo));
        self::assertCount(100, $state['renamed']);
        self::assertCount(150, $state['pending']);

        $checkpoints = [];
        $result = $ns->apply($pdo, $map, function (int $done, int $total) use (&$checkpoints): void {
            $checkpoints[] = [$done, $total];
        });
        self::assertSame(['renamed_now' => 150, 'already_renamed' => 100], $result);
        self::assertSame([[100, 150], [150, 150]], $checkpoints);
        self::assertSame(['renamed_now' => 0, 'already_renamed' => 250], $ns->apply($pdo, $map));
    }

    public function test_apply_stops_on_a_table_existing_under_both_names_or_neither(): void
    {
        $ns = new LegacyTableNamespace;
        $pdo = $this->pdo(['modules', 'legacy_modules']);
        try {
            $ns->apply($pdo, ['modules' => 'legacy_modules']);
            self::fail('collision was not detected');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('both names', $e->getMessage());
        }
        self::assertSame('row-in-modules', $pdo->query('SELECT v FROM modules')->fetchColumn());

        $this->expectException(RuntimeException::class);
        $ns->apply($this->pdo(['other']), ['modules' => 'legacy_modules']);
    }

    public function test_invalid_maps_are_rejected(): void
    {
        $ns = new LegacyTableNamespace;
        $pdo = $this->pdo(['a', 'b']);
        foreach ([[], ['a' => 'a'], ['a' => 'x', 'b' => 'x'], ['a' => 'b', 'b' => 'c']] as $map) {
            try {
                $ns->apply($pdo, $map);
                self::fail('invalid map accepted');
            } catch (RuntimeException) {
                self::assertTrue(true);
            }
        }
        self::assertEqualsCanonicalizing(['a', 'b'], $ns->tables($pdo));
    }
}
