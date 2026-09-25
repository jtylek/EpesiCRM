<?php

namespace App\Support\Setup;

use App\Models\User;
use Filament\Schemas\Components\Component;

/**
 * One page a module adds to the end of setup — the port of a module's
 * post_install() / post_install_process() pair in Epesi's FirstRun (CRM/Contacts
 * asked for your company, Base/RegionalSettings for the default formats).
 *
 * Registered from the module's service provider with SetupSteps::register().
 * It runs on the request after the modules were installed, so the module's
 * own classes and providers are loaded by then.
 */
interface SetupStep
{
    public function label(): string;

    public function description(): ?string;

    /**
     * Form fields for this page. Their state is handed to handle() as-is, so
     * names only need to be unique within the step.
     *
     * @return array<int, Component>
     */
    public function schema(): array;

    /**
     * Initial values for this step's fields.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array;

    /**
     * @param  array<string, mixed>  $data  this step's field values
     */
    public function handle(array $data, User $admin): void;
}
