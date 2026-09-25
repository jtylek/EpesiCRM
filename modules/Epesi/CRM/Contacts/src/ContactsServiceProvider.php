<?php

namespace Epesi\Modules\CRM\Contacts;

use App\Support\Setup\SetupSteps;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\CRM\Contacts\Policies\ContactPolicy;
use Epesi\Modules\CRM\Contacts\Setup\YourCompanyStep;
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
        // Laravel discovers policies by rewriting App\Models\X to
        // App\Policies\XPolicy; a module's model matches neither half of
        // that convention, so the binding has to be explicit.
        Gate::policy(Contact::class, ContactPolicy::class);

        SetupSteps::register('contacts-your-company', YourCompanyStep::class, 10);
    }
}
