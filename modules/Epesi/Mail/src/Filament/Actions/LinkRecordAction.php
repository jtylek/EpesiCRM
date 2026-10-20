<?php

namespace Epesi\Modules\Mail\Filament\Actions;

use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RecordBrowser\Recordset\RecordPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * File a message under another record — rc_mails' "Related" field, the one
 * thing about an archived message that is editable. The record is picked the
 * way every Linked to field picks one (RecordPicker): the recordset first,
 * each with its icon, then the record, your recent ones until you search.
 */
class LinkRecordAction
{
    public static function make(Mail $mail): Action
    {
        return Action::make('linkRecord')
            ->label('Link to record')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->schema([
                static::typeField()->required(),
                static::recordField()->required(),
            ])
            ->action(function (array $data) use ($mail): void {
                $record = static::find($data['type'], $data['record']);

                if ($record) {
                    $mail->linkTo($record);
                    Notification::make()->title(__('Linked to :record', ['record' => Records::label($record)]))->success()->send();
                }
            });
    }

    /**
     * The recordset to pick from: those Administration → Related modules
     * allows e-mails on, each with its icon.
     */
    public static function typeField(?string $default = 'contact'): Select
    {
        return Select::make('type')
            ->label('Recordset')
            ->options(fn (): array => RecordPicker::recordsetOptions(static::types()))
            ->allowHtml()
            ->native(false)
            ->default(fn (): ?string => in_array($default, static::types(), true) ? $default : null)
            ->afterStateUpdated(fn (Set $set) => $set('record', null))
            ->live();
    }

    /** A record of the chosen recordset: your recent ones until you search. */
    public static function recordField(): Select
    {
        return Select::make('record')
            ->searchable()
            ->native(false)
            ->options(fn (Get $get): array => static::recent((string) $get('type')))
            ->getSearchResultsUsing(fn (Get $get, string $search): array => static::search((string) $get('type'), $search))
            ->getOptionLabelUsing(fn (Get $get, $value): ?string => ($m = static::find((string) $get('type'), $value)) ? Records::title($m) : null)
            ->disabled(fn (Get $get): bool => blank($get('type')));
    }

    /**
     * The records of $type you opened last (the recordset's recent list), or
     * the newest ones when you have opened none, or it keeps no recent list.
     *
     * @return array<int|string, string>
     */
    public static function recent(string $type, int $limit = 10): array
    {
        if (! in_array($type, static::types(), true)) {
            return [];
        }

        $recent = RecordPicker::recent($type, limit: $limit);

        if ($recent !== []) {
            return $recent;
        }

        $class = Relation::getMorphedModel($type);

        // The model's own query, visibility scopes included.
        return $class
            ? $class::query()->orderByDesc((new $class)->getKeyName())->limit($limit)->get()
                ->mapWithKeys(fn (Model $m): array => [$m->getKey() => LinkableRecordsets::title($m)])
                ->all()
            : [];
    }

    /**
     * @return array<int|string, string>
     */
    protected static function search(string $type, string $search): array
    {
        return in_array($type, static::types(), true) ? RecordPicker::search($type, $search) : [];
    }

    public static function find(string $type, mixed $id): ?Model
    {
        // The model's own query, visibility scopes included: you can only
        // file a message under a record you can see.
        return in_array($type, static::types(), true) ? RecordPicker::find($type, $id) : null;
    }

    /** @return list<string> */
    protected static function types(): array
    {
        return array_values(MailServiceProvider::recordTypes());
    }
}
