<?php

namespace Tests\Feature;

use App\Console\Commands\PackageRelease;
use App\Services\Update\CorePackage;
use ReflectionClassConstant;
use ReflectionMethod;
use Tests\TestCase;

/**
 * What epesi:package packs from git. An installed updater refuses a release that holds a
 * top-level file or folder it doesn't list (CorePackage::FOLDERS and FILES), so a tracked
 * developer folder such as .github would make the packager stop at the end of a build.
 */
class PackageReleaseFilesTest extends TestCase
{
    public function test_ci_workflows_are_left_out_of_a_release(): void
    {
        $excluded = (new ReflectionClassConstant(PackageRelease::class, 'EXCLUDE'))->getValue();

        $this->assertContains('.github/', $excluded);
    }

    public function test_every_packed_file_sits_where_an_update_accepts_it(): void
    {
        $files = (new ReflectionMethod(PackageRelease::class, 'applicationFiles'))->invoke(app(PackageRelease::class));

        if ($files === null) {
            $this->markTestSkipped('git is not available here, so the tracked files cannot be listed.');
        }

        $refused = [];

        foreach ($files as $path) {
            $top = explode('/', $path)[0];

            if (! in_array($top, CorePackage::FOLDERS, true) && ! in_array($top, CorePackage::FILES, true)) {
                $refused[] = $path;
            }
        }

        $this->assertSame([], array_slice($refused, 0, 10), 'These tracked files would make an update refuse the release.');
    }
}
