<?php

namespace Epesi\Modules\Mail\Filament\Actions;

use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Support\Records;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

/**
 * File a message under another record — rc_mails' "Related" field, the one
 * thing about an archived message that is editable.
 */
class LinkRecordAction
{
    /** Columns searched per record type when picking a record. */
    protected const SEARCH = [
        'contact' => ['first_name', 'last_name', 'email'],
        'company' => ['company_name', 'email'],
        'task' => ['title'],
        'meeting' => ['title'],
        'phone_call' => ['subject'],
    ];

    public static function make(Mail $mail): Action
    {
        return Action::make('linkRecord')
            ->label('Link to record')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->schema([
                Select::make('type')
                    ->options(collect(MailServiceProvider::$recordTypes)
                        ->mapWithKeys(fn (string $t): array => [$t => Str::headline($t)])
                        ->all())
                    ->default('contact')
                    ->required()
                    ->live(),
                Select::make('record')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(fn (Get $get, string $search): array => static::search((string) $get('type'), $search))
                    ->getOptionLabelUsing(fn (Get $get, $value): ?string => ($m = static::find((string) $get('type'), $value)) ? Records::title($m) : null),
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
     * @return array<int|string, string>
     */
    protected static function search(string $type, string $search): array
    {
        $class = Relation::getMorphedModel($type);

        if (! $class || ! in_array($type, MailServiceProvider::$recordTypes, true)) {
            return [];
        }

        $columns = self::SEARCH[$type] ?? ['id'];

        return $class::query()
            ->where(function ($q) use ($columns, $search): void {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            })
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Model $m): array => [$m->getKey() => Records::title($m)])
            ->all();
    }

    protected static function find(string $type, mixed $id): ?Model
    {
        $class = Relation::getMorphedModel($type);

        // The model's own query, visibility scopes included: you can only
        // file a message under a record you can see.
        return $class && in_array($type, MailServiceProvider::$recordTypes, true)
            ? $class::query()->find($id)
            : null;
    }
}
