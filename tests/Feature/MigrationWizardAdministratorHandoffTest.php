<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Epesi\Modules\MigrationWizard\Admin\AdminCredentials;
use Epesi\Modules\MigrationWizard\Admin\AdministratorHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MigrationWizardAdministratorHandoffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Schema::create('legacy_user_login', static function ($table): void {
            $table->unsignedInteger('id');
            $table->string('login');
            $table->unsignedTinyInteger('admin')->default(0);
            $table->unsignedTinyInteger('active')->default(1);
        });
        DB::table('legacy_user_login')->insert([
            ['id' => 1, 'login' => 'root', 'admin' => 2, 'active' => 1],
            ['id' => 2, 'login' => 'sub', 'admin' => 1, 'active' => 1],
            ['id' => 3, 'login' => 'gone', 'admin' => 2, 'active' => 0],
            ['id' => 4, 'login' => 'staff', 'admin' => 0, 'active' => 1],
        ]);
        foreach ([1, 2, 3, 4] as $id) {
            User::create(['name' => "user{$id}", 'email' => "u{$id}@example.test", 'password' => 'unusable-random-'.$id])
                ->forceFill(['legacy_id' => $id, 'active' => $id !== 3])->save();
        }
    }

    public function test_it_lists_only_active_legacy_administrators(): void
    {
        $ids = array_column((new AdministratorHandoff)->candidates(DB::getPdo()), 'id');

        self::assertSame([1, 2], $ids);
    }

    public function test_it_sets_the_new_password_and_makes_the_account_a_super_admin(): void
    {
        $result = (new AdministratorHandoff)->apply(new AdminCredentials(1, 'a-new-strong-pass'), DB::getPdo());

        $user = User::where('legacy_id', 1)->firstOrFail();
        self::assertTrue(Hash::check('a-new-strong-pass', $user->password));
        self::assertTrue($user->hasRole('super_admin'));
        self::assertTrue($user->active);
        self::assertNotNull($user->email_verified_at);
        self::assertSame(['user_id' => $user->id, 'legacy_id' => 1, 'login' => 'root'], $result);
        // Other imported users keep their unusable passwords.
        self::assertFalse(Hash::check('a-new-strong-pass', User::where('legacy_id', 2)->firstOrFail()->password));
    }

    public function test_it_sets_the_requested_email_address(): void
    {
        (new AdministratorHandoff)->apply(new AdminCredentials(1, 'a-new-strong-pass', 'owner@example.test'), DB::getPdo());

        self::assertSame('owner@example.test', User::where('legacy_id', 1)->firstOrFail()->email);
    }

    public function test_it_refuses_an_invalid_or_taken_email_and_changes_nothing(): void
    {
        foreach (['not-an-email', 'u2@example.test'] as $email) {
            try {
                (new AdministratorHandoff)->apply(new AdminCredentials(1, 'a-new-strong-pass', $email), DB::getPdo());
                self::fail("E-mail {$email} was accepted.");
            } catch (RuntimeException) {
            }
        }
        $user = User::where('legacy_id', 1)->firstOrFail();
        self::assertSame('u1@example.test', $user->email);
        self::assertFalse(Hash::check('a-new-strong-pass', $user->password));
    }

    public function test_it_refuses_inactive_non_admin_unknown_and_unimported_accounts(): void
    {
        foreach ([3, 4, 99] as $id) {
            try {
                (new AdministratorHandoff)->apply(new AdminCredentials($id, 'a-new-strong-pass'), DB::getPdo());
                self::fail("Account {$id} was accepted.");
            } catch (RuntimeException) {
            }
        }
        DB::table('legacy_user_login')->insert(['id' => 5, 'login' => 'late', 'admin' => 2, 'active' => 1]);
        $this->expectException(RuntimeException::class);
        (new AdministratorHandoff)->apply(new AdminCredentials(5, 'a-new-strong-pass'), DB::getPdo());
    }

    public function test_it_refuses_a_short_password_and_leaves_the_account_untouched(): void
    {
        $before = User::where('legacy_id', 1)->firstOrFail()->password;
        try {
            (new AdministratorHandoff)->apply(new AdminCredentials(1, 'short'), DB::getPdo());
            self::fail('A short password was accepted.');
        } catch (RuntimeException) {
        }
        self::assertSame($before, User::where('legacy_id', 1)->firstOrFail()->password);
    }

    public function test_credentials_never_reveal_or_serialise_the_password(): void
    {
        $credentials = new AdminCredentials(1, 'top-secret-value');

        self::assertStringNotContainsString('top-secret-value', print_r($credentials, true));
        self::assertStringNotContainsString('top-secret-value', var_export(get_object_vars($credentials), true));
        $this->expectException(LogicException::class);
        serialize($credentials);
    }
}
