<?php

namespace Epesi\Modules\Mail;

use App\Services\LegacyImport\ImporterRegistry;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Console\FetchMailCommand;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\RelationManagers\MailAddressesRelationManager;
use Epesi\Modules\Mail\Filament\RelationManagers\MailsRelationManager;
use Epesi\Modules\Mail\LegacyImport\MailImporter;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Models\MailAccountFolder;
use Epesi\Modules\Mail\Models\MailAddress;
use Epesi\Modules\Mail\Models\MailAttachment;
use Epesi\Modules\Mail\Models\MailLink;
use Epesi\Modules\Mail\Models\MailThread;
use Epesi\Modules\Mail\Policies\MailAccountPolicy;
use Epesi\Modules\Mail\Policies\MailAddressPolicy;
use Epesi\Modules\Mail\Policies\MailPolicy;
use Epesi\Modules\Mail\Services\ContactMatcher;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Epesi\Modules\Mail\Services\SmtpTransportFactory;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Epesi\Modules\Watchdog\Watchdog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class MailServiceProvider extends ServiceProvider
{
    /**
     * Record types mail can be filed under, each with an E-mails tab — the
     * port of the `rc_related` admin table (Epesi shipped company and
     * contact; activities are here too, so a message filed under a task
     * shows on the task).
     *
     * @var array<int, string>
     */
    public static array $recordTypes = ['contact', 'company', 'task', 'meeting', 'phone_call'];

    /**
     * Record types that can have extra e-mail addresses (rc_multiple_emails).
     *
     * @var array<int, string>
     */
    public static array $addressTypes = ['contact', 'company'];

    public function register(): void
    {
        Relation::morphMap([
            'mail' => Mail::class,
            'mail_account' => MailAccount::class,
            'mail_account_folder' => MailAccountFolder::class,
            'mail_address' => MailAddress::class,
            'mail_attachment' => MailAttachment::class,
            'mail_link' => MailLink::class,
            'mail_thread' => MailThread::class,
        ]);

        $this->mergeConfigFrom(__DIR__.'/../config/epesi-mail.php', 'epesi-mail');

        $this->app->singleton(MailboxFactory::class);
        $this->app->singleton(SmtpTransportFactory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'epesi-mail');

        Gate::policy(Mail::class, MailPolicy::class);
        Gate::policy(MailAccount::class, MailAccountPolicy::class);
        Gate::policy(MailAddress::class, MailAddressPolicy::class);

        if ($this->app->runningInConsole()) {
            $this->commands([FetchMailCommand::class]);

            // `import:legacy mail` (and part of `import:legacy all`).
            $this->app->make(ImporterRegistry::class)->register('mail', MailImporter::class);
        }

        // Epesi fetched from cron.php; here it is Laravel's scheduler, which
        // likewise needs `schedule:run` in cron.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('mail:fetch')
                ->cron((string) config('epesi-mail.fetch_schedule'))
                ->withoutOverlapping()
                ->runInBackground();
        });

        $this->relinkWhenAnAddressChanges();

        $this->app->booted(function (): void {
            $this->defineRelations();

            RecordExtensions::addon(MailsRelationManager::class, static::$recordTypes);
            RecordExtensions::addon(MailAddressesRelationManager::class, static::$addressTypes);
            RecordExtensions::headerActions(
                'mail',
                fn (Model $record): array => [ComposeAction::make($record)->color('gray')],
                static::$recordTypes,
            );
            // An address clicked on a record opens the compose page for it,
            // filed with that record — else the engine's plain mailto:.
            RecordExtensions::emailLink('mail', fn (Model $record, string $email): ?string => ComposeAction::canSend()
                ? ComposeAction::url(in_array(RecordExtensions::aliasOf($record), static::$recordTypes, true) ? $record : null, to: $email)
                : null);

            if (class_exists(Watchdog::class)) {
                Watchdog::enableFor('mail');
            }
        });
    }

    protected function defineRelations(): void
    {
        foreach (array_unique([...static::$recordTypes, ...static::$addressTypes]) as $alias) {
            $class = Relation::getMorphedModel($alias);

            if (! $class) {
                continue;
            }

            if (in_array($alias, static::$recordTypes, true)) {
                $class::resolveRelationUsing('mails', fn (Model $record): MorphToMany => $record
                    ->morphToMany(Mail::class, 'linkable', 'epesi_mail_links')
                    ->withTimestamps());
            }

            if (in_array($alias, static::$addressTypes, true)) {
                $class::resolveRelationUsing('mailAddresses', fn (Model $record): MorphMany => $record
                    ->morphMany(MailAddress::class, 'addressable'));
            }
        }
    }

    /**
     * A contact or company given a new e-mail address picks up the mail
     * already archived from it — reload_mails() on save.
     */
    protected function relinkWhenAnAddressChanges(): void
    {
        foreach ([Contact::class, Company::class] as $class) {
            $class::saved(function (Model $record): void {
                if ($record->wasChanged('email') || ($record->wasRecentlyCreated && filled($record->email))) {
                    app(ContactMatcher::class)->relinkExisting($record, (string) $record->email);
                }
            });
        }
    }
}
