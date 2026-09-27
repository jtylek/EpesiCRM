<?php

namespace App\Filament\Administration\Resources\Users\Tables;

use App\Filament\Administration\Resources\Users\UserActions;
use App\Filament\Administration\Resources\Users\UserResource;
use App\Models\User;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('contact'))
            ->columns([
                // The person the login is for: its Contact (see UserForm).
                // An account with no contact shows its own name, as
                // User::displayName() does everywhere else.
                UserResource::contactBadge(TextColumn::make('contact')->label('Contact'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => self::matching($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(Contact::query()->select('last_name')->whereColumn('contacts.user_id', 'users.id')->limit(1), $direction)
                        ->orderBy('name', $direction)),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->placeholder(__('-')),
                IconColumn::make('active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive')
                    ->placeholder(__('All'))
                    ->default(true),
            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->recordActions([
                ViewAction::make()
                    ->iconButton()
                    ->tooltip(__('View')),
                EditAction::make()
                    ->iconButton()
                    ->tooltip(__('Edit')),
                UserActions::logInAs()
                    ->iconButton()
                    ->tooltip(__('Log in as user')),
                UserActions::toggleActive()
                    ->iconButton()
                    ->tooltip(fn ($record): string => $record->active ? __('Deactivate') : __('Reactivate')),
            ]);
    }

    /**
     * Users whose contact's first or last name, or whose own name, holds
     * every word typed: "ann kow" finds Ann Kowalska.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    protected static function matching(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/', trim($search), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$word}%")
                ->orWhereHas('contact', fn (Builder $contact) => $contact
                    ->where('first_name', 'like', "%{$word}%")
                    ->orWhere('last_name', 'like', "%{$word}%")));
        }

        return $query;
    }
}
