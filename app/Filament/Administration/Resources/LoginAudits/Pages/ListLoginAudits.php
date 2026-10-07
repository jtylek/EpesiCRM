<?php

namespace App\Filament\Administration\Resources\LoginAudits\Pages;

use App\Filament\Administration\Resources\LoginAudits\LoginAuditResource;
use App\Models\User;
use Epesi\Modules\RecordBrowser\Filament\Pages\ListRecords;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\View\TablesRenderHook;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;

class ListLoginAudits extends ListRecords
{
    protected static string $resource = LoginAuditResource::class;

    /** User id to show the audits of; empty for all users. */
    #[Url(as: 'user')]
    public ?string $auditUser = null;

    public function boot(): void
    {
        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_BEFORE,
            fn (): View => view('filament.administration.login-audits-user-filter', [
                'users' => $this->userOptions(),
            ]),
            scopes: static::class,
        );
    }

    public function updatedAuditUser(): void
    {
        $this->resetPage();
    }

    /** @return array<int, string> user id => User Name */
    private function userOptions(): array
    {
        return User::query()
            ->with('contact')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->displayName()])
            ->sortBy(fn (string $name): string => mb_strtolower($name))
            ->all();
    }
}
