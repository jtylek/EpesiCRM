<?php

namespace Epesi\Modules\Mail\Filament\RelationManagers;

use App\Filament\Concerns\TranslatesRelationManagerLabels;
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

/**
 * The "E-mails" addon — CRM_Mail::addon() on contacts and companies: every
 * archived message linked to this record, newest first, with compose and
 * .eml archiving right here.
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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('links.linkable'))
            ->defaultSort('date', 'desc')
            ->columns(MailTable::columns())
            ->filters(MailTable::filters())
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

    public function isReadOnly(): bool
    {
        return false;
    }
}
