<?php

namespace App\Filament\Setup\Pages;

use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Setup\DatabaseSetup;
use App\Services\Setup\Installer;
use App\Services\Setup\InstallOptions;
use App\Services\Setup\ModulePlan;
use App\Services\Setup\Requirements;
use App\Services\Setup\RoundcubeSetup;
use App\Services\Setup\SetupException;
use App\Support\Modules\ModuleManifest;
use App\Support\Setup\SetupCode;
use App\Support\Setup\SetupState;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Throwable;

/**
 * The first-run wizard — Epesi's setup.php and modules/FirstRun in one page.
 *
 * On a fresh copy (no tables yet) it is setup.php: the one-time setup code,
 * the server check and the database connection, which it saves to .env
 * before creating the tables. public/index.php has already written .env with
 * an application key (App\Support\Setup\FirstBoot), so this page can run
 * before any database exists. `php artisan epesi:install` does the same part
 * on the command line.
 *
 * Once the tables exist it is FirstRun: setup type, administrator, mail
 * settings, confirm, then install.
 *
 * The setup code is asked for once per browser session (SetupCode), on
 * whichever of the two comes first.
 */
class InstallWizard extends SimplePage
{
    use TranslatesPageLabels;

    protected Width|string|null $maxWidth = Width::ThreeExtraLarge;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed>|null */
    public ?array $database = [];

    /** The address this page was opened at — the likely APP_URL. */
    #[Locked]
    public string $siteUrl = '';

