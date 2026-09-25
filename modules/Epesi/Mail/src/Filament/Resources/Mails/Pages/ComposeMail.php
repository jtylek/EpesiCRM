<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails\Pages;

use App\Filament\Concerns\HasResourceIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAccount;
use Epesi\Modules\Mail\Services\MailSender;
use Epesi\Modules\Mail\Support\Records;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Write and send a message from the CRM — Epesi opened Roundcube's compose
 * window for this. A page rather than a modal: a message is long-form, and
 * the address fields want the room. One page serves a new message
 * (optionally about a record, whose address it starts with) and reply,
 * reply-all and forward of an archived one; see ComposeAction for the links.
 */
class ComposeMail extends Page
{
    use HasResourceIconBreadcrumb;
    use HidesPageHeading;

    protected static string $resource = MailResource::class;

    #[Url]
    public string $mode = ComposeAction::NEW;

    /** The record the message is about, as "<morph alias>:<id>". */
    #[Url]
    public ?string $about = null;

    /** The archived message being replied to or forwarded. */
    #[Url]
    public ?string $source = null;

    /** The address clicked, in place of the record's own. */
    #[Url]
    public ?string $to = null;

    /** @var array<string, mixed> */
    public array $data = [];

    #[Locked]
    public string $returnUrl = '';

    public function getTitle(): string
    {
        return __(ComposeAction::label($this->mode));
    }

