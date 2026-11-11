<?php

namespace Tests\Unit;

use Epesi\Modules\MigrationWizard\Background\BackgroundRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MigrationWizardBackgroundRunnerTest extends TestCase
{
    #[DataProvider('disabledLists')]
    public function test_the_disable_functions_setting_is_parsed(string $ini, array $expected): void
    {
        self::assertSame($expected, BackgroundRunner::disabledFunctions($ini));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function disabledLists(): array
    {
        return [
            'empty' => ['', []],
            'spaces and blanks' => [' exec , ,proc_open,', ['exec', 'proc_open']],
            'others only' => ['system,shell_exec', ['system', 'shell_exec']],
        ];
    }

    public function test_a_function_in_disable_functions_is_not_usable(): void
    {
        self::assertSame([], BackgroundRunner::usableFunctions(['proc_open', 'exec']));
        self::assertSame(['exec'], BackgroundRunner::usableFunctions(['proc_open']));
        self::assertSame(['proc_open'], BackgroundRunner::usableFunctions(['exec']));
        self::assertSame(['proc_open', 'exec'], BackgroundRunner::usableFunctions(['system']));
    }

    public function test_the_unix_command_detaches_and_appends_to_the_log(): void
    {
        $command = BackgroundRunner::buildCommand('/usr/bin/php8.3', '/home/u/site', '/home/u/site/storage/logs/b.log', false);

        self::assertSame("cd '/home/u/site' && nohup '/usr/bin/php8.3' artisan epesi:migrate-legacy --resume >> '/home/u/site/storage/logs/b.log' 2>&1 &", $command);
    }

    public function test_the_windows_command_starts_without_waiting(): void
    {
        $command = BackgroundRunner::buildCommand('C:\\xampp\\php\\php.exe', 'C:\\site', 'C:\\site\\storage\\logs\\b.log', true);

        self::assertSame('cmd /c start "" /B "C:\\xampp\\php\\php.exe" artisan epesi:migrate-legacy --resume >> "C:\\site\\storage\\logs\\b.log" 2>&1', $command);
    }

    public function test_the_command_never_carries_a_password(): void
    {
        self::assertStringNotContainsString('password', BackgroundRunner::buildCommand('php', '/x', '/x/log', false));
        self::assertStringNotContainsString('admin', BackgroundRunner::buildCommand('php', '/x', '/x/log', true));
    }
}
