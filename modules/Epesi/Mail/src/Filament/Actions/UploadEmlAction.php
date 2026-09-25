<?php

namespace Epesi\Modules\Mail\Filament\Actions;

use Epesi\Modules\Mail\Services\MailArchiver;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Archive messages saved from any mail client as .eml files — the manual
 * "Archive" button Epesi had inside Roundcube, for people who don't use the
 * IMAP archive folder. On a record's E-mails tab the messages are also linked
 * to that record, matched or not.
 */
class UploadEmlAction
{
    public static function make(?Model $record = null): Action
    {
        return Action::make('uploadEml')
            ->label('Archive .eml')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->modalHeading(__('Archive e-mail files'))
            ->modalDescription(__('Drag messages out of your mail client (or save them as .eml) and drop them here.'))
            ->modalSubmitActionLabel(__('Archive'))
            ->schema([
                FileUpload::make('files')
                    ->label('Messages')
                    ->multiple()
                    ->required()
                    ->disk('local')
                    ->directory('mail-upload')
                    ->visibility('private')
                    // By extension, not acceptedFileTypes(): that checks the MIME type
                    // PHP sniffs from the content, and a message whose headers don't
                    // start with Received:/Return-Path:/From comes out as text/html
                    // or text/plain. Anything else that isn't e-mail fails in the archiver.
                    ->rule('extensions:eml')
                    ->maxSize(50 * 1024),
            ])
            ->action(function (array $data) use ($record): void {
                $archiver = app(MailArchiver::class);
                $disk = Storage::disk('local');
                $archived = 0;
                $failed = 0;

                foreach ($data['files'] ?? [] as $path) {
                    try {
                        $mail = $archiver->archive($disk->get($path), Auth::user(), links: $record ? [$record] : []);
                        $archived += $mail ? 1 : 0;
                    } catch (Throwable) {
                        $failed++;
                    } finally {
                        $disk->delete($path);
                    }
                }

                Notification::make()
                    ->title(trans_choice('{0} No messages archived|{1} :count message archived|[2,*] :count messages archived', $archived, ['count' => $archived]))
                    ->body($failed ? trans_choice('{1} :count file could not be read as e-mail.|[2,*] :count files could not be read as e-mail.', $failed, ['count' => $failed]) : null)
                    ->status($failed ? 'warning' : 'success')
                    ->send();
            });
    }
}
