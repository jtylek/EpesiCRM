<?php

namespace Tests\Feature;

use App\Support\RetryingFilesystem;
use ErrorException;
use Illuminate\Support\Facades\File;
use Illuminate\View\Compilers\Compiler;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Windows refuses to rename over a file another process has open, which is
 * what happens when parallel requests compile the same Blade view. The lock
 * is simulated here with a rename that fails a given number of times.
 */
class RetryingFilesystemTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('framework/testing/files-'.uniqid());
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  int  $failures  renames refused before one goes through (PHP_INT_MAX: never)
     */
    protected function lockedFilesystem(int $failures): RetryingFilesystem
    {
        return new class($failures) extends RetryingFilesystem
        {
            public int $calls = 0;

            protected int $pauseMicroseconds = 1_000;

            public function __construct(public int $failures) {}

            protected function renameFile(string $from, string $to): bool
            {
                return ++$this->calls > $this->failures && parent::renameFile($from, $to);
            }
        };
    }

    protected function leftoverTempFiles(): array
    {
        return array_values(array_diff(scandir($this->dir), ['.', '..', 'view.php']));
    }

    public function test_the_application_and_blade_use_it(): void
    {
        $this->assertInstanceOf(RetryingFilesystem::class, app('files'));

        $compilerFiles = (new ReflectionProperty(Compiler::class, 'files'))->getValue(app('blade.compiler'));
        $this->assertInstanceOf(RetryingFilesystem::class, $compilerFiles);
    }

    public function test_a_briefly_locked_file_is_written_once_the_lock_goes(): void
    {
        $path = $this->dir.'/view.php';
        File::put($path, 'old');

        $files = $this->lockedFilesystem(failures: 3);
        $files->replace($path, 'new');

        $this->assertSame('new', File::get($path));
        $this->assertSame(4, $files->calls);
        $this->assertSame([], $this->leftoverTempFiles());
    }

    public function test_a_file_another_request_already_wrote_the_same_way_counts_as_written(): void
    {
        $path = $this->dir.'/view.php';
        File::put($path, '<?php compiled ?>');

        $files = $this->lockedFilesystem(failures: PHP_INT_MAX);
        $files->replace($path, '<?php compiled ?>');

        $this->assertSame(1, $files->calls, 'no waiting when the content is already there');
        $this->assertSame('<?php compiled ?>', File::get($path));
        $this->assertSame([], $this->leftoverTempFiles());
    }

    public function test_a_file_that_stays_locked_with_other_content_still_fails(): void
    {
        $path = $this->dir.'/view.php';
        File::put($path, 'old');

        $files = $this->lockedFilesystem(failures: PHP_INT_MAX);

        try {
            $files->replace($path, 'new');
            $this->fail('Expected the write to fail.');
        } catch (ErrorException) {
            $this->assertSame('old', File::get($path));
            $this->assertSame(20, $files->calls);
            $this->assertSame([], $this->leftoverTempFiles(), 'the temporary file is removed');
        }
    }

    public function test_a_temporary_file_view_clear_swept_away_is_written_again(): void
    {
        $path = $this->dir.'/view.php';

        $files = new class extends RetryingFilesystem
        {
            public int $calls = 0;

            protected int $pauseMicroseconds = 1_000;

            protected function renameFile(string $from, string $to): bool
            {
                if (++$this->calls === 1) {
                    unlink($from);
                }

                return parent::renameFile($from, $to);
            }
        };

        $files->replace($path, 'fresh');

        $this->assertSame('fresh', File::get($path));
        $this->assertSame(2, $files->calls);
        $this->assertSame([], $this->leftoverTempFiles());
    }

    public function test_the_failure_says_why_the_rename_failed(): void
    {
        // A folder where the file should go: no rename can replace it.
        $path = $this->dir.'/view.php';
        File::ensureDirectoryExists($path);

        $files = new class extends RetryingFilesystem
        {
            protected int $attempts = 1;
        };

        try {
            $files->replace($path, 'fresh');
            $this->fail('Expected the write to fail.');
        } catch (ErrorException $e) {
            $this->assertStringStartsWith('rename(', $e->getMessage());
            $this->assertStringEndsNotWith(') failed', $e->getMessage(), 'PHP\'s reason, not the fallback');
            $this->assertSame([], $this->leftoverTempFiles());
        }
    }

    public function test_the_suite_compiles_views_apart_from_the_running_app(): void
    {
        $folder = fn (string $path): string => str_replace('\\', '/', $path);

        $this->assertSame(
            $folder(storage_path('framework/testing/views')),
            $folder(dirname(app('blade.compiler')->getCompiledPath('page.blade.php'))),
        );
    }

    public function test_it_writes_new_files_like_laravels(): void
    {
        $path = $this->dir.'/view.php';

        app('files')->replace($path, 'fresh');

        $this->assertSame('fresh', File::get($path));
    }
}
