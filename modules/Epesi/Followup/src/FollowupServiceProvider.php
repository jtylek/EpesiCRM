<?php

namespace Epesi\Modules\Followup;

use App\Enums\RecordStatus;
use Epesi\Modules\RecordBrowser\Extensions\RecordExtensions;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FollowupServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RecordExtensions::headerActions(
            'followup',
            fn (Model $record): array => [static::action($record)],
            array_keys(Followup::TYPES),
        );
    }

    /**
     * "Close / Follow-up" on an open task, meeting or phone call: set its
     * final status, optionally leave a note, and optionally schedule the next
     * activity — Epesi's Follow-up leightbox (Save / New Meeting / New Task /
     * New Phonecall), as one modal.
     */
    protected static function action(Model $record): Action
    {
        return Action::make('followup')
            ->label('Close / Follow-up')
            ->icon(Heroicon::OutlinedArrowUturnRight)
            ->color('gray')
            ->visible(fn (): bool => Followup::isOpen($record) && Gate::allows('update', $record))
            ->modalHeading(__('Follow-up'))
            ->modalSubmitActionLabel(__('Save'))
            ->schema([
                Select::make('status')
                    ->options(RecordStatus::class)
                    ->default(RecordStatus::Closed)
                    ->required()
                    ->selectablePlaceholder(false),
                Textarea::make('note')
                    ->rows(3),
                Radio::make('followup')
                    ->label('Then')
                    ->options([
                        'none' => __('Nothing'),
                        'task' => __('New task'),
                        'meeting' => __('New meeting'),
                        'phone_call' => __('New phone call'),
                    ])
                    ->default('none')
                    ->inline()
                    ->live(),
                TextInput::make('title')
                    ->default(Followup::titleOf($record))
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => $get('followup') !== 'none')
                    ->required(fn (Get $get): bool => $get('followup') !== 'none'),
                DateTimePicker::make('when')
                    ->label('When')
                    ->seconds(false)
                    ->default(now()->addDay()->setTime(9, 0))
                    ->visible(fn (Get $get): bool => $get('followup') !== 'none')
                    ->required(fn (Get $get): bool => $get('followup') !== 'none'),
            ])
            ->action(function (array $data, Action $action) use ($record): void {
                $status = $data['status'] instanceof RecordStatus ? $data['status'] : RecordStatus::from((int) $data['status']);

                $followup = Followup::close(
                    $record,
                    $status,
                    $data['note'] ?? null,
                    ($data['followup'] ?? 'none') !== 'none' ? $data['followup'] : null,
                    $data['title'] ?? null,
                    filled($data['when'] ?? null) ? now()->parse($data['when']) : null,
                );

                Notification::make()
                    ->title($followup ? __('Closed; follow-up created') : __('Saved'))
                    ->success()
                    ->send();

                $resource = $followup ? Filament::getModelResource($followup::class) : null;

                if ($resource) {
                    $action->redirect($resource::getUrl('edit', ['record' => $followup]));
                }
            });
    }
}
