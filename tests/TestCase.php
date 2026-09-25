<?php

namespace Tests;

use App\Services\FileStorage;
use App\Services\Setup\SystemUpdate;
use App\Support\Setup\SetupState;
use App\Support\Translations\CustomTranslations;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Compiled views go to a folder of their own, not the running app's:
        // installing a module in a test runs `view:clear`, which would empty
        // the app's folder under requests compiling into it at that moment.
        $_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = dirname(__DIR__).'/storage/framework/testing/views';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // "Installed" is remembered for the rest of the process once true,
        // which is right for a real request and wrong for the next test's
        // fresh database. Same for the custom-field definitions.
        SetupState::flush();
        SystemUpdate::flush();
        CustomFieldRegistry::flush();

        // Notes' files and e-mail attachments land here; never the real one.
        Storage::fake(FileStorage::DISK);

        // Nor the administrator's own translations, which would hide a
        // missing one from TranslationsTest.
        Storage::fake(CustomTranslations::DISK);
        app('translator')->setLoaded([]);
    }
}
