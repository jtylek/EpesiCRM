<?php

namespace Epesi\Modules\Mail\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Actions\UploadEmlAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailTable;
use Epesi\Modules\Mail\Models\Mail;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The "E-mails" addon — CRM_Mail::addon() on contacts and companies: every
 * archived message linked to this record, newest first, with compose and
 * .eml archiving right here.
 *
 * On a contact it lists only the mail addressed to one of the contact's
 * addresses (To or Cc; Bcc is never stored on a message), not everything
 * linked to the contact by being its sender or by archiving: see
 * addressedMailIds(). A company keeps every linked message.
 */
class MailsRelationManager extends RelationManager
{
    use TranslatesRelationManagerLabels;

    protected static string $relationship = 'mails';

    protected static ?string $title = 'E-mails';

    protected static ?string $modelLabel = 'e-mail';

    protected static string|\BackedEnum|null $icon = Heroicon::OutlinedEnvelope;

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('subject')
            ->modifyQueryUsing(function (Builder $query): Builder {
                $ids = static::addressedMailIds($this->getOwnerRecord());

                return ($ids === null ? $query : $query->whereIn($query->qualifyColumn('id'), $ids))->with('links.linkable');
            })
            ->defaultSort('date', 'desc')
            ->columns(MailTable::columns())
            ->filters([])
            ->columnManager(false)
            ->recordUrl(fn (Mail $record): string => MailResource::getUrl('view', ['record' => $record]))
            ->headerActions([
                ComposeAction::make($this->getOwnerRecord()),
                UploadEmlAction::make($this->getOwnerRecord()),
            ])
            ->recordActionsPosition(RecordActionsPosition::AfterColumns)
            ->recordActions([
                Action::make('unlink')
                    ->label('Unlink')
                    ->icon(Heroicon::OutlinedLinkSlash)
                    ->iconButton()
                    ->tooltip(__('Remove from this record'))
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(fn (Mail $record) => $record->links()
                        ->where('linkable_type', $this->getOwnerRecord()->getMorphClass())
                        ->where('linkable_id', $this->getOwnerRecord()->getKey())
                        ->delete()),
            ]);
    }

    /**
     * The ids of the owner's linked messages addressed to it, or null for no
     * restriction (anything but a contact). An address counts only as a
     * whole one, so "ann@x.com" never matches "joann@x.com".
     *
     * @return list<int>|null
     */
    public static function addressedMailIds(Model $owner): ?array
    {
        if (! $owner instanceof Contact) {
            return null;
        }

        $own = $owner->collection('emails')->pluck('value')
            ->map(fn ($value): string => mb_strtolower(trim((string) $value)))->filter()->unique()->values()->all();

        if ($own === []) {
            return [];
        }

        return $owner->mails()
            ->where(function (Builder $query) use ($own): void {
                foreach ($own as $address) {
                    $query->orWhere('to', 'like', "%{$address}%")->orWhere('cc', 'like', "%{$address}%");
                }
            })
            ->get(['epesi_mails.id', 'epesi_mails.to', 'epesi_mails.cc'])
            ->filter(function (Mail $mail) use ($own): bool {
                preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', implode(',', [$mail->to, $mail->cc]), $m);

                return array_intersect($own, array_map('mb_strtolower', $m[0])) !== [];
            })
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $ids = static::addressedMailIds($ownerRecord);

        return $ids === null ? (string) $ownerRecord->mails()->count() : (string) count($ids);
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
