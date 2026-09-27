<?php

namespace Epesi\Modules\CRM\Companies;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Companies\Policies\CompanyPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * A core CRM module (`"core": true`): the rest of the app and the other CRM
 * modules reference Company directly, so ModuleInstaller refuses to disable or
 * uninstall it, and config('modules.core_namespaces') registers its PSR-4
 * prefix even when the `modules` table can't be read.
 */
class CompaniesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The core morph map is enforced, so an unmapped model throws the
        // moment anything polymorphic touches it. Registering the alias here
        // rather than in AppServiceProvider is what lets this model live in a
        // module without orphaning its history rows or custom fields.
        Relation::morphMap(['company' => Company::class]);
    }

    public function boot(): void
    {
        // Laravel discovers policies by rewriting App\Models\X to
        // App\Policies\XPolicy; a module's model matches neither half of
        // that convention, so the binding has to be explicit.
        Gate::policy(Company::class, CompanyPolicy::class);

        // The companies table itself is a core migration; this module's own
        // are data it owns (the Group list). The installer runs them once
        // itself with --path; this keeps `php artisan migrate` aware of them.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
