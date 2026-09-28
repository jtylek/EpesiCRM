<?php

namespace Epesi\Modules\RecordBrowser\Models\Concerns;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;

/**
 * Opts a model into administrator-added fields.
 *
 * The hook is Eloquent's per-trait initialiser, which runs inside the model
 * constructor — so by the time anything touches the model, its `cf_*` columns
 * are already fillable and cast like columns the model declared itself.
 *
 * Two consequences worth knowing:
 *
 * - custom fields become mass-assignable, which is what lets a Filament form
 *   save them with no special handling;
 * - `spatie/laravel-activitylog`'s `logFillable()` reads `getFillable()`, so
 *   **history covers custom fields with no extra code** — the same property
 *   RecordBrowser's `<table>_edit_history` has.
 *
 * Also what makes a model appear in Administration → Fields: the screen offers
 * every model in the morph map that uses this trait, so opting in is one `use`
 * statement and nothing else. It brings HasFileFields, HasRecordLinks and
 * HasCollections along, so a File, "link to any record" or Collection field an
 * administrator adds has its files, links or items looked after too.
 */
trait HasCustomFields
{
    use HasCollections, HasFileFields, HasRecordLinks;

    public function initializeHasCustomFields(): void
    {
        // A link field's relationship ("cf12"), registered on every instance
        // rather than once per class: the definitions can change within a
        // process (the registry is refreshed on every save of one), and the
        // resolver is only an array entry.
        foreach (CustomFieldRegistry::relationsFor(static::class) as $name => $resolver) {
            static::resolveRelationUsing($name, $resolver);
        }

        $columns = CustomFieldRegistry::columnsFor(static::class);

        if ($columns === []) {
            return;
        }

        $this->mergeFillable($columns);
        $this->mergeCasts(CustomFieldRegistry::castsFor(static::class));
    }
}