    public function mount(): void
    {
        abort_unless(ComposeAction::sendingAccounts() !== [], 403);
        abort_unless(in_array($this->mode, [ComposeAction::NEW, ComposeAction::REPLY, ComposeAction::REPLY_ALL, ComposeAction::FORWARD], true), 404);

        $previous = url()->previous();
        $this->returnUrl = parse_url($previous, PHP_URL_HOST) === request()->getHost() && ! str_contains($previous, '/compose')
            ? $previous
            : MailResource::getUrl('index');

        $this->form->fill($this->defaults($this->linkedRecord(), $this->sourceMail(), $this->mode));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Select::make('account_id')
                    ->label('From')
                    ->inlineLabel()
                    ->options(fn (): array => ComposeAction::sendingAccounts())
                    ->required()
                    ->selectablePlaceholder(false),
                $this->addresses('to')->label('To')->required(),
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    $this->addresses('cc')->label('Cc'),
                    $this->addresses('bcc')->label('Bcc'),
                ]),
                TextInput::make('subject')
                    ->inlineLabel()
                    ->required()
                    ->maxLength(500),
                RichEditor::make('body')
                    ->label('Message')
                    ->extraFieldWrapperAttributes(['class' => 'epesi-compose-body'])
                    ->required(),
                FileUpload::make('files')
                    ->label('Attachments')
                    ->multiple()
                    ->disk('local')
                    ->directory('mail-outgoing')
                    ->visibility('private')
                    ->storeFileNamesIn('file_names')
                    ->maxSize(20 * 1024),
                Toggle::make('archive')
                    ->label('Archive in CRM')
                    ->helperText(__('Keep a copy linked to the recipients\' contacts and companies.')),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('epesi-mail::compose-styles'),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(__('Send'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('success')
                ->submit('send')
                ->formId('form'),
            Action::make('cancel')
                ->label(__('Cancel'))
                ->icon(Heroicon::OutlinedXMark)
                ->color('gray')
                ->url(fn (): string => $this->returnUrl),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $account = MailAccount::query()
            ->where('user_id', Auth::id())
            ->findOrFail($data['account_id']);

        $names = $data['file_names'] ?? [];
        $files = collect($data['files'] ?? [])
            ->map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)])
            ->values()
            ->all();

        $record = $this->linkedRecord();
        $sender = app(MailSender::class);

        try {
            $archived = $sender->send(
                account: $account,
                user: Auth::user(),
                to: array_values($data['to'] ?? []),
                subject: (string) $data['subject'],
                html: (string) $data['body'],
                cc: array_values($data['cc'] ?? []),
                bcc: array_values($data['bcc'] ?? []),
                files: $files,
                inReplyTo: in_array($this->mode, [ComposeAction::REPLY, ComposeAction::REPLY_ALL], true) ? $this->sourceMail() : null,
                links: $record ? [$record] : [],
                archive: (bool) ($data['archive'] ?? false),
            );
        } catch (Throwable $e) {
            // Stay on the page, uploads and all, so the address or the
            // account can be fixed and the send retried.
            Notification::make()->title(__('The message was not sent'))->body($e->getMessage())->danger()->persistent()->send();

            return;
        }

        foreach ($files as $file) {
            Storage::disk('local')->delete($file['path']);
        }

        Notification::make()
            ->title($archived ? __('Sent and archived') : __('Sent'))
            ->body(implode("\n", $sender->warnings()) ?: null)
            ->status($sender->warnings() === [] ? 'success' : 'warning')
            ->send();

        $this->redirect($this->returnUrl);
    }

    protected function addresses(string $name): TagsInput
    {
        return TagsInput::make($name)
            ->inlineLabel()
            ->extraFieldWrapperAttributes(['class' => 'epesi-compose-address'])
            ->placeholder(__('Add address'))
            ->suggestions(fn (): array => $this->addressBook())
            ->nestedRecursiveRules(['email'])
            ->splitKeys(['Tab', ',', ' ']);
    }

    protected function linkedRecord(): ?Model
    {
        if (blank($this->about)) {
            return null;
        }

        [$type, $id] = array_pad(explode(':', $this->about, 2), 2, null);
        abort_unless(in_array($type, MailServiceProvider::$recordTypes, true), 404);

        $class = Relation::getMorphedModel($type);
        abort_unless($class, 404);

        return $class::query()->findOrFail($id);
    }

    protected function sourceMail(): ?Mail
    {
        if (blank($this->source)) {
            return null;
        }

        $mail = Mail::query()->findOrFail($this->source);
        Gate::authorize('view', $mail);

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(?Model $record, ?Mail $source, string $mode): array
    {
        $accounts = ComposeAction::sendingAccounts();
        $default = MailAccount::query()->where('user_id', Auth::id())->whereKey(array_keys($accounts))
            ->orderByDesc('is_default')->first();

        $data = [
            'account_id' => $default?->getKey(),
            'archive' => (bool) ($default?->archive_on_sending ?? true),
            'to' => filter_var($this->to, FILTER_VALIDATE_EMAIL) ? [$this->to] : Records::emailsOf($record),
            'cc' => [],
            'bcc' => [],
            'subject' => '',
            'body' => '',
        ];

        if ($source === null) {
            return $data;
        }

        $subject = (string) $source->subject;
        $quote = sprintf(
            '<p></p><p>%s</p><blockquote>%s</blockquote>',
            e(__('On :date, :sender wrote:', ['date' => (string) $source->date?->format('Y-m-d H:i'), 'sender' => (string) $source->from])),
            filled($source->body_html)
                ? strip_tags((string) $source->body_html, '<p><br><div><span><b><strong><i><em><u><ul><ol><li><a><blockquote><table><tr><td><th>')
                : nl2br(e((string) $source->body_text)),
        );

        $own = array_filter(array_map('mb_strtolower', MailAccount::query()->where('user_id', Auth::id())->pluck('email')->all()));
        $sender = $this->emails((string) $source->from);

        return match ($mode) {
            ComposeAction::FORWARD => [...$data, 'to' => [], 'subject' => $this->prefixed('Fwd', $subject), 'body' => $quote],
            ComposeAction::REPLY => [...$data,
                'to' => $source->direction === Mail::OUTGOING ? $this->emails((string) $source->to) : $sender,
                'subject' => $this->prefixed('Re', $subject),
                'body' => $quote,
            ],
            ComposeAction::REPLY_ALL => [...$data,
                'to' => array_values(array_diff(array_unique([...$sender, ...$this->emails((string) $source->to)]), $own)),
                'cc' => array_values(array_diff($this->emails((string) $source->cc), $own)),
                'subject' => $this->prefixed('Re', $subject),
                'body' => $quote,
            ],
            default => $data,
        };
    }

    /**
     * @return array<int, string>
     */
    protected function addressBook(): array
    {
        return Contact::query()->whereNotNull('email')->orderBy('last_name')->limit(500)->pluck('email')
            ->concat(Company::query()->whereNotNull('email')->limit(500)->pluck('email'))
            ->unique()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    protected function emails(string $list): array
    {
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $list, $m);

        return array_values(array_unique(array_map('mb_strtolower', $m[0])));
    }

    protected function prefixed(string $prefix, string $subject): string
    {
        return preg_match('/^'.$prefix.':/i', $subject) ? $subject : "{$prefix}: {$subject}";
    }
}
