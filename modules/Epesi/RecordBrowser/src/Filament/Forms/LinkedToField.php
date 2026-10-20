<?php

namespace Epesi\Modules\RecordBrowser\Filament\Forms;

use Closure;
use Epesi\Modules\RecordBrowser\Models\RecordLink;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Epesi\Modules\RecordBrowser\Recordset\RecordPicker;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * The Linked to field on a form: the linked records as badges, each with its
 * recordset's icon and an × to take it off, and a "Link a record" button
 * opening a popup that asks for the recordset first and then the record —
 * your recent records of that recordset before you type, when it keeps a
 * recent list. The state is a list of "alias:id" tokens (RecordLink); the
 * engine's Field saves it through HasRecordLinks::syncRecordLinks().
 *
 * The record being edited is never offered, nor are records already linked.
 */
class LinkedToField extends Field
{
    protected string $view = 'epesi-recordbrowser::forms.linked-to';

    /** @var list<string>|Closure(): list<string> */
    protected array|Closure $recordsets = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->registerActions([
            fn (LinkedToField $component): Action => $component->getLinkAction(),
        ]);
    }

    /**
     * @param  list<string>|Closure(): list<string>  $recordsets  morph aliases it offers
     */
    public function recordsets(array|Closure $recordsets): static
    {
        $this->recordsets = $recordsets;

        return $this;
    }

    /** @return list<string> */
    public function getRecordsets(): array
    {
        return array_values((array) $this->evaluate($this->recordsets));
    }

    /**
     * The badges: one per linked record this user can see, in the order
     * linked.
     *
     * @return array<string, array{title: string, type: string, icon: mixed}> token => badge
     */
    public function getLinkedItems(): array
    {
        $byAlias = [];

        foreach ((array) $this->getState() as $token) {
            if ($parsed = RecordLink::parseToken($token)) {
                $byAlias[$parsed[0]][] = $parsed[1];
            }
        }

        $found = [];

        foreach ($byAlias as $alias => $keys) {
            $class = Relation::getMorphedModel($alias);

            if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            foreach ($class::query()->whereKey($keys)->get() as $record) {
                $found[RecordLink::tokenFor($alias, $record->getKey())] = [
                    'title' => LinkableRecordsets::title($record),
                    'type' => LinkableRecordsets::label($alias),
                    'icon' => LinkableRecordsets::icon($alias),
                ];
            }
        }

        $items = [];

        foreach ((array) $this->getState() as $token) {
            if (is_string($token) && isset($found[$token])) {
                $items[$token] = $found[$token];
            }
        }

        return $items;
    }

    public function getLinkAction(): Action
    {
        return Action::make('linkRecord')
            ->label('Link a record')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->button()
            ->size(Size::Small)
            ->visible(fn (): bool => ! $this->isDisabled() && $this->getRecordsets() !== [])
            ->modalHeading('Link a record')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Link'))
            ->extraModalFooterActions(fn (Action $action): array => [
                $action->makeModalSubmitAction('linkAnother', arguments: ['another' => true])
                    ->label(__('Link & add another')),
            ])
            ->fillForm(fn (): array => ['recordset' => $this->defaultRecordset(), 'record' => null])
            ->schema([
                Select::make('recordset')
                    ->label('Recordset')
                    ->options(fn (): array => RecordPicker::recordsetOptions($this->getRecordsets()))
                    ->allowHtml()
                    ->native(false)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('record', null)),
                Select::make('record')
                    ->label('Record')
                    ->required()
                    ->searchable()
                    ->native(false)
                    ->options(fn (Get $get): array => RecordPicker::recent((string) $get('recordset'), $this->excludedKeys((string) $get('recordset'))))
                    ->getSearchResultsUsing(fn (Get $get, string $search): array => RecordPicker::search((string) $get('recordset'), $search, $this->excludedKeys((string) $get('recordset'))))
                    ->getOptionLabelUsing(fn (Get $get, mixed $value): ?string => RecordPicker::title((string) $get('recordset'), $value))
                    ->searchPrompt(fn (Get $get): string => RecordPicker::hasRecent((string) $get('recordset'))
                        ? __('Your recent records are listed. Type to search.')
                        : __('Start typing to search'))
                    ->disabled(fn (Get $get): bool => blank($get('recordset'))),
            ])
            ->action(function (array $data, array $arguments, Action $action, ?Schema $schema): void {
                $alias = (string) ($data['recordset'] ?? '');

                if (in_array($alias, $this->getRecordsets(), true)
                    && ! in_array((string) ($data['record'] ?? ''), array_map('strval', $this->excludedKeys($alias)), true)
                    && RecordPicker::find($alias, $data['record'] ?? null)) {
                    $this->state(array_values(array_unique([
                        ...array_filter((array) $this->getState(), 'is_string'),
                        RecordLink::tokenFor($alias, $data['record']),
                    ])));
                    $this->callAfterStateUpdated();
                }

                session()->put($this->rememberKey(), $alias);

                if ($arguments['another'] ?? false) {
                    $schema?->fill(['recordset' => $alias, 'record' => null]);
                    $action->halt();
                }
            });
    }

    /**
     * Keys of $alias the picker leaves out: the record being edited, when it
     * is one of that recordset, and those already linked.
     *
     * @return list<int|string>
     */
    public function excludedKeys(string $alias): array
    {
        $keys = [];
        $record = $this->getRecord();

        if ($record instanceof Model && $record->exists && $record->getMorphClass() === $alias) {
            $keys[] = $record->getKey();
        }

        foreach ((array) $this->getState() as $token) {
            if (($parsed = RecordLink::parseToken($token)) && $parsed[0] === $alias) {
                $keys[] = $parsed[1];
            }
        }

        return $keys;
    }

    /** The recordset the popup opens on: the last one picked in this field, else the first offered. */
    protected function defaultRecordset(): ?string
    {
        $offered = array_keys(RecordPicker::recordsetOptions($this->getRecordsets()));
        $last = session()->get($this->rememberKey());

        return in_array($last, $offered, true) ? $last : ($offered[0] ?? null);
    }

    protected function rememberKey(): string
    {
        $model = $this->getModel();
        $owner = is_string($model) && is_subclass_of($model, Model::class) ? (new $model)->getMorphClass() : 'record';

        return "epesi.linked_to.{$owner}.{$this->getName()}";
    }
}
