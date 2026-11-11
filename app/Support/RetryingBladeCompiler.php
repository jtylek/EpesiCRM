<?php

namespace App\Support;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Component;

/**
 * Blade::render() through RetryingFilesystem. Laravel's version stores the string in the compiled views folder with
 * `new Filesystem`, past the application's `files`, so parallel first requests on Windows failed there with
 * "Access is denied (code: 5)".
 */
class RetryingBladeCompiler extends BladeCompiler
{
    public static function render($string, $data = [], $deleteCachedView = false)
    {
        $component = new class($string) extends Component
        {
            public function __construct(protected string $template) {}

            public function render()
            {
                return $this->template;
            }

            protected function createBladeViewFromString($factory, $contents)
            {
                // Laravel's own method then finds the file and only registers the namespace.
                $directory = Container::getInstance()['config']->get('view.compiled');
                $viewFile = $directory.'/'.hash('xxh128', $contents).'.blade.php';
                if (is_dir($directory) && (! is_file($viewFile) || filesize($viewFile) === 0)) {
                    Container::getInstance()->make('files')->replace($viewFile, $contents);
                }

                return parent::createBladeViewFromString($factory, $contents);
            }
        };

        $view = Container::getInstance()->make(ViewFactory::class)->make($component->resolveView(), $data);

        return tap($view->render(), function () use ($view, $deleteCachedView): void {
            if ($deleteCachedView) {
                @unlink($view->getPath());
            }
        });
    }
}
