<?php

namespace Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages;

use App\Enums\NoteFormat;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\AttachmentResource;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\AlertsWhenNotAttached;
use Epesi\Modules\Attachments\Filament\Resources\Attachments\Pages\Concerns\BelongsToOwnerRecord;
use Epesi\Modules\Attachments\Models\Attachment;
use Epesi\Modules\Attachments\Services\LegacyNoteCipher;
use Epesi\Modules\RecordBrowser\Filament\Pages\EditRecord;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

class EditAttachment extends EditRecord
{
    use AlertsWhenNotAttached, BelongsToOwnerRecord;

    protected static string $resource = AttachmentResource::class;

    /** The password-protected note's text once its password was entered on this page. */
    #[Locked]
    public ?string $unlockedLegacyNote = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->mountOwnerRecord($this->getRecord());

        if ($this->getRecord()->legacy_encrypted) {
            // The page caches its header actions after mount() by default.
            $this->cacheInteractsWithHeaderActions();
            $this->mountAction('unlockLegacyNote');
        }
    }

    /**
     * An encrypted note is edited as plain text after its password is verified.
     * Saving re-encrypts it by default; the editor can also turn encryption off.
     */
    protected function getHeaderActions(): array
    {
        return [
            ...parent::getHeaderActions(),
            Action::make('unlockLegacyNote')
                ->label('Decrypt note')
                ->visible(fn (): bool => $this->getRecord()->legacy_encrypted && $this->unlockedLegacyNote === null)
                ->modalHeading(__('Decrypt note to edit it'))
                ->modalDescription(fn (): string => (filled($this->getRecord()->legacy_password_hint) ? __('Password hint: :hint', ['hint' => $this->getRecord()->legacy_password_hint]).'. ' : '')
                    .__('After editing, enter the password again to keep this note encrypted.'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCancelAction(fn (Action $action): Action => $action->url($this->getResourceUrl('view')))
                ->modalSubmitActionLabel(__('Decrypt'))
                ->schema(fn (): array => [AttachmentResource::notePasswordInput($this->getRecord())])
                ->action(function (array $data): void {
                    $record = $this->getRecord();
                    abort_unless(Gate::allows('update', $record), 403);

                    $plain = app(LegacyNoteCipher::class)->decrypt((string) $record->note, (string) $data['password']);
                    abort_if($plain === null, 403);

                    $this->unlockedLegacyNote = $plain;
                    $this->form->fill([
                        ...$this->data,
                        'note' => $plain,
                        'format' => NoteFormat::Html->value,
                        'editor' => NoteFormat::Html->value,
                        'note_markdown' => '',
                        'encrypt_note' => true,
                    ]);
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Attachment $note */
        $note = $this->getRecord();

        $data['attach_to'] = AttachmentResource::attachedToState($note);
        $data['editor'] = ($note->format ?? NoteFormat::Html)->value;
        $data['note_markdown'] = $note->format === NoteFormat::Markdown ? $note->note : '';

        // Never put the ciphertext in the editor; the password unlocks the text.
        if ($note->legacy_encrypted) {
            $data['note'] = '';
            $data['note_markdown'] = '';
            $data['format'] = NoteFormat::Html->value;
            $data['editor'] = NoteFormat::Html->value;
            $data['encrypt_note'] = true;
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $rows = Arr::pull($data, 'attach_to') ?? [];

        $data = AttachmentResource::foldNoteState($data, $this->data['note_markdown'] ?? '');
        if ($record->legacy_encrypted) {
            abort_if($this->unlockedLegacyNote === null, 403);
        }
        $data = AttachmentResource::applyEncryptionState($data, $this->data ?? []);
        $record->allowEncryptedNoteWrite = (bool) ($data['legacy_encrypted'] ?? false);

        /** @var Attachment $note */
        $note = parent::handleRecordUpdate($record, $data);

        AttachmentResource::syncAttachedTo($note, $rows);

        // Taken off the record it was opened from: the page no longer belongs
        // to that record, and a note page can't be opened as part of one the
        // note isn't on (mountOwnerRecord()).
        if ($this->ownerRecord && ! $note->links()
            ->where('attachable_type', $this->ownerRecord->getMorphClass())
            ->where('attachable_id', $this->ownerRecord->getKey())
            ->exists()) {
            $this->ownerRecord = null;
        }

        return $note;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('view');
    }
}
