<?php

namespace Epesi\Modules\Mail\Filament\Actions;

use Epesi\Modules\Mail\MailServiceProvider;
use Epesi\Modules\Mail\Models\Mail;
use Epesi\Modules\Mail\Support\Records;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * File a message under another record — rc_mails' "Related" field, the one
 * thing about an archived message that is editable.
 */
class LinkRecordAction
{
    /** Columns searched per record type when picking a record. */
    protected const SEARCH = [
        'contact' => ['first_name', 'last_name'],
        'company' => ['company_name'],
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
                    ->options(fn (): array => collect(MailServiceProvider::recordTypes())
                        ->mapWithKeys(fn (string $t): array => [$t => LinkableRecordsets::label($t)])
                        ->sort()
                        ->all())
                    ->default(fn (): ?string => in_array('contact', MailServiceProvider::recordTypes(), true) ? 'contact' : null)
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

        if (! $class || ! in_array($type, MailServiceProvider::recordTypes(), true)) {
            return [];
        }

        $columns = static::searchColumns($type);

        return $class::query()
            ->where(function ($q) use ($columns, $search): void {
                foreach ($columns as $column) {
                    // A collection's "addresses.city" is looked up through its relation.
                    if (str_contains($column, '.')) {
                        [$relation, $inner] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn ($items) => $items->where($inner, 'like', "%{$search}%"));
                    } else {
                        $q->orWhere($column, 'like', "%{$search}%");
                    }
                }
            })
            ->limit(25)
            ->get()
            ->mapWithKeys(fn (Model $m): array => [$m->getKey() => Records::title($m)])
            ->all();
    }

    /**
     * What a record of this type is searched by: the fixed list for the CRM
     * types, otherwise the recordset's own searchable fields — so a Projects
     * recordset an administrator enables is findable without any code of
     * ours. A resource that names none falls back to the id.
     *
     * @return array<int, string>
     */
    protected static function searchColumns(string $type): array
    {
        if (isset(self::SEARCH[$type])) {
            return self::SEARCH[$type];
        }

        $resource = LinkableRecordsets::resource($type);
        $columns = $resource ? array_values(array_filter($resource::getGloballySearchableAttributes(), 'is_string')) : [];

        return $columns !== [] ? $columns : ['id'];
    }

    protected static function find(string $type, mixed $id): ?Model
    {
        $class = Relation::getMorphedModel($type);

        // The model's own query, visibility scopes included: you can only
        // file a message under a record you can see.
        return $class && in_array($type, MailServiceProvider::recordTypes(), true)
            ? $class::query()->find($id)
            : null;
    }
}
