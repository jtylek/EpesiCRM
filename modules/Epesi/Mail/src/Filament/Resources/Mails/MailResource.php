<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails;

use App\Filament\Concerns\TranslatesResourceLabels;
use BackedEnum;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ComposeMail;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ListMails;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ViewMail;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAttachment;
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * The mail archive — CRM/Mail's `rc_mails` browser. Messages are never
 * created or edited here, only archived (upload, IMAP fetch, sending) and
 * linked, so there are no Create/Edit pages.
 */
class MailResource extends Resource
{
    use TranslatesResourceLabels;

    protected static ?string $model = Mail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'E-mails';

    protected static ?string $modelLabel = 'e-mail';

    protected static ?string $pluralModelLabel = 'e-mails';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?int $navigationSort = 60;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record instanceof Mail ? ($record->subject ?: '(no subject)') : parent::getRecordTitle($record);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()
                ->columns(1)
                ->compact()
                ->schema([
                    TextEntry::make('from')->inlineLabel()->placeholder(__('-')),
                    TextEntry::make('date')->label('Date')->dateTime('Y-m-d H:i')->inlineLabel(),
                    TextEntry::make('to')->inlineLabel()->placeholder(__('-')),
                    TextEntry::make('cc')->label('Cc')->inlineLabel()
                        ->visible(fn (Mail $record): bool => filled($record->cc)),
                    LinkedRecords::badges(TextEntry::make('linked'), static::linkedRecords(...), Records::label(...))
                        ->label('Linked to')
                        ->placeholder(__('Not linked to any record'))
                        ->inlineLabel(),
                    TextEntry::make('files')
                        ->label('Attachments')
                        ->state(fn (Mail $record): HtmlString => static::attachmentLinks($record))
                        ->visible(fn (Mail $record): bool => $record->attachments->where('inline', false)->isNotEmpty())
                        ->inlineLabel(),
                ]),
            ViewEntry::make('body')
                ->hiddenLabel()
                ->view('epesi-mail::body'),
            Section::make(__('Conversation'))
                ->compact()
                ->collapsible()
                ->visible(fn (Mail $record): bool => ($record->thread?->message_count ?? 0) > 1)
                ->schema([
                    TextEntry::make('thread_messages')
                        ->hiddenLabel()
                        ->state(fn (Mail $record): HtmlString => static::threadList($record)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['links.linkable']))
            ->defaultSort('date', 'desc')
            ->columns(MailTable::columns())
            ->filters(MailTable::filters())
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [HistoryRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMails::route('/'),
            'compose' => ComposeMail::route('/compose'),
            'view' => ViewMail::route('/{record}'),
        ];
    }

    /**
     * @return Collection<int, Model>
     */
    protected static function linkedRecords(Mail $record): Collection
    {
        return $record->loadMissing('links.linkable')->links->pluck('linkable')->filter()->values();
    }

    protected static function attachmentLinks(Mail $record): HtmlString
    {
        return new HtmlString($record->attachments->where('inline', false)
            ->map(fn (MailAttachment $a): string => sprintf(
                '<a href="%s" style="text-decoration:underline">%s</a> <span style="opacity:.6">(%s)</span>',
                e($a->url()),
                e($a->name),
                e($a->humanSize()),
            ))
            ->implode('<br>'));
    }

    protected static function threadList(Mail $record): HtmlString
    {
        $rows = $record->thread->mails()->orderBy('date')->get()->map(function (Mail $mail) use ($record): string {
            $line = sprintf('%s · %s · %s', e((string) $mail->date?->format('Y-m-d H:i')), e((string) $mail->from), e($mail->subject ?: '(no subject)'));

            return $mail->is($record)
                ? '<div style="font-weight:600">'.$line.'</div>'
                : '<div><a href="'.e(static::getUrl('view', ['record' => $mail])).'" style="text-decoration:underline">'.$line.'</a></div>';
        });

        return new HtmlString($rows->implode(''));
    }
}
