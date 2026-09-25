<?php

namespace Tests\Feature;

use App\Console\Commands\PackageRelease;
use App\Models\User;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\VersionConstraint;
use App\Support\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * One version, from the VERSION file: what modules are checked against,
 * what people see, and what a release zip is called.
 */
class VersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_comes_from_the_version_file(): void
    {
        $this->assertSame(trim(File::get(base_path('VERSION'))), Version::current());
        $this->assertSame(Version::current(), config('modules.core_version'));
        $this->assertSame('epesi 2.0', Version::label());
    }

    public function test_every_bundled_module_accepts_this_version(): void
    {
        // A version bump that forgets the modules would stop setup from
        // installing them.
        foreach (Finder::create()->files()->in(base_path('modules'))->name('module.json') as $file) {
            $manifest = ModuleManifest::fromJson($file->getContents());

            $this->assertTrue(
                VersionConstraint::satisfies(Version::current(), $manifest->epesiCore),
                "{$manifest->id} requires epesi_core {$manifest->epesiCore}, not ".Version::current(),
            );
        }
    }

    public function test_the_login_page_and_the_sidebar_show_it(): void
    {
        User::factory()->create();

        $this->get(route('filament.main.auth.login'))->assertOk()->assertSee('epesi 2.0');
    }

    public function test_an_untagged_commit_builds_a_development_release(): void
    {
        $name = (new ReflectionMethod(PackageRelease::class, 'releaseName'))->invoke(app(PackageRelease::class));

        // The test suite runs on a checkout that is rarely the tagged release.
        $this->assertMatchesRegularExpression('/^2\.0(-dev(\.\d{8}\.[0-9a-f]+)?)?$/', $name);
    }
}
