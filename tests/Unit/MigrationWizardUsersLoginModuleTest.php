<?php

use Epesi\Modules\MigrationWizard\Import\InPlaceLegacyImport;
use PHPUnit\Framework\TestCase;

final class MigrationWizardUsersLoginModuleTest extends TestCase
{
    public function test_the_users_importer_is_listed_for_base_user_login_and_gates_nothing_there(): void
    {
        $modules = InPlaceLegacyImport::moduleImporters(['users' => 'x']);

        $this->assertSame(['users'], $modules['Base_User']);
        $this->assertSame(['users'], $modules['Base_User_Login']);
        // MODULES is the gate and cleanup list: the login module's other tables (autologin, bans, reset hashes) are not claimed.
        $this->assertSame(['Base_User'], InPlaceLegacyImport::MODULES['users']);
    }
}
