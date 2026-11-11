<?php

namespace Tests\Feature;

use App\Services\LegacyImport\Importer;
use App\Services\LegacyImport\ImporterRegistry;
use App\Support\Modules\DeclaredLegacyImporters;
use Tests\TestCase;

/**
 * The shipped core modules declare their `import:legacy` importers in module.json, so the Migration
 * Wizard sees them on a still-legacy site (no provider loaded) and in a browser request, exactly as
 * in the CLI.
 */
class DeclaredLegacyImportersTest extends TestCase
{
    private const EXPECTED = [
        'before' => ['commondata', 'currencies', 'invoice_tax_rates'],
        'after' => ['shoutbox'],
        'last' => ['attachments', 'mail'],
    ];

    public function test_shipped_core_modules_declare_their_importers(): void
    {
        $registry = new ImporterRegistry;
        DeclaredLegacyImporters::register($registry, DeclaredLegacyImporters::shippedCoreModules());

        $this->assertEqualsCanonicalizing(self::EXPECTED['before'], array_keys($registry->before()));
        $this->assertSame(self::EXPECTED['after'], array_keys($registry->after()));
        $this->assertEqualsCanonicalizing(self::EXPECTED['last'], array_keys($registry->last()));

        foreach ([...$registry->before(), ...$registry->after(), ...$registry->last()] as $tab => $class) {
            $this->assertTrue(class_exists($class), "Importer {$class} of tab {$tab} does not autoload.");
            $this->assertTrue(method_exists($class, 'run'), "{$class} has no run().");
        }
    }

    public function test_the_application_registry_holds_them_without_any_provider_registering_them(): void
    {
        // Registered from the manifests by ModuleServiceProvider, in a plain (non-console-only) way.
        $registry = $this->app->make(ImporterRegistry::class);

        $this->assertEqualsCanonicalizing(self::EXPECTED['last'], array_keys($registry->last()));
        $this->assertContains('shoutbox', array_keys($registry->after()));
    }

    public function test_a_class_outside_the_module_namespace_is_ignored(): void
    {
        $dir = storage_path('framework/testing/declared-importers-'.uniqid());
        mkdir($dir.'/Acme/Thing', 0777, true);
        file_put_contents($dir.'/Acme/Thing/module.json', json_encode([
            'legacy_importers' => [['tab' => 'evil', 'class' => Importer::class]],
        ]));
        config(['modules.path' => $dir]);

        try {
            $registry = new ImporterRegistry;
            DeclaredLegacyImporters::register($registry, [['path' => 'Acme/Thing', 'namespace' => 'Acme\\Thing\\']]);

            $this->assertSame([], $registry->after());
        } finally {
            @unlink($dir.'/Acme/Thing/module.json');
            @rmdir($dir.'/Acme/Thing');
            @rmdir($dir.'/Acme');
            @rmdir($dir);
        }
    }
}
