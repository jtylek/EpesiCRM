<?php

namespace Epesi\Modules\Mail\Filament\Resources\MailAccounts;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Support\Demo;
use BackedEnum;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\CreateMailAccount;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\EditMailAccount;
use Epesi\Modules\Mail\Filament\Resources\MailAccounts\Pages\ListMailAccounts;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\Imap\MailboxFactory;
use Epesi\Modules\Mail\Services\SmtpTransportFactory;
use Epesi\Modules\Mail\Services\UnreadCounter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Throwable;

/**
 * "Mail accounts" — rc_accounts. Each user sees and manages only their own.
 */
class MailAccountResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = MailAccount::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?string $navigationLabel = 'Mail accounts';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 61;

    public const SECURITY = ['ssl' => 'SSL/TLS', 'tls' => 'STARTTLS', 'none' => 'None'];

    /**
     * @return array<string, string>
     */
    protected static function securityOptions(): array
    {
        return [...self::SECURITY, 'none' => __('None')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Auth::id());
    }

    /**
     * Not in demo mode: every visitor shares the demo accounts, so a mailbox
     * password typed in here would be everyone's, and "Test" and "Check now"
     * would connect the server to any host a visitor names.
     */
    public static function canAccess(): bool
    {
        return ! Demo::enabled() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('Account'))->columns(2)->compact()->schema([
                TextInput::make('name')->label('Account name')->required()->maxLength(64),
                TextInput::make('email')->email()->required()->maxLength(128),
                TextInput::make('from_name')->label('Sender name')->maxLength(255)
                    ->placeholder(fn (): string => Auth::user()?->displayName() ?? ''),
                Toggle::make('is_default')->label('Default account')->inline(false),
            ]),
            Section::make(__('Incoming mail (IMAP)'))->columns(3)->compact()->schema([
                TextInput::make('imap_host')->label('Server')->maxLength(255)->columnSpan(2),
                TextInput::make('imap_port')->label('Port')->integer()->placeholder('993 / 143'),
                Select::make('imap_security')->label('Security')->options(self::securityOptions())->default('ssl')->selectablePlaceholder(false),
                TextInput::make('imap_login')->label('Login')->maxLength(255)->placeholder(__('Same as e-mail')),
                static::password('imap_password'),
            ]),
            Section::make(__('Outgoing mail (SMTP)'))->columns(3)->compact()->schema([
                TextInput::make('smtp_host')->label('Server')->maxLength(255)->columnSpan(2),
                TextInput::make('smtp_port')->label('Port')->integer()->placeholder('465 / 587'),
                Select::make('smtp_security')->label('Security')->options(self::securityOptions())->default('tls')->selectablePlaceholder(false),
                Toggle::make('smtp_auth')->label('Authentication')->default(true)->inline(false)->live(),
                TextInput::make('smtp_login')->label('Login')->maxLength(255)
                    ->placeholder(__('Same as IMAP'))->visible(fn (Get $get): bool => (bool) $get('smtp_auth')),
                static::password('smtp_password')->placeholder(__('Same as IMAP'))
                    ->visible(fn (Get $get): bool => (bool) $get('smtp_auth')),
                Toggle::make('save_to_sent')->label('Save sent messages on the server')->default(true)->inline(false)->live()
                    ->helperText(__('Copies each message sent from the CRM into your IMAP Sent folder.')),
                TextInput::make('sent_folder')->label('Sent folder')->default('Sent')->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) $get('save_to_sent'))
                    ->required(fn (Get $get): bool => (bool) $get('save_to_sent'))
                    ->columnSpan(2),
                RichEditor::make('signature')->columnSpanFull()
                    ->toolbarButtons(['bold', 'italic', 'underline', 'link', 'bulletList', 'attachFiles']),
            ]),
            Section::make(__('Archiving'))->columns(2)->compact()->schema([
                Toggle::make('archive_on_sending')->label('Archive sent messages by default')->default(false)->inline(false),
                TextInput::make('archive_folder')->label('Archive folder')->default('CRM Archive')->maxLength(255)
                    ->helperText(__('Every message you move into this IMAP folder, from any mail client, is archived.')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->weight('medium'),
                TextColumn::make('email'),
                IconColumn::make('is_default')->label('Default')->boolean(),
                TextColumn::make('last_checked_at')->label('Last checked')->since()->placeholder(__('never'))
                    ->description(fn (MailAccount $record): ?string => $record->last_error ? 'Error: '.str($record->last_error)->limit(60) : null),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                EditAction::make()->iconButton()->tooltip(__('Edit')),
                static::testAction(),
                static::checkAction(),
                DeleteAction::make()->iconButton()->tooltip(__('Delete')),
            ]);
    }

    public static function testAction(): Action
    {
        return Action::make('test')
            ->label('Test connection')
            ->icon(Heroicon::OutlinedSignal)
            ->iconButton()
            ->tooltip(__('Test connection'))
            ->color('gray')
            ->action(function (MailAccount $record): void {
                $results = [];

                if ($record->canReceive()) {
                    try {
                        $mailbox = app(MailboxFactory::class)->make($record);
                        $count = count($mailbox->folders());
                        $mailbox->close();
                        $results[] = "IMAP: OK ({$count} folders)";
                    } catch (Throwable $e) {
                        $results[] = 'IMAP: '.$e->getMessage();
                    }
                }

                if ($record->canSend()) {
                    try {
                        $transport = app(SmtpTransportFactory::class)->forAccount($record);

                        if ($transport instanceof SmtpTransport) {
                            $transport->start();
                            $transport->stop();
                        }

                        $results[] = 'SMTP: OK';
                    } catch (Throwable $e) {
                        $results[] = 'SMTP: '.$e->getMessage();
                    }
                }

                $ok = $results !== [] && collect($results)->every(fn (string $r): bool => str_contains($r, ': OK'));

                Notification::make()
                    ->title($ok ? __('Connection works') : __('Connection problem'))
                    ->body($results === [] ? 'No IMAP or SMTP server is set up.' : implode("\n", $results))
                    ->status($ok ? 'success' : 'danger')
                    ->send();
            });
    }

    /** The INBOX's unread count, now rather than at the next `mail:check`. Archives nothing. */
    public static function checkAction(): Action
    {
        return Action::make('check')
            ->label('Check now')
            ->icon(Heroicon::OutlinedArrowPath)
            ->iconButton()
            ->tooltip(__('Check now'))
            ->color('gray')
            ->visible(fn (MailAccount $record): bool => $record->canReceive())
            ->action(function (MailAccount $record): void {
                $result = app(UnreadCounter::class)->check($record);

                if ($result['count'] === null) {
                    Notification::make()->title(__('Checking failed'))->body($result['error'])->danger()->send();

                    return;
                }

                Notification::make()->title(__(':count unread', ['count' => $result['count']]))->success()->send();
            });
    }

    /**
     * A stored password is never sent back to the browser: the field starts
     * empty and leaving it empty keeps the current one (Epesi's "leave blank
     * to keep current").
     */
    protected static function password(string $name): TextInput
    {
        return TextInput::make($name)
            ->label('Password')
            ->password()
            ->revealable()
            ->maxLength(255)
            ->formatStateUsing(fn (): ?string => null)
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->placeholder(fn (string $operation): ?string => $operation === 'edit' ? 'Leave empty to keep' : null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailAccounts::route('/'),
            'create' => CreateMailAccount::route('/create'),
            'edit' => EditMailAccount::route('/{record}/edit'),
        ];
    }
}
