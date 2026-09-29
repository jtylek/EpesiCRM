<?php

namespace App\Providers;

use App\Filament\Commands\FileGenerators\ResourceViewRecordPageClassGenerator;
use App\Filament\Navigation\AlphabeticalNavigationManager;
use App\Filament\Support\DemoAnalytics;
use App\Filament\Support\DemoNotice;
use App\Filament\Support\ImpersonationNotice;
use App\Listeners\FinalizeLoginAudit;
use App\Models\LoginAudit;
use App\Models\Module;
use App\Models\StoredFile;
use App\Models\StoredFileContent;
use App\Models\User;
use App\Services\Cron\CronLog;
use App\Services\LegacyImport\ImporterRegistry;
use App\Support\Demo;
use App\Support\Mail\ServerMailTransport;
use App\Support\NoIdentityAutofill;
use App\Support\RetryingFilesystem;
use App\Support\Translations\CustomTranslationLoader;
use App\Support\Version;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Commands\FileGenerators\Resources\Pages\ResourceViewRecordPageClassGenerator as BaseResourceViewRecordPageClassGenerator;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationManager;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component as SchemaComponent;
use Filament\Support\Enums\Alignment;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

use function Filament\Support\get_model_label;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Compiled Blade views are written through `files`; on Windows the
        // plain version fails when parallel requests compile the same view
        // (see RetryingFilesystem). Nothing has resolved it yet at this point.
        $this->app->singleton('files', fn (): RetryingFilesystem => new RetryingFilesystem);

        // Administration → Translations: custom translations load last.
        $this->app->extend('translation.loader', fn (Loader $loader): Loader => new CustomTranslationLoader($loader));

        $this->registerMorphAliases();

        // A singleton because modules push their import:legacy tabs into it
        // from their own boot() — a fresh instance per resolve would lose them.
        $this->app->singleton(ImporterRegistry::class);

        // Makes `php artisan make:filament-resource` scaffold View pages
        // extending our shared ViewRecord (see the RecordBrowser module)
        // instead of Filament's own — see that generator's own doc comment.
        $this->app->bind(BaseResourceViewRecordPageClassGenerator::class, ResourceViewRecordPageClassGenerator::class);

        $this->app->scoped(NavigationManager::class, AlphabeticalNavigationManager::class);
    }

    /**
     * Short, stable names for every model that can appear on the far side of a
     * polymorphic column — `activity_log.subject_type`/`causer_type` and
     * spatie/laravel-permission's `model_has_roles.model_type`/
     * `model_has_permissions.model_type`.
     *
     * Without a map those columns hold the model's FQCN, which pins a model to
     * the namespace it happened to be written in. This is not hypothetical: the
     * CRM models have since moved from App\Models into modules under
     * Epesi/CRM, and every history row, role assignment and
     * `custom_fields.model_type` value survived because they store the alias
     * rather than the class name.
     *
     * `enforceMorphMap()` (rather than `morphMap()`) makes an unmapped model
     * used polymorphically throw instead of quietly writing an FQCN again —
     * the mistake is otherwise invisible until the data is already mixed.
     * Modules add their own models the same way from their service provider;
     * both calls merge.
     *
     * Registered in register() rather than boot() so it is in place before any
     * provider that might touch a polymorphic relation while booting.
     *
     * Changing or removing an alias here is a stored-data change: existing rows
     * hold the old value, so it needs a migration rewriting them (see
     * database/migrations/..._map_morph_types_to_aliases.php).
     */
    private function registerMorphAliases(): void
    {
        // The CRM aliases (contact, company, task, meeting, phone_call) are
        // registered by their own modules' service providers, which run first —
        // enforceMorphMap() merges rather than replaces, so the enforcement
        // below still covers them.
        Relation::enforceMorphMap([
            'login_audit' => LoginAudit::class,
            'module' => Module::class,
            'stored_file' => StoredFile::class,
            'stored_file_content' => StoredFileContent::class,
            'user' => User::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->preferViewOverEditOnRecordClick();
        $this->blockIdentityAutofillOnAllTextFields();
        $this->offerNoEmptyChoiceOnRequiredSelects();
        $this->translateAllLabels();
        $this->putModalActionsOnHeadingLine();
        $this->hideZeroActiveFiltersBadge();
        $this->closeFiltersPanelOnApply();
        $this->closeColumnManagerOnApply();
        $this->compactBellNotifications();
        $this->offerRowsPerPageUpToAScreenful();
        $this->giveEveryAddonAColumnSelector();
        $this->showVersion();
        $this->lockDownDemoMode();
        $this->wrapWidePreBlocksInsteadOfOverflowing();

        Event::listen(Logout::class, FinalizeLoginAudit::class);

        // "This server's mail system" (config/mail.php's sendmail mailer).
        ServerMailTransport::register();

        // Administration → Cron: each scheduled task's last run, however cron runs.
        Event::subscribe(CronLog::class);

        // On every panel: a user logged in as can be sent to any of them.
        FilamentView::registerRenderHook(PanelsRenderHook::CONTENT_START, fn () => ImpersonationNotice::render());

        // Google Analytics on a demo that has an ID for it, once the visitor
        // accepts (DemoAnalytics). Empty everywhere else.
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn () => DemoAnalytics::head());
        FilamentView::registerRenderHook(PanelsRenderHook::FOOTER, fn () => DemoAnalytics::consent());

        // Installable as an app, in a window of its own with no address bar.
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.components.web-app-head'));
    }

    /**
     * Demo mode (App\Support\Demo): what one visitor could do to spoil the
     * demo for the next, or to use the server for something else. Each hook
     * asks Demo::enabled() when it runs, so these cost nothing on a normal
     * installation. The modules guard their own actions the same way
     * (Demo::guard()); the Administration panel and the setup wizard are
     * closed by DisabledInDemo.
     *
     * - Bulk and permanent deletes say "Unavailable in demo mode": one
     *   visitor would otherwise empty a list for everyone until the reset.
     * - No file uploads, in forms or into the rich editor: the demo would be
     *   a place to leave files for strangers to download. Livewire's own
     *   upload endpoint, which any form component could be pointed at, is
     *   capped at 1 MB on top of that.
     * - No e-mail leaves the server: Laravel's mailer drops every message
     *   (reminders, watching, password resets). Sending from a mail account
     *   is closed in the Mail module, which has no accounts in the demo.
     */
    private function lockDownDemoMode(): void
    {
        foreach ([DeleteBulkAction::class, ForceDeleteAction::class, ForceDeleteBulkAction::class] as $class) {
            $class::configureUsing(fn (Action $action): Action => Demo::guard($action), isImportant: true);
        }

        FileUpload::configureUsing(fn (FileUpload $upload): FileUpload => Demo::enabled()
            ? $upload->disabled()->helperText(__('Unavailable in demo mode'))
            : $upload, isImportant: true);
        RichEditor::configureUsing(fn (RichEditor $editor): RichEditor => Demo::enabled()
            ? $editor->fileAttachments(false)
            : $editor, isImportant: true);

        // Returning false from a MessageSending listener cancels the message.
        Event::listen(MessageSending::class, fn (): ?bool => Demo::enabled() ? false : null);

        FilamentView::registerRenderHook(PanelsRenderHook::CONTENT_AFTER, fn () => DemoNotice::render());

        if (Demo::enabled()) {
            config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:1024']]);
        }
    }

    /**
     * Global default for every Filament resource table (ours and vendor
     * ones, e.g. Shield's Roles/Permissions): clicking a row opens the
     * record read-only on its View page, not Edit — Edit is reached via the
     * explicit "Edit" action from there.
     *
     * Filament's own row-click default already prefers 'view' over 'edit',
     * but only among actions actually registered in the table's
     * ->recordActions() — a resource whose table registers EditAction but
     * not ViewAction (Shield's RoleResource does exactly this, despite
     * having a real `view` page/route) falls straight through to Edit. This
     * resolves the target page directly off the resource instead, so it
     * doesn't depend on which actions a given table happens to list.
     */
    private function preferViewOverEditOnRecordClick(): void
    {
        Table::configureUsing(function (Table $table): void {
            $livewire = $table->getLivewire();

            if (! method_exists($livewire, 'getResource')) {
                return;
            }

            $resource = $livewire::getResource();

            if (! $resource::hasPage('view')) {
                return;
            }

            $table->recordUrl(
                fn (Model $record): string => $resource::getUrl('view', ['record' => $record]),
            );
        });
    }

    /**
     * Global default for every TextInput/Textarea in every panel form:
     * blocks password-manager "Identity" autofill (1Password/Bitwarden/etc.
     * matching plain fields like "First Name"/"Address 1" against the
     * logged-in developer's own saved name/address). This was previously
     * applied field-by-field in ContactForm/CompanyForm, which still missed
     * fields as the corruption kept finding new ones. One global hook
     * covers every current and future field with no per-field opt-in.
     */
    private function blockIdentityAutofillOnAllTextFields(): void
    {
        TextInput::configureUsing(fn (TextInput $component) => $component->extraInputAttributes(NoIdentityAutofill::attributes()));
        Textarea::configureUsing(fn (Textarea $component) => $component->extraInputAttributes(NoIdentityAutofill::attributes()));
    }

    /**
     * Global default for every Select: a required one offers no empty "Select
     * an option" choice, since picking it could only fail validation. Epesi's
     * own required selects worked the same way.
     *
     * So that a dropdown never shows a value the form doesn't hold, a required
     * select left empty gets a value on load: its default or, in a plain
     * (native) dropdown, its first option, which the browser displays anyway
     * once there's no empty choice. This covers existing records too, where
     * Filament applies no default. A required custom field added later, for
     * example, leaves every earlier record empty.
     *
     * A searchable select with no default stays empty until the user picks,
     * since its first option would be an arbitrary contact or company. A
     * select's own ->selectablePlaceholder() or ->afterStateHydrated() wins.
     */
    private function offerNoEmptyChoiceOnRequiredSelects(): void
    {
        Select::configureUsing(fn (Select $select) => $select
            ->selectablePlaceholder(fn (Select $component): bool => ! $component->isRequired())
            ->afterStateHydrated(function (Select $component, mixed $state): void {
                if (filled($state) || $component->isMultiple() || ! $component->isRequired()) {
                    return;
                }

                $isNative = $component->isNative() && ! $component->isSearchable() && ! $component->isHtmlAllowed();
                $value = $component->getDefaultState() ?? ($isNative ? array_key_first($component->getEnabledOptions()) : null);

                if (filled($value)) {
                    $component->state($value);
                }
            }));
    }

    /**
     * Every Filament label — fields, infolist entries, columns, filters,
     * actions, tabs and wizard steps, including the ones Filament derives
     * from a field name — goes through __(), so translating one means adding
     * its English text to lang/<code>.json rather than wrapping every
     * ->label() call. Filament's own labels are already translated by the
     * time this sees them, and a string with no translation comes back as is.
     */
    private function translateAllLabels(): void
    {
        // Not every schema component has a label (a Grid doesn't).
        $translate = fn ($component) => method_exists($component, 'translateLabel') ? $component->translateLabel() : null;

        SchemaComponent::configureUsing($translate);

        // A table with no label of its own (an addon without $modelLabel)
        // names its records after the model class, in English. A label set
        // by the resource or relation manager still wins over this.
        Table::configureUsing(fn (Table $table) => $table
            ->modelLabel(fn (): ?string => $table->getModel() ? __(get_model_label($table->getModel())) : null)
            ->pluralModelLabel(fn (): ?string => $table->getModel() ? __(Str::plural(get_model_label($table->getModel()))) : null));
        Column::configureUsing($translate);
        BaseFilter::configureUsing($translate);
        Action::configureUsing($translate);
    }

    /**
     * "epesi 2.0" under the sidebar and below the login and setup pages, and
     * in `php artisan about`: what someone reporting a problem is asked for
     * first. From the VERSION file (App\Support\Version).
     */
    private function showVersion(): void
    {
        $label = fn (string $style): HtmlString => new HtmlString(
            '<div style="'.$style.'font-size:.75rem;opacity:.55">'.e(Version::label()).'</div>'
        );

        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn (): HtmlString => $label('padding:.75rem 1.5rem;'));
        FilamentView::registerRenderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, fn (): HtmlString => $label('text-align:center;padding:1rem 0;'));

        AboutCommand::add('epesi', fn (): array => ['Version' => Version::current()]);
    }

    /**
     * Global default for every action modal in every panel, matching the
     * Create/Edit pages' header actions (AI-shared/conventions.md): the
     * footer actions (Create/Save, Cancel…) sit on the heading's line rather
     * than under the form, and the corner close button goes — Cancel is right
     * beside it. Filament has no header slot for modal actions, so the window
     * gets a marker class and the stylesheet below re-flows its flex
     * children; the footer wraps under the heading by itself when the line
     * is too narrow for both.
     *
     * Confirmation dialogs, slide-overs and small (centred) modals keep
     * Filament's layout, and so does a modal with no footer, which would
     * otherwise lose its only close button. A closure's `Action $action` is
     * resolved by Filament to the action being rendered — for a table row,
     * that row's clone rather than the instance configured here.
     */
    private function putModalActionsOnHeadingLine(): void
    {
        $onHeadingLine = fn (Action $action): bool => ! $action->isConfirmationRequired()
            && ! $action->isModalSlideOver()
            && in_array($action->getModalAlignment(), [Alignment::Start, Alignment::Left], true)
            && filled($action->getVisibleModalFooterActions());

        Action::configureUsing(fn (Action $action) => $action
            ->modalCloseButton(fn (Action $action): bool => ! $onHeadingLine($action))
            ->extraModalWindowAttributes(fn (Action $action): array => $onHeadingLine($action) ? ['class' => 'epesi-modal-actions-in-header'] : []));

        CreateAction::configureUsing(fn (CreateAction $action) => $action
            ->modalSubmitActionLabel('Save')
            ->createAnother(false)
            ->modalSubmitAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedCheck)->color('success'))
            ->modalCancelAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedXMark)));

        EditAction::configureUsing(fn (EditAction $action) => $action
            ->modalSubmitActionLabel('Save')
            ->modalSubmitAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedCheck)->color('success'))
            ->modalCancelAction(fn (Action $action): Action => $action->icon(Heroicon::OutlinedXMark)));

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>'
                .'.fi-modal-window.epesi-modal-actions-in-header{flex-flow:row wrap!important;align-items:center!important}'
                .'.epesi-modal-actions-in-header>.fi-modal-header{flex:1 1 auto!important}'
                .'.epesi-modal-actions-in-header>.fi-modal-footer{order:1!important;width:auto!important;padding:1.5rem 1.5rem 0!important}'
                .'.epesi-modal-actions-in-header>.fi-modal-content{order:2!important;flex-basis:100%!important}'
                .'</style>'),
        );
    }

    /**
     * A table's filter button shows how many filters are active, but Filament
     * shows that count even when it is 0 (it hands the number straight to the
     * button's badge in its table view, and 0 counts as "filled"). The
     * button gets a marker class while no filter is active and the stylesheet
     * hides its badge then. The closure is evaluated on each render, so the
     * count is current.
     */
    private function hideZeroActiveFiltersBadge(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->filtersTriggerAction(fn (Action $action): Action => $action->extraAttributes(
                fn (): array => $table->getActiveFiltersCount() ? [] : ['class' => 'epesi-no-active-filters'],
                merge: true,
            )));

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>.epesi-no-active-filters .fi-icon-btn-badge-ctn{display:none}</style>'),
        );
    }

    /**
     * A table's filter panel otherwise stays open after Apply, needing a
     * click outside to dismiss it — Filament's Apply button doesn't close the
     * dropdown it sits in. The dropdown's Alpine wrapper (filamentDropdown,
     * vendor/filament/support/resources/js/components/dropdown.js) exposes a
     * close(), reachable from the button since it sits inside the same
     * x-data scope.
     *
     * ->extraAttributes(['x-on:click' => 'close()']) looks like the natural
     * way to add that, but does nothing: Action::toButtonHtml() always
     * builds the button's own x-on:click first (from getAlpineClickHandler(),
     * null here since this action has no URL/selected-records handling to
     * generate one) and only then ->merge()s the extra attributes onto it —
     * and Filament's ComponentAttributeBag::merge() keeps the receiver's own
     * attributes over the merged-in ones for any key that already exists,
     * even a null one, so the extra x-on:click is dropped silently. Setting
     * alpineClickHandler() instead wins immediately: getAlpineClickHandler()
     * returns it before any of that.
     *
     * alpineClickHandler() has its own side effect to undo, though: it always
     * calls livewireClickHandlerEnabled(false) alongside itself (it exists
     * for actions that are Alpine-only, e.g. CopyAction), which would drop
     * this button's wire:click="applyTableFilters" and leave the button
     * closing the panel without ever applying the filters. Re-enabling it
     * after restores that wire:click; both it and the Alpine close() are
     * independent listeners on the same click, so both still fire.
     */
    private function closeFiltersPanelOnApply(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->filtersApplyAction(fn (Action $action): Action => $action
                ->alpineClickHandler('close()')
                ->livewireClickHandlerEnabled()));
    }

    /**
     * The column manager dropdown has the same Apply-doesn't-close-it
     * problem as the filters panel above, but the fix differs: unlike the
     * filters Apply button (plain wire:click, no Alpine handler of its
     * own), this one is already Alpine-only — Filament gives it
     * alpineClickHandler('applyTableColumnManager') to sync the dropdown's
     * deferred column state to Livewire (column-manager.js). Overwriting
     * that handler instead of appending to it would close the panel
     * without ever applying the change, so close() is chained after it
     * rather than replacing it.
     */
    private function closeColumnManagerOnApply(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->columnManagerApplyAction(fn (Action $action): Action => $action
                ->alpineClickHandler('applyTableColumnManager(); close()')));
    }

    /**
     * A rich-text field's code block (`->prose()`, e.g. a Note or the
     * History Show modal) has no overflow rule of its own in Filament's
     * `.fi-prose` CSS — this app has no @tailwindcss/typography plugin to
     * supply one either. A `<pre>` is `white-space: pre` by default, so a
     * long line (or a whole paragraph typed into a "code block" by mistake,
     * which is prose, not code that must keep its exact line breaks) doesn't
     * wrap, and spills out of its card instead — cut off, not just wide.
     * Wrapped rather than scrolled (tried first, but a scrollbar for text
     * that reads fine wrapped is its own annoyance): every line stays
     * readable without hiding any of it sideways, and a genuinely unbreakable
     * token (a long URL) still breaks rather than overflowing again.
     * Attachments' own markdown viewer scrolls its code blocks instead
     * (modules/Epesi/Attachments/resources/views/markdown.blade.php) — real
     * fenced code there, where a line's exact breaks are worth keeping.
     */
    private function wrapWidePreBlocksInsteadOfOverflowing(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>.fi-prose pre{white-space:pre-wrap;overflow-wrap:anywhere;max-width:100%}</style>'),
        );
    }

    /**
     * The bell's list (Watchdog, Reminders) in less space: Filament pads each
     * notification like a toast and spaces its lines apart, so a screen held
     * about six. Tighter padding and lines, and a smaller icon. The text
     * wrapper is dissolved (display: contents) so title, date and body share
     * one grid. Toasts (not .fi-inline) keep Filament's layout.
     *
     * The whole notification opens its record: its "Open" action is stretched,
     * invisible, over it rather than shown as a link of its own. It stays a
     * real link, marking the notification read as before, so a middle click
     * opens a new tab and a screen reader still reads "Open"; the ✕ is kept
     * above it. Done here rather than by dropping the action, which is stored
     * with each notification, so the ones already sent work the same.
     *
     * The main panel is a SPA and keeps the bell from page to page, so its
     * slide-over would stay open over the record just opened: a plain click
     * closes it. A click that opens a new tab or window leaves it open.
     * data-navigate-once: the listener is on the document, which outlives
     * every page.
     */
    private function compactBellNotifications(): void
    {
        $n = '.fi-no-database .fi-no-notification.fi-inline';
        $linked = "{$n}:has(.fi-no-notification-actions a)";

        FilamentView::registerRenderHook(
            PanelsRenderHook::STYLES_AFTER,
            fn (): HtmlString => new HtmlString('<style>'
                ."{$n}{padding:.5rem .75rem;gap:.625rem;position:relative}"
                ."{$n} .fi-no-notification-icon{width:1.25rem;height:1.25rem}"
                ."{$n} .fi-no-notification-main{margin-top:0;gap:0}"
                ."{$n} .fi-no-notification-text{display:contents}"
                ."{$n} :is(.fi-no-notification-title,.fi-no-notification-date,.fi-no-notification-body){line-height:1.125rem}"
                ."{$n} .fi-no-notification-actions{position:absolute;inset:0;margin:0;padding:0;opacity:0}"
                ."{$n} .fi-no-notification-actions .fi-link{position:absolute;inset:0}"
                ."{$n} .fi-no-notification-close-btn{position:relative;z-index:1}"
                ."{$linked}:hover{background:color-mix(in srgb,var(--gray-500) 10%,transparent)}"
                ."{$linked}:has(a:focus-visible){outline:2px solid var(--primary-600);outline-offset:-2px}"
                .'</style>'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): HtmlString => new HtmlString(<<<'HTML'
                <script data-navigate-once>
                    document.addEventListener('click', (event) => {
                        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return
                        if (! event.target.closest('.fi-no-database .fi-no-notification-actions a')) return
                        window.dispatchEvent(new CustomEvent('close-modal', { detail: { id: 'database-notifications' } }))
                    }, true)
                </script>
                HTML),
        );
    }

    /**
     * Global default for a table's "Per page" choices, instead of Filament's
     * 5, 10, 25, 50: a full-screen list has room for about 15 rows, which
     * Filament didn't offer, so 15 is also the default. A table that sets its
     * own (the dashboard widgets, Translations) keeps them: this runs before
     * the table's own configuration. A per-page choice saved in the session
     * that is no longer offered falls back to the default.
     */
    private function offerRowsPerPageUpToAScreenful(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->paginationPageOptions([10, 15, 20, 25, 30])
            ->defaultPaginationPageOption(15));
    }

    /**
     * Filament shows a table's column selector only when one of its columns is
     * toggleable, so an addon had one or not depending on whether its author
     * happened to mark a column. Every addon column is toggleable instead,
     * shown until the user hides it. A column's own ->toggleable() call still
     * wins, so an addon can start a column hidden or keep one always shown.
     */
    private function giveEveryAddonAColumnSelector(): void
    {
        Column::configureUsing(fn (Column $column) => $column
            ->toggleable(fn ($livewire): bool => $livewire instanceof RelationManager));
    }
}
