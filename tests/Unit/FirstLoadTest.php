<?php

namespace Tests\Unit;

use App\Support\Setup\FirstLoad;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FirstLoadTest extends TestCase
{
    private const PAGE = ['REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,*/*;q=0.8'];

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/epesi-first-load-'.uniqid();
        mkdir($this->root.'/storage/framework', 0777, true);
        mkdir($this->root.'/bootstrap', 0777, true);
        mkdir($this->root.'/views', 0777, true);
        mkdir($this->root.'/compiled', 0777, true);
        file_put_contents($this->root.'/bootstrap/release-id', "abc\n");
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);
        parent::tearDown();
    }

    private function decide(array $server): string
    {
        return (new FirstLoad($this->root, $server))->decide();
    }

    public function test_a_page_load_of_an_unprepared_release_gets_the_notice(): void
    {
        self::assertSame(FirstLoad::NOTICE, $this->decide(self::PAGE));
        self::assertStringContainsString('Preparing Epesi - please wait...', FirstLoad::noticeHtml());
    }

    public function test_a_checkout_without_a_release_id_never_sees_it(): void
    {
        unlink($this->root.'/bootstrap/release-id');

        self::assertSame(FirstLoad::PASS, $this->decide(self::PAGE));
    }

    public function test_the_marker_ends_it_and_a_new_release_brings_it_back(): void
    {
        file_put_contents($this->root.'/storage/framework/epesi-first-load', 'abc');
        self::assertSame(FirstLoad::PASS, $this->decide(self::PAGE));

        file_put_contents($this->root.'/bootstrap/release-id', 'def');
        self::assertSame(FirstLoad::NOTICE, $this->decide(self::PAGE));
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function notPageLoads(): array
    {
        return [
            'livewire navigation' => [self::PAGE + ['HTTP_X_LIVEWIRE_NAVIGATE' => '1']],
            'livewire' => [self::PAGE + ['HTTP_X_LIVEWIRE' => '1']],
            'ajax' => [self::PAGE + ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']],
            'an image or frame' => [self::PAGE + ['HTTP_SEC_FETCH_DEST' => 'image']],
            'cron or curl' => [['REQUEST_METHOD' => 'GET', 'HTTP_ACCEPT' => '*/*']],
            'an ordinary form' => [['REQUEST_METHOD' => 'POST', 'HTTP_ACCEPT' => 'text/html']],
        ];
    }

    #[DataProvider('notPageLoads')]
    public function test_anything_but_a_page_load_passes_through(array $server): void
    {
        self::assertSame(FirstLoad::PASS, $this->decide($server));
    }

    public function test_the_script_can_ask_to_compile_or_to_give_up_only_while_pending(): void
    {
        $compile = ['REQUEST_METHOD' => 'POST', 'HTTP_X_EPESI_PREPARE' => 'compile'];
        $giveUp = ['REQUEST_METHOD' => 'POST', 'HTTP_X_EPESI_PREPARE' => 'give-up'];
        self::assertTrue((new FirstLoad($this->root, $compile))->wantsCompile());
        self::assertSame(FirstLoad::GIVE_UP, $this->decide($giveUp));

        file_put_contents($this->root.'/storage/framework/epesi-first-load', 'abc');
        self::assertFalse((new FirstLoad($this->root, $compile))->wantsCompile());
        self::assertSame(FirstLoad::PASS, $this->decide($giveUp));
    }

    public function test_batches_resume_where_the_last_one_stopped(): void
    {
        $paths = [];
        foreach (['a', 'b'] as $name) {
            $paths[] = $path = $this->root.'/views/'.$name.'.blade.php';
            file_put_contents($path, '<p>{{ $'.$name.' }}</p>');
        }
        $compiler = new BladeCompiler(new Filesystem, $this->root.'/compiled');

        self::assertFalse(FirstLoad::compileBatch($compiler, $paths, 0.0), 'No time: not finished.');
        self::assertFileDoesNotExist($compiler->getCompiledPath($paths[0]));

        self::assertTrue(FirstLoad::compileBatch($compiler, $paths, 30.0));
        self::assertFileExists($compiler->getCompiledPath($paths[0]));
        self::assertFileExists($compiler->getCompiledPath($paths[1]));

        self::assertTrue(FirstLoad::compileBatch($compiler, $paths, 0.0), 'Nothing left: finished, whatever the time.');
    }

    public function test_a_source_dated_in_the_future_cannot_keep_the_batches_going_forever(): void
    {
        $path = $this->root.'/views/future.blade.php';
        file_put_contents($path, 'ok');
        touch($path, time() + 3600);
        $compiler = new BladeCompiler(new Filesystem, $this->root.'/compiled');

        self::assertTrue(FirstLoad::compileBatch($compiler, [$path], 30.0));
        self::assertTrue(FirstLoad::compileBatch($compiler, [$path], 0.0));
    }

    public function test_a_template_changed_since_it_was_compiled_is_compiled_again(): void
    {
        $path = $this->root.'/views/changed.blade.php';
        file_put_contents($path, 'old');
        $compiler = new BladeCompiler(new Filesystem, $this->root.'/compiled');
        $compiler->compile($path);
        touch($compiler->getCompiledPath($path), time() - 3600);
        touch($path, time() - 60);

        self::assertFalse(FirstLoad::compileBatch($compiler, [$path], 0.0));
        self::assertTrue(FirstLoad::compileBatch($compiler, [$path], 30.0));
        self::assertTrue(FirstLoad::compileBatch($compiler, [$path], 0.0));
    }

    public function test_a_template_that_cannot_compile_does_not_stop_the_batch(): void
    {
        $good = $this->root.'/views/good.blade.php';
        file_put_contents($good, 'ok');
        $compiler = new BladeCompiler(new Filesystem, $this->root.'/compiled');

        self::assertTrue(FirstLoad::compileBatch($compiler, [$this->root.'/views/missing.blade.php', $good], 30.0));
        self::assertFileExists($compiler->getCompiledPath($good));
    }
}
