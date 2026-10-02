<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails;

use App\Filament\Concerns\TranslatesResourceLabels;
use App\Support\Files\FileChip;
use BackedEnum;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ComposeMail;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ListMails;
use Epesi\Modules\Mail\Filament\Resources\Mails\Pages\ViewMail;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Models\MailAttachment;
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\RecordBrowser\Filament\LinkedRecords;
use Epesi\Modules\RecordBrowser\Filament\RelationManagers\HistoryRelationManager;
use Epesi\Modules\RegionalSettings\Models\RegionalSetting;
use Filament\Actions\DeleteBulkAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use UnitEnum;

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

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

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
                    TextEntry::make('from')->inlineLabel()->placeholder(__('-'))
                        ->state(fn (Mail $record): HtmlString => static::addressLinks($record->from)),
                    TextEntry::make('to')->inlineLabel()->placeholder(__('-'))
                        ->state(fn (Mail $record): HtmlString => static::addressLinks($record->to)),
                    TextEntry::make('cc')->label('Cc')->inlineLabel()
                        ->visible(fn (Mail $record): bool => filled($record->cc))
                        ->state(fn (Mail $record): HtmlString => static::addressLinks($record->cc)),
                    TextEntry::make('date')->label('Date')->dateTime()->inlineLabel(),
                    TextEntry::make('subject')
                        ->label('Subject')
                        ->inlineLabel()
                        ->weight(FontWeight::Bold)
                        ->placeholder(__('(no subject)')),
                    LinkedRecords::badges(TextEntry::make('linked'), static::linkedRecords(...), Records::label(...))
                        ->label('Linked to')
                        ->placeholder(__('Not linked to any record'))
                        ->inlineLabel(),
                ]),
            ViewEntry::make('body')
                ->hiddenLabel()
                ->state(fn (Mail $record): string => static::messageBody($record))
                ->view('epesi-mail::body'),
            Section::make()
                ->compact()
                ->visible(fn (Mail $record): bool => $record->attachments->where('inline', false)->isNotEmpty())
                ->schema([
                    TextEntry::make('files')
                        ->label('Attachments')
                        ->state(fn (Mail $record): HtmlString => static::attachmentLinks($record))
                        ->inlineLabel(),
                ]),
            Section::make(__('Conversation'))
                ->compact()
                ->collapsible()
                ->visible(fn (Mail $record): bool => ($record->thread?->message_count ?? 0) > 1)
                ->schema([
                    TextEntry::make('thread_messages')
                        ->label('Conversation')
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
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columnManager(false)
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

    /**
     * Every address in a From/To/Cc header, linked to compose a new message
     * to it — same convention as a structured Email field's link
     * (RecordExtensions::emailLink()) and the message body's mailto: links
     * (messageBody()). Left plain when the viewer can't send.
     */
    protected static function addressLinks(?string $value): HtmlString
    {
        $escaped = e((string) $value);

        if (! ComposeAction::canSend()) {
            return new HtmlString($escaped);
        }

        // e()-escaped here, unlike messageBody(): this HtmlString is
        // rendered on the page directly, with no second escaping pass on
        // the way (there's no srcdoc attribute to land in).
        return new HtmlString((string) preg_replace_callback(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            fn (array $m): string => '<a href="'.e(ComposeAction::url(to: $m[0])).'" style="text-decoration:underline">'.$m[0].'</a>',
            $escaped,
        ));
    }

    /**
     * A mailto: link in the message body opens the CRM's own compose page
     * instead of the visitor's system mail client — legacy Epesi routed
     * these through Roundcube (CRM_RoundcubeCommon::get_mailto_link()); the
     * CRM's own ComposeAction covers it here instead, deliberately not
     * Roundcube (see AI-shared/Epesi-Laravel-Roundcube.md, "Deliberately not
     * carried over"). Left as plain mailto: when the viewer can't send,
     * same fallback as RecordExtensions::emailLink().
     */
    protected static function messageBody(Mail $record): string
    {
        $html = $record->displayHtml();

        if (! ComposeAction::canSend()) {
            return $html;
        }

        // Rewrites the whole tag, not just the href value: the compose link
        // navigates the current window (target="_top") instead of opening a
        // new one — see body.blade.php for why. Not e()-escaped: like the
        // cid: substitution above, this lands in raw (unescaped) body HTML
        // that body.blade.php's `{{ $html }}` escapes exactly once for the
        // iframe's srcdoc attribute.
        return (string) preg_replace_callback(
            '/<a\b[^>]*(\bhref\s*=\s*(["\'])mailto:([^"\']*)\2)[^>]*>/i',
            fn (array $m): string => static::composeLinkTag($m),
            $html,
        );
    }

    /**
     * @param  array<int, string>  $match  the outer regex's captures: [0]
     *                                     the whole opening <a> tag, [1] its original href attribute, [3]
     *                                     the mailto: value
     */
    protected static function composeLinkTag(array $match): string
    {
        $to = explode('?', urldecode($match[3]), 2)[0];
        $tag = str_replace($match[1], 'href="'.ComposeAction::url(to: $to).'"', $match[0]);
        $tag = preg_replace('/\s+target\s*=\s*(["\']).*?\1/i', '', $tag, 1);

        return (string) preg_replace('/^<a\b/i', '<a target="_top"', (string) $tag, 1);
    }

    protected static function attachmentLinks(Mail $record): HtmlString
    {
        // stored_file_id is nullable: an attachment archived before files
        // moved into the shared file storage, and never backfilled, has none
        // (see the 2026_09_27 migration) — skip it rather than link to a file
        // that isn't there.
        return new HtmlString($record->loadMissing('attachments.storedFile.content')->attachments
            ->where('inline', false)
            ->filter(fn (MailAttachment $a): bool => $a->storedFile !== null)
            ->map(fn (MailAttachment $a): string => FileChip::render($a->storedFile, $a->url(), $a->previewUrl(), FileChip::shareButton($a->shareUrl()))
                .' <span style="opacity:.6">('.e($a->humanSize()).')</span>')
            ->implode('<br>'));
    }

    protected static function threadList(Mail $record): HtmlString
    {
        $rows = $record->thread->mails()->orderBy('date')->get()->map(function (Mail $mail) use ($record): string {
            $line = sprintf('%s · %s · %s', e((string) RegionalSetting::display($mail->date)), e((string) $mail->from), e($mail->subject ?: '(no subject)'));

            return $mail->is($record)
                ? '<div style="font-weight:600">'.$line.'</div>'
                : '<div><a href="'.e(static::getUrl('view', ['record' => $mail])).'" style="text-decoration:underline">'.$line.'</a></div>';
        });

        return new HtmlString($rows->implode(''));
    }
}
