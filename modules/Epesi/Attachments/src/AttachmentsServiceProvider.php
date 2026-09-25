<?php

namespace Epesi\Modules\Attachments;

use Epesi\Modules\Attachments\Filament\RelationManagers\NotesRelationManager;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Policies\AttachmentPolicy;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Puts a "Notes" tab on every record type listed in $recordTypes — the port
 * of Epesi's `utils_attachment_related` table, whose rows each wired the
 * Attachment addon onto one recordset with new_addon().
 *
 * Each of those models gets an `attachments()` relation at runtime
 * (resolveRelationUsing), so the core models carry no knowledge of this
 * module and uninstalling it leaves them untouched.
 */
class AttachmentsServiceProvider extends ServiceProvider
{
    /**
     * Morph aliases of the record types that get a Notes tab. Another module
     * adds its own with Attachments::enableFor() — see that class.
     *
     * @var array<int, string>
     */
    public static array $recordTypes = ['company', 'contact', 'task', 'meeting', 'phone_call'];

    public function register(): void
    {
        Relation::morphMap([
            'attachment' => Attachment::class,
            'attachment_link' => Models\AttachmentLink::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        Gate::policy(Attachment::class, AttachmentPolicy::class);

        // After every provider has booted, so a module enabling Notes for
        // its own record type from its own boot() is picked up regardless of
        // provider order.
        $this->app->booted(function (): void {
            foreach (static::$recordTypes as $alias) {
                static::defineRelation($alias);
            }

            RecordExtensions::addon(NotesRelationManager::class, static::$recordTypes);
        });
    }

    public static function defineRelation(string $alias): void
    {
        $class = Relation::getMorphedModel($alias);

        if ($class === null || ! is_subclass_of($class, Model::class)) {
            return;
        }

        $class::resolveRelationUsing('attachments', fn (Model $record): MorphToMany => $record
            ->morphToMany(Attachment::class, 'attachable', 'epesi_attachment_links')
            ->withTimestamps());
    }
}
