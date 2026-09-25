<?php

namespace Epesi\Modules\Notes;

use Epesi\Modules\Notes\Models\Note;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

/**
 * Registered by App\Providers\ModuleServiceProvider once this module's row in
 * the `modules` table is enabled — there is no composer package auto-discovery
 * in the loop, so provider_class in module.json is what points at this class.
 */
class NotesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Every model of a module has to be here: the core map is enforced
        // (AppServiceProvider::registerMorphAliases()), so an unmapped model
        // throws the moment anything polymorphic touches it — history rows,
        // custom-field definitions. morphMap() merges, so registering before
        // or after the core map is equally fine.
        Relation::morphMap(['note' => Note::class]);
    }

    public function boot(): void
    {
        // Keeps `php artisan migrate` aware of this module's migrations after
        // install; the installer runs them once itself with --path.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
