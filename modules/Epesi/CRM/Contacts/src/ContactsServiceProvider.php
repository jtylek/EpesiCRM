<?php

namespace Epesi\Modules\CRM\Contacts;

use App\Support\Setup\SetupSteps;
use Epesi\Modules\CRM\Contacts\LegacyImport\ContactsImporter;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Contacts\Policies\ContactPolicy;
use Epesi\Modules\CRM\Contacts\Setup\YourCompanyStep;
use Epesi\Modules\MigrationWizard\Import\LegacyImporterRegistry;
use Epesi\Modules\RecordBrowser\Recordset\Clipboard;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * A core CRM module (`"core": true`): the rest of the app and the other CRM
 * modules reference Contact directly, so ModuleInstaller refuses to disable or
 * uninstall it, and config('modules.core_namespaces') registers its PSR-4
 * prefix even when the `modules` table can't be read.
 */
class ContactsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The core morph map is enforced, so an unmapped model throws the
        // moment anything polymorphic touches it. Registering the alias here
        // rather than in AppServiceProvider is what lets this model live in a
        // module without orphaning its history rows or custom fields.
        Relation::morphMap(['contact' => Contact::class]);
    }

    public function boot(): void
    {
        $this->app->make(LegacyImporterRegistry::class)->register(new ContactsImporter);

        // Laravel discovers policies by rewriting App\Models\X to
        // App\Policies\XPolicy; a module's model matches neither half of
        // that convention, so the binding has to be explicit.
        Gate::policy(Contact::class, ContactPolicy::class);

        SetupSteps::register('contacts-your-company', YourCompanyStep::class, 10);

        // "Copy to clipboard"'s default pattern, as Legacy's Contacts module
        // set it (ContactsInstall.php) — see
        // AI-shared/recordbrowser/clipboard.md for the keyword grammar
        // and what this renders to. An administrator can still override it,
        // or turn the feature off, under Administration → Recordset features.
        Clipboard::enableFor('contact', <<<'PATTERN'
            %{{first_name} {last_name}}
            %{{title}}
            {company_id}
            {addresses.address_1}
            {addresses.address_2}
            %{{addresses.city}, }%{{addresses.zone} }{addresses.postal_code}
            {addresses.country}
            %{tel. {phones.work}}
            %{e-mail: {emails}}
            PATTERN);

        // The contacts table itself is a core migration; this module's own
        // are data it owns (the Group list). The installer runs them once
        // itself with --path; this keeps `php artisan migrate` aware of them.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
