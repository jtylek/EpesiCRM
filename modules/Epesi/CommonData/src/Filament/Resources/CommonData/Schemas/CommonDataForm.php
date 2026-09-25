<?php

namespace Epesi\Modules\CommonData\Filament\Resources\CommonData\Schemas;

use Closure;
use Epesi\Modules\CommonData\Models\CommonDataNode;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CommonDataForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->required()
                ->maxLength(64)
                ->helperText(__('Used by code to read this entry back. Cannot contain "/".'))
                ->rules([
                    // Epesi's check_key2: "/" separates path segments, and a key
                    // containing one would also break subtree queries.
                    'regex:/^[^\/]+$/',
                    static::uniqueWithinParent(...),
                ]),

            Textarea::make('value')
                ->label('Value')
                ->rows(2)
                ->helperText(__('What people see. Leave empty for an entry that only groups the entries below it.'))
                ->columnSpanFull(),
        ]);
    }

    /**
     * Epesi's check_key rule. Not `->unique()`: the constraint is on
     * (parent, key), and the parent of a new entry is the level the page is
     * currently showing rather than anything in the form.
     */
    protected static function uniqueWithinParent(?CommonDataNode $record, mixed $livewire): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($record, $livewire): void {
            $parentId = $record?->parent_id
                ?? (method_exists($livewire, 'currentNode') ? $livewire->currentNode()?->getKey() : null);

            $taken = CommonDataNode::query()
                ->where('parent_id', $parentId)
                ->where('key', $value)
                ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                ->exists();

            if ($taken) {
                $fail(__('An entry with this key already exists at this level.'));
            }
        };
    }
}