    public function mount(): void
    {
        if (SetupState::isInstalled()) {
            $this->redirect(SetupState::finishPending() ? route('filament.setup.finish') : filament()->getPanel('main')->getUrl());

            return;
        }

        $this->siteUrl = url('/');

        if (! SetupState::databaseReady()) {
            $this->fillDatabaseForm();

            return;
        }

        $this->form->fill([
            'profile' => config('setup.default_profile'),
            'demo_data' => false,
            'mail_method' => InstallOptions::MAIL_SENDMAIL,
            'smtp_security' => 'tls',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return __('Set up epesi');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('Welcome to epesi');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return SetupState::databaseReady()
            ? __('A few questions, and your new system is ready.')
            : __('First the server and the database, then a few questions about your system.');
    }

    public function content(Schema $schema): Schema
    {
        if (! SetupState::databaseReady()) {
            return $schema->components([
                Form::make([EmbeddedSchema::make('databaseForm')])
                    ->id('databaseForm')
                    ->livewireSubmitHandler('connectDatabase'),
            ]);
        }

        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('install'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make(array_values(array_filter([
                    $this->codeStep(),
                    $this->setupTypeStep(),
                    $this->administratorStep(),
                    $this->mailStep(),
                    $this->webmailStep(),
                    $this->confirmStep(),
                ])))
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="install">{{ __(\'Install\') }}</x-filament::button>',
                    ))),
            ]);
    }

    /**
     * The database half: setup.php's pages, before any table exists.
     */
    public function databaseForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('database')
            ->components([
                Wizard::make(array_values(array_filter([
                    $this->codeStep(),
                    $this->serverStep(),
                    $this->connectionStep(),
                ])))
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" size="sm" wire:loading.attr="disabled" wire:target="connectDatabase">{{ __(\'Create the tables\') }}</x-filament::button>',
                    ))),
            ]);
    }

    /**
     * Asked once per browser session: the one-time code from the server
     * (see SetupCode), or SETUP_TOKEN when .env sets one.
     */
    protected function codeStep(): ?Step
    {
        // Installed: the page is on its way out, and the code is used up.
        if (SetupCode::verified() || SetupState::isInstalled()) {
            return null;
        }

        // Makes sure the file exists before anyone is told to look in it.
        SetupCode::current();

        return Step::make('Setup code')
            ->icon(Heroicon::OutlinedKey)
            ->schema([
                TextInput::make('code')
                    ->label('Setup code')
                    ->helperText(SetupCode::usesToken()
                        ? __('The SETUP_TOKEN value from this server\'s .env file.')
                        : new HtmlString(__('Only someone with access to this server\'s files can set it up. Open the file :file in the epesi folder and copy the code from it.', ['file' => '<code>'.e(SetupCode::displayPath()).'</code>'])))
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (! SetupCode::matches($value)) {
                            $fail(__('That is not this server\'s setup code.'));
                        }
                    }),
            ]);
    }

    protected function serverStep(): Step
    {
        return Step::make('Server check')
            ->icon(Heroicon::OutlinedServer)
            ->schema([
                Text::make(fn (): Htmlable => $this->requirementsTable(app(Requirements::class)->check(envWritable: true))),
            ])
            ->afterValidation(function (): void {
                if (! Requirements::passes(app(Requirements::class)->check(envWritable: true))) {
                    Notification::make()
                        ->title(__('This server is missing something epesi needs'))
                        ->body(__('Fix the items marked above (for a PHP extension: enable it in php.ini and restart the web server), then reload this page.'))
                        ->danger()
                        ->send();

                    throw new Halt;
                }
            });
    }

    /**
     * @param  list<array{label: string, ok: bool, status: string, required: bool}>  $rows
     */
    protected function requirementsTable(array $rows): Htmlable
    {
        $html = collect($rows)->map(function (array $row): string {
            $color = $row['ok'] ? 'var(--success-600)' : ($row['required'] ? 'var(--danger-600)' : 'var(--warning-600)');

            return '<tr><td style="padding:.15rem 1rem .15rem 0">'.e($row['label']).'</td>'
                .'<td style="color:'.$color.';font-weight:600">'.e(__($row['status'])).'</td></tr>';
        })->implode('');

        return new HtmlString('<table style="font-size:.875rem">'.$html.'</table>');
    }

    protected function connectionStep(): Step
    {
        $sqlite = fn (Get $get): bool => app(DatabaseSetup::class)->isSqlite((string) $get('connection'));
        $server = fn (Get $get): bool => ! $sqlite($get);

        return Step::make('Database')
            ->icon(Heroicon::OutlinedCircleStack)
            ->schema([
                Text::make(__('Create an empty database first (in XAMPP: phpMyAdmin → Databases), then enter its details. epesi creates its tables in it.')),
                Select::make('connection')
                    ->label('Database type')
                    ->options(Requirements::availableConnections())
                    ->selectablePlaceholder(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $set('port', match ($state) {
                            'pgsql' => '5432',
                            'mysql', 'mariadb' => '3306',
                            default => null,
                        });
                    }),
                Grid::make(3)->visible($server)->schema([
                    TextInput::make('host')->label('Database server')->required($server)->maxLength(255)->columnSpan(2),
                    TextInput::make('port')->label('Port')->integer(),
                    TextInput::make('name')->label('Database name')->required($server)->maxLength(64),
                    TextInput::make('username')->label('Database user')->required($server)->maxLength(255),
                    TextInput::make('password')->label('Database password')->password()->revealable()->maxLength(255),
                ]),
                TextInput::make('file')
                    ->label('Database file')
                    ->helperText(__('Created if it doesn\'t exist.'))
                    ->visible($sqlite)
                    ->required($sqlite),
            ]);
    }

    protected function fillDatabaseForm(): void
    {
        $available = Requirements::availableConnections();
        $current = (string) config('database.default');

        // .env.example's SQLite is a placeholder; a real install usually
        // means MySQL/MariaDB.
        $connection = $current !== 'sqlite' && isset($available[$current])
            ? $current
            : (isset($available['mysql']) ? 'mysql' : (string) array_key_first($available));

        $settings = config("database.connections.{$connection}", []);

        $this->databaseForm->fill([
            'connection' => $connection,
            'host' => $settings['host'] ?? '127.0.0.1',
            'port' => (string) ($settings['port'] ?? ''),
            'name' => $current === $connection ? ($settings['database'] ?? 'epesi') : 'epesi',
            'username' => $settings['username'] ?? 'root',
            'file' => database_path('database.sqlite'),
        ]);
    }

    /**
     * setup.php's last page: connect, save the connection to .env, create
     * the tables — then this page reloads as FirstRun.
     */
    public function connectDatabase(DatabaseSetup $database): void
    {
        $data = $this->databaseForm->getState();

        if (! $this->codeAccepted($data)) {
            return;
        }

        if (SetupState::databaseReady() || ! Requirements::passes(app(Requirements::class)->check(envWritable: true))) {
            $this->redirect(route('filament.setup.install'));

            return;
        }

        $connection = (string) $data['connection'];
        $settings = $database->settings($connection, $database->isSqlite($connection)
            ? ['database' => $data['file'] ?? '']
            : ['host' => $data['host'] ?? '', 'port' => $data['port'] ?? '', 'database' => $data['name'] ?? '', 'username' => $data['username'] ?? '', 'password' => $data['password'] ?? '']);

        try {
            $database->connect($settings);
        } catch (Throwable $e) {
            Notification::make()->title(__('Could not connect to the database'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        // .env.example's placeholder address: the one this page was opened
        // at is a better guess, and links in e-mail depend on it.
        if (in_array(rtrim((string) config('app.url'), '/'), ['', 'http://localhost'], true) && $this->siteUrl !== '') {
            $settings['APP_URL'] = $this->siteUrl;
        }

        try {
            // Saved before the tables are created, so a failure part-way
            // comes back to this page with the same details filled in.
            $database->save($settings);

            // Creating a few dozen tables can outlast a short limit.
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $database->migrate();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title(__('The database tables could not be created'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        SetupState::flush();

        // An existing epesi database: nothing left to set up.
        $this->redirect(SetupState::isInstalled() ? filament()->getPanel('main')->getUrl() : route('filament.setup.install'));
    }

    /**
     * Remembers a correct setup code for the rest of the session; refuses a
     * submission that skipped the step.
     *
     * @param  array<string, mixed>  $data
     */
    protected function codeAccepted(array $data): bool
    {
        if (SetupCode::verified()) {
            return true;
        }

        if (! SetupCode::matches($data['code'] ?? null)) {
            Notification::make()->title(__('That is not this server\'s setup code.'))->danger()->send();

            return false;
        }

        SetupCode::markVerified();

        return true;
    }

    protected function setupTypeStep(): Step
    {
        $profiles = collect(config('setup.profiles'));

        return Step::make('Setup type')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                Radio::make('profile')
                    ->label('What would you like to install?')
                    ->options($profiles->map(fn (array $p): string => __($p['label']))->all())
                    ->descriptions($profiles->map(fn (array $p): string => isset($p['description']) ? __($p['description']) : '')->all())
                    ->required()
                    ->live(),
                Text::make(__('If you are not sure, choose CRM installation. Modules can be turned on and off later under Administration → Modules.')),
                Toggle::make('demo_data')
                    ->label('Load demo data')
                    ->helperText(__('About 100 companies and contacts, 30 tasks, phone calls and meetings, a shoutbox conversation, and two demo users (manager@example.com and employee@example.com, password "password"). For trying epesi out; remove it all later under Administration → Demo data.')),
            ]);
    }

    protected function administratorStep(): Step
    {
        return Step::make('Administrator')
            ->icon(Heroicon::OutlinedUserCircle)
            ->schema([
                TextInput::make('admin_name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('admin_email')
                    ->label('E-mail')
                    ->helperText(__('You sign in with this address.'))
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->notIn(fn (Get $get): array => $get('demo_data') ? ['manager@example.com', 'employee@example.com'] : [])
                    ->validationMessages(['not_in' => __('That address belongs to a demo user; choose another or turn off demo data.')]),
                Grid::make(2)->schema([
                    TextInput::make('admin_password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::min(8))
                        ->same('admin_password_confirmation'),
                    TextInput::make('admin_password_confirmation')
                        ->label('Confirm password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ]),
            ]);
    }

    protected function mailStep(): Step
    {
        $smtp = fn (Get $get): bool => $get('mail_method') === InstallOptions::MAIL_SMTP;

        return Step::make('Mail')
            ->icon(Heroicon::OutlinedEnvelope)
            ->schema([
                Radio::make('mail_method')
                    ->label('How should epesi send its own e-mail (password resets, reminders)?')
                    ->options([
                        InstallOptions::MAIL_SENDMAIL => __('This server\'s mail system'),
                        InstallOptions::MAIL_SMTP => __('An SMTP server'),
                        InstallOptions::MAIL_LOG => __('Don\'t send e-mail yet'),
                    ])
                    ->descriptions([
                        InstallOptions::MAIL_SENDMAIL => __('On a hosted server this is usually right.'),
                        InstallOptions::MAIL_LOG => __('Messages are written to the application log instead. Change this later in .env.'),
                    ])
                    ->required()
                    ->live(),
                Grid::make(3)->visible($smtp)->schema([
                    TextInput::make('smtp_host')->label('SMTP server')->required($smtp)->maxLength(255)->columnSpan(2),
                    TextInput::make('smtp_port')->label('Port')->integer()->placeholder(__('587')),
                    Select::make('smtp_security')->label('Security')
                        ->options(['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => __('None')])
                        ->selectablePlaceholder(false),
                    TextInput::make('smtp_username')->label('Login')->maxLength(255),
                    TextInput::make('smtp_password')->label('Password')->password()->revealable()->maxLength(255),
                ]),
            ]);
    }

    /**
     * Roundcube is someone else's GPL software, downloaded from the internet,
     * so it is asked for, never assumed. Only when the module is in this copy.
     */
    protected function webmailStep(): ?Step
    {
        if (! app(RoundcubeSetup::class)->available()) {
            return null;
        }

        return Step::make('Webmail')
            ->icon(Heroicon::OutlinedInbox)
            ->schema([
                Text::make(RoundcubeSetup::notice()),
                Radio::make('roundcube')
                    ->label('Would you like to install Roundcube?')
                    ->options([
                        'yes' => __('Yes, download and install Roundcube'),
                        'no' => __('No, not now'),
                    ])
                    ->descriptions([
                        'yes' => __('This computer needs to be connected to the internet. It adds a minute or two to the installation.'),
                        'no' => __('You can add it later: open Mailbox in the menu, or Administration → Modules.'),
                    ])
                    ->required()
                    ->validationMessages(['required' => __('Please choose yes or no.')]),
            ]);
    }

    protected function confirmStep(): Step
    {
        return Step::make('Install')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->schema([
                Text::make(fn (Get $get): Htmlable => $this->summary((string) $get('profile'), $get('roundcube') === 'yes')),
            ]);
    }

    protected function summary(string $profile, bool $roundcube = false): Htmlable
    {
        $paths = config("setup.profiles.{$profile}.modules", []);

        if ($roundcube) {
            $paths[] = RoundcubeSetup::MODULE_PATH;
        }

        try {
            $modules = app(ModulePlan::class)->for($paths);
        } catch (SetupException $e) {
            return new HtmlString('<strong>'.e($e->getMessage()).'</strong>');
        }

        $list = collect($modules)
            ->map(fn (ModuleManifest $m): string => '<li>'.e($m->name).'</li>')
            ->implode('');

        return new HtmlString(
            '<p>'.e(__('Setup will now install these modules, create your administrator account and save the mail settings. It can take a minute.')).'</p>'
            .'<ul style="margin:.5rem 0 0 1.25rem;list-style:disc;columns:2">'.$list.'</ul>'
            .($roundcube ? '<p style="margin-top:.75rem">'.e(__('Then it downloads Roundcube. That can take another minute or two; please keep this page open until it has finished.')).'</p>' : ''),
        );
    }

    public function install(Installer $installer): void
    {
        $data = $this->form->getState();

        if (! $this->codeAccepted($data)) {
            return;
        }

        try {
            $admin = $installer->install(new InstallOptions(
                profile: $data['profile'],
                adminName: $data['admin_name'],
                adminEmail: $data['admin_email'],
                adminPassword: $data['admin_password'],
                mailMethod: $data['mail_method'],
                smtpHost: $data['smtp_host'] ?? null,
                smtpPort: filled($data['smtp_port'] ?? null) ? (int) $data['smtp_port'] : null,
                smtpSecurity: $data['smtp_security'] ?? 'tls',
                smtpUsername: $data['smtp_username'] ?? null,
                smtpPassword: $data['smtp_password'] ?? null,
                demoData: (bool) ($data['demo_data'] ?? false),
                roundcube: ($data['roundcube'] ?? 'no') === 'yes',
            ));
        } catch (Throwable $e) {
            // Anything from a module's migrations to a lost database
            // connection; running setup again is safe (see Installer).
            report($e);
            Notification::make()->title(__('Setup did not finish'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        Auth::login($admin);
        session()->regenerate();

        foreach ($installer->warnings() as $warning) {
            Notification::make()->title(__('One thing to finish by hand'))->body($warning)->warning()->persistent()->send();
        }

        $this->redirect(route('filament.setup.finish'));
    }
}
