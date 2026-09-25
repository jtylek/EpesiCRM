<?php

namespace Epesi\Modules\Shoutbox\Filament\Pages;

use App\Filament\Concerns\HasPageIconBreadcrumb;
use App\Filament\Concerns\HidesPageHeading;
use App\Filament\Concerns\TranslatesPageLabels;
use BackedEnum;
use Epesi\Modules\Shoutbox\Filament\Concerns\ComposesMessages;
use Epesi\Modules\Shoutbox\Filament\Concerns\RedrawsWhenChanged;
use Epesi\Modules\Shoutbox\Models\Message;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * The applet's message box and polling over every Shoutbox message visible
 * to you, not just the widget's latest SHOWN batch — a plain table rather
 * than an addition to the RecordBrowser engine, since Message isn't a
 * recordset.
 */
class Shoutbox extends Page implements HasTable
{
    use ComposesMessages;
    use HasPageIconBreadcrumb;
    use HidesPageHeading;
    use InteractsWithTable;
    use RedrawsWhenChanged;
    use TranslatesPageLabels;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Shoutbox';

    protected static ?string $title = 'Shoutbox';

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAnyRole(['super_admin', 'manager', 'employee']) ?? false;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([View::make('epesi-shoutbox::compose')])
                ->extraAttributes(['wire:poll.10s' => 'poll']),
            EmbeddedTable::make(),
        ]);
    }

    /**
     * Messages are only ever added or deleted, so their count and the newest
     * id catch every change, whatever page or search the table is on. The
     * last number drops as the ten minutes in which you may delete one of
     * yours run out, taking its trash icon with it.
     */
    protected function fingerprint(): string
    {
        $messages = Message::query()
            ->visibleTo(Auth::user())
            ->where('deleted', false)
            ->selectRaw('count(*) as total, max(id) as newest, sum(case when user_id = ? and created_at > ? then 1 else 0 end) as deletable', [
                Auth::id(),
                now()->subMinutes(Message::DELETE_WINDOW_MINUTES),
            ])
            ->toBase()
            ->first();

        return "{$messages->total} {$messages->newest} {$messages->deletable}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Message::query()
                ->visibleTo(Auth::user())
                ->where('deleted', false)
                ->with(['author.contact', 'recipient.contact']))
            ->heading(__('Shoutbox'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('author')
                    ->label('From')
                    ->state(fn (Message $record): string => $record->author?->displayName() ?? __('Anonymous')),
                TextColumn::make('recipient')
                    ->label('To')
                    ->state(fn (Message $record): ?string => $record->recipient?->displayName())
                    ->placeholder(__('Everyone'))
                    ->badge()
                    ->color(fn (Message $record): string => $record->to_user_id !== null ? 'primary' : 'gray'),
                TextColumn::make('message')
                    ->label('Message')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('delete')
                    ->label('Delete')
                    ->icon(Heroicon::OutlinedTrash)
                    ->iconButton()
                    ->color('danger')
                    ->visible(fn (Message $record): bool => $record->canBeDeletedBy(Auth::user()))
                    ->requiresConfirmation()
                    ->action(fn (Message $record) => $record->update(['deleted' => true])),
            ]);
    }
}
