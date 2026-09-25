<?php

namespace Epesi\Modules\Notes\Filament\Resources\Notes;

use BackedEnum;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\Notes\Models\Note;
use Epesi\Modules\RecordBrowser\Recordset\Field;
use Epesi\Modules\RecordBrowser\Recordset\RecordsetResource;
use Filament\Support\Icons\Heroicon;

/**
 * A recordset, declared and nothing else.
 *
 * Everything this feature has — the list with its columns, search, sorting and
 * filters; the view with its linked Contact badge, Record Info tab and History
 * tab; the create and edit forms with their validation; and any field an
 * administrator adds from Administration → Fields — is built from `fields()` by
 * the engine. There is no form class, no infolist class, no table class and no
 * relation manager in this module.
 */
class NoteResource extends RecordsetResource
{
    protected static ?string $model = Note::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $recordsetDefaultSort = 'updated_at';

    protected static string $recordsetDefaultSortDirection = 'desc';

    public static function fields(): array
    {
        return [
            Field::text('title')->required()->fullWidth()->inTable(),
            Field::relation('contact_id', Contact::class)->label('Contact')->inTable()->filterable(),
            Field::boolean('pinned')->inTable()->filterable(),
            Field::longText('content'),

            // Only a column: the timestamp belongs on the list and in the
            // Record Info tab, not on the form. Declaring it here stops the
            // engine adding its own hidden-by-default copy.
            Field::dateTime('updated_at')->label('Updated')->onlyInTable(),
        ];
    }
}
