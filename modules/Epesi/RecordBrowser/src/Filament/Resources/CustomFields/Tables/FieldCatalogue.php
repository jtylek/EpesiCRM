<?php

namespace Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Tables;

use App\Support\Demo;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\CustomFieldResource;
use Epesi\Modules\RecordBrowser\Filament\Resources\CustomFields\Schemas\ModuleFieldForm;
use Epesi\Modules\RecordBrowser\Models\CustomField;
use Epesi\Modules\RecordBrowser\Recordset\FieldOverrides;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FieldCatalogue
{
    public static function records(): Collection
    {
        $registry = app(FieldOverrides::class);
        $records = [];
        $recordsets = $registry->recordsets();
        $positions = [];
        foreach ($recordsets as $alias => $recordset) {
            $positions[$alias] = count($recordset['fields']);
            foreach ($recordset['fields'] as $position => $field) {
                $properties = $registry->properties($alias, $field->name);
                $effective = $properties === [] ? $field : $registry->resolve($recordset['model'], [$field])[0];
                $records["module:{$alias}:{$field->name}"] = [
                    'model_type' => $alias, 'recordset' => __($recordset['label']),
                    'name' => $field->name, 'label' => __($effective->getLabel()),
                    'type' => $field->type->label(), 'origin' => 'Module', 'custom_id' => null,
                    'required' => $effective->isRequired(), 'active' => true,
                    'customized' => $properties !== [], 'position' => $properties['position'] ?? ($position * 10),
                ];
            }
        }
        $labels = CustomFieldRegistry::participatingModels();
        foreach (CustomField::query()->orderByDesc('active')->orderBy('position')->orderBy('id')->get() as $field) {
            $position = $positions[$field->model_type] ?? 0;
            $positions[$field->model_type] = $position + 1;
            $records["custom:{$field->id}"] = [
                'model_type' => $field->model_type, 'recordset' => __($recordsets[$field->model_type]['label'] ?? $labels[$field->model_type] ?? $field->model_type),
                'name' => $field->name, 'label' => __($field->label), 'type' => $field->type->label(),
                'origin' => 'Custom', 'custom_id' => $field->id, 'required' => $field->required,
                'active' => $field->active, 'customized' => false,
                'position' => $registry->properties($field->model_type, $field->column)['position'] ?? ($position * 10),
            ];
        }

        return collect($records);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->description(__('Select one recordset and clear search and Origin to reorder fields.'))
            ->reorderable('position', fn ($livewire): bool => $livewire->canReorderFields())
            ->reorderRecordsTriggerAction(fn (Action $action, bool $isReordering): Action => $action
                ->label($isReordering ? __('Done reordering') : __('Reorder fields'))
                ->iconButton(false)->button())
            ->records(function (?string $search, ?array $filters, ?string $sortColumn, ?string $sortDirection, int $page, int|string $recordsPerPage, $livewire): LengthAwarePaginator {
                $reordering = $livewire->isTableReordering();
                $records = static::records()
                    ->filter(fn (array $record): bool => (! filled($search) || str_contains(mb_strtolower(implode(' ', [$record['label'], $record['name'], $record['recordset']])), mb_strtolower($search)))
                        && (! filled($filters['model_type']['value'] ?? null) || $record['model_type'] === $filters['model_type']['value'])
                        && (! filled($filters['origin']['value'] ?? null) || $record['origin'] === $filters['origin']['value']))
                    ->sortBy($reordering ? 'position' : ($sortColumn ?: 'position'), SORT_NATURAL | SORT_FLAG_CASE, ! $reordering && $sortDirection === 'desc')
                    ->sortBy('recordset', SORT_NATURAL | SORT_FLAG_CASE);
                $perPage = $reordering || $recordsPerPage === 'all' ? max(1, $records->count()) : $recordsPerPage;
                $page = $reordering ? 1 : $page;

                return new LengthAwarePaginator($records->slice(($page - 1) * $perPage, $perPage), $records->count(), $perPage, $page);
            })
            ->groups(['recordset'])
            ->defaultGroup('recordset')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('recordset')->label('Recordset')->sortable(),
                TextColumn::make('label')->label('Label')->searchable()->sortable(),
                TextColumn::make('name')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')->badge(),
                TextColumn::make('origin')->label('Origin')->formatStateUsing(fn (string $state): string => __($state))->badge(),
                IconColumn::make('customized')->label('Customized')->boolean(),
                IconColumn::make('required')->boolean(),
                IconColumn::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('model_type')->label('Recordset')->options(fn (): array => collect(app(FieldOverrides::class)->recordsets())->map(fn (array $recordset): string => __($recordset['label']))->all() + CustomFieldRegistry::participatingModels()),
                SelectFilter::make('origin')->label('Origin')->options(['Module' => __('Module'), 'Custom' => __('Custom')]),
            ])
            ->recordUrl(fn (array $record): ?string => $record['custom_id'] ? CustomFieldResource::getUrl('view', ['record' => $record['custom_id']]) : null)
            ->recordAction('view')
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                Action::make('view')->label('View')->icon(Heroicon::OutlinedEye)->iconButton()->tooltip(__('View'))
                    ->url(fn (array $record): ?string => $record['custom_id'] ? CustomFieldResource::getUrl('view', ['record' => $record['custom_id']]) : null)
                    ->modalHeading(fn (array $record): string => $record['recordset'].' — '.$record['label'])
                    ->fillForm(fn (array $record): array => ModuleFieldForm::fill($record))
                    ->schema(fn (array $record): array => ModuleFieldForm::components($record, readOnly: true))
                    ->modalSubmitAction(false)->modalCancelActionLabel(__('Close')),
                Demo::guard(Action::make('edit')->label('Edit')->icon(Heroicon::OutlinedPencilSquare)->iconButton()->tooltip(__('Edit'))
                    ->url(fn (array $record): ?string => $record['custom_id'] ? CustomFieldResource::getUrl('edit', ['record' => $record['custom_id']]) : null)
                    ->modalHeading(__('Edit field properties'))
                    ->fillForm(fn (array $record): array => ModuleFieldForm::fill($record))
                    ->schema(fn (array $record): array => ModuleFieldForm::components($record))
                    ->action(function (array $record, array $data): void {
                        ModuleFieldForm::save($record, $data);
                        Notification::make()->success()->title(__('Field settings saved'))->send();
                    })),
                Demo::guard(Action::make('reset')->label('Reset to module defaults')->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->iconButton()->tooltip(__('Reset to module defaults'))
                    ->visible(fn (array $record): bool => $record['origin'] === 'Module' && $record['customized'])
                    ->requiresConfirmation()
                    ->action(function (array $record): void {
                        app(FieldOverrides::class)->save($record['model_type'], $record['name'], []);
                        Notification::make()->success()->title(__('Module defaults restored'))->send();
                    })),
            ]);
    }
}
