<?php

namespace App\Filament\Administration\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use App\Services\Setup\InstallOptions;
use App\Support\Mail\MailConfig;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Epesi's Administration → Mail server settings (Base/Mail): how epesi sends
 * its own e-mail, and a Test that sends one, so a mail server that doesn't
 * work is found out here and not by a user who never got their password reset
 * or their new account's link. The settings are the mailer's .env keys
 * (MailConfig), the ones setup wrote; this is where they change afterwards.
 *
 * Legacy's "Reply-To" isn't ported: Laravel has no such setting.
 */
class MailServer extends Page
{
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Mail Server';

    protected static ?string $title = 'Mail Server';

    protected static ?string $slug = 'mail-server';

    protected static ?int $navigationSort = 80;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(MailConfig::current());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('Mail settings'))
                ->description(__('Password resets and new accounts depend on this: without a working mail server nobody receives their link.'))
                ->schema([EmbeddedSchema::make('form')]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $smtp = fn (Get $get): bool => $get('method') === InstallOptions::MAIL_SMTP;

        return $schema
            ->statePath('data')
            ->components([
                Radio::make('method')
                    ->label('How should epesi send its own e-mail (password resets, reminders)?')
                    ->options([
                        InstallOptions::MAIL_SENDMAIL => __('This server\'s mail system'),
                        InstallOptions::MAIL_SMTP => __('An SMTP server'),
                        InstallOptions::MAIL_LOG => __('Don\'t send e-mail'),
                    ])
                    ->descriptions([
                        InstallOptions::MAIL_SENDMAIL => __('What php.ini sets up for PHP\'s own mail: its sendmail_path, or on Windows its SMTP server and port. On a hosted server this is usually right.'),
                        InstallOptions::MAIL_SMTP => __('Use it when the server\'s own mail system is missing, or its mail is marked as spam.'),
                        InstallOptions::MAIL_LOG => __('Messages are only written to the application log, so nobody receives them.'),
                    ])
                    ->required()
                    ->live(),
                Grid::make(2)->schema([
                    TextInput::make('from_address')
                        ->label('Administrator e-mail address')
                        ->helperText(__('The address epesi\'s e-mail is sent from.'))
                        ->email()
                        ->required()
                        ->maxLength(255),
                    TextInput::make('from_name')
                        ->label('Send e-mails from name')
                        ->maxLength(255),
                ]),
                Grid::make(3)->visible($smtp)->schema([
                    TextInput::make('host')->label('SMTP server')->required($smtp)->maxLength(255)->columnSpan(2),
                    TextInput::make('port')->label('Port')->integer()->minValue(1)->maxValue(65535)->placeholder('587'),
                    Select::make('security')->label('Security')
                        ->options([MailConfig::SECURITY_TLS => 'STARTTLS', MailConfig::SECURITY_SSL => 'SSL/TLS', MailConfig::SECURITY_NONE => __('None')])
                        ->selectablePlaceholder(false),
                    TextInput::make('username')->label('Login')->maxLength(255),
                    TextInput::make('password')->label('Password')->password()->revealable()->maxLength(255),
                ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->saveAction(),
            $this->testAction(),
        ];
    }

    protected function saveAction(): Action
    {
        return Action::make('save')
            ->label('Save')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->action(function (): void {
                $warning = MailConfig::write($this->envValues());

                if ($warning !== null) {
                    Notification::make()->title(__('The mail settings were not saved'))->body($warning)->warning()->persistent()->send();

                    return;
                }

                Notification::make()->title(__('Settings saved'))->success()->send();

                // The page's own request has read the old .env.
                $this->redirect(static::getUrl(), navigate: false);
            });
    }

    /**
     * Epesi's Test: an e-mail to the administrator who presses it. It uses
     * what the form says now, saved or not, so a setting is tried before it
     * is kept. A short connect timeout, because a wrong host or port should
     * fail in seconds, not leave the page waiting.
     */
    protected function testAction(): Action
    {
        return Action::make('test')
            ->label('Test')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->tooltip(fn (): string => __('E-mail will be sent to :email to test the configuration', ['email' => Auth::user()?->email]))
            ->action(function (): void {
                $values = $this->envValues();
                $email = (string) Auth::user()?->email;

                if ($values['MAIL_MAILER'] === 'log') {
                    Notification::make()
                        ->title(__('Nothing was sent'))
                        ->body(__('E-mail is set to be written to the application log only. Choose an SMTP server or this server\'s mail system to send it.'))
                        ->warning()
                        ->send();

                    return;
                }

                MailConfig::apply($values);
                config(['mail.mailers.smtp.timeout' => 10, 'mail.mailers.sendmail.timeout' => 10]);

                try {
                    Mail::send(
                        [
                            'html' => 'mail.test',
                            'raw' => __('If you are reading this, it means that your e-mail server configuration at :url is working properly.', ['url' => url('/')]),
                        ],
                        ['url' => url('/')],
                        fn ($message) => $message->to($email)->subject(__('E-mail configuration test')),
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title(__('An error has occured'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('E-mail was sent successfully'))
                    ->body(__('It went to :email.', ['email' => $email]))
                    ->success()
                    ->send();
            });
    }

    /**
     * The form's answers as the .env keys they stand for.
     *
     * @return array<string, string|int|null>
     */
    protected function envValues(): array
    {
        $data = $this->form->getState();

        return MailConfig::envValues(
            $data['method'],
            $data['host'] ?? null,
            filled($data['port'] ?? null) ? (int) $data['port'] : null,
            $data['security'] ?? MailConfig::SECURITY_TLS,
            $data['username'] ?? null,
            $data['password'] ?? null,
            $data['from_address'],
            $data['from_name'] ?? null,
        );
    }
}
