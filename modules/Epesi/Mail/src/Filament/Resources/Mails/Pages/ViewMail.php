<?php

namespace Epesi\Modules\Mail\Filament\Resources\Mails\Pages;

use Epesi\Modules\Mail\Filament\Actions\ComposeAction;
use Epesi\Modules\Mail\Filament\Actions\LinkRecordAction;
use Epesi\Modules\Mail\Filament\Resources\Mails\MailResource;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\RecordBrowser\Filament\Pages\ViewRecord;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;

class ViewMail extends ViewRecord
{
    protected static string $resource = MailResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Mail $mail */
        $mail = $this->getRecord();

        return [
            ComposeAction::make(source: $mail, mode: ComposeAction::REPLY),
            ComposeAction::make(source: $mail, mode: ComposeAction::REPLY_ALL),
            ComposeAction::make(source: $mail, mode: ComposeAction::FORWARD),
            LinkRecordAction::make($mail),
            ActionGroup::make([
                DeleteAction::make(),
            ]),
        ];
    }
}
