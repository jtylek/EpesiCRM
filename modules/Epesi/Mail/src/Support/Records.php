<?php

namespace Epesi\Modules\Mail\Support;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Epesi\Modules\RecordBrowser\Recordset\LinkableRecordsets;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Naming and linking any CRM record from mail screens, through the record's
 * own Filament resource (so a Contact reads "Ann Smith", with a link).
 */
class Records
{
    public static function title(Model $record): string
    {
        $resource = static::resource($record);
        $title = $resource ? $resource::getRecordTitle($record) : null;

        return filled($title) ? strip_tags((string) $title) : LinkableRecordsets::label($record->getMorphClass()).' #'.$record->getKey();
    }

    /** "Company: Acme Ltd" — plain text, for a notification title or any other non-badge use. */
    public static function label(Model $record): string
    {
        return LinkableRecordsets::label($record->getMorphClass()).': '.static::title($record);
    }

    /** The recordset's icon, then the record's own title — an Htmlable label, so LinkedRecords::badges() trusts it instead of sanitizing its icon away. */
    public static function badgeLabel(Model $record): HtmlString
    {
        return new HtmlString(LinkableRecordsets::iconLabel($record->getMorphClass(), static::title($record)));
    }

    public static function url(Model $record): ?string
    {
        $resource = static::resource($record);

        return $resource && $resource::hasPage('view') ? $resource::getUrl('view', ['record' => $record]) : null;
    }

    /**
     * Where a message about $record should go by default: its own address,
     * or — for an activity — its customers' addresses.
     *
     * @return array<int, string>
     */
    public static function emailsOf(?Model $record): array
    {
        if ($record === null) {
            return [];
        }

        if ($record instanceof Contact || $record instanceof Company) {
            return $record->collection('emails')->pluck('value')->all();
        }

        $emails = [];

        foreach (['customers', 'customerCompanies', 'contact', 'company'] as $relation) {
            if (! method_exists($record, $relation)) {
                continue;
            }

            $related = $record->{$relation};

            foreach ($related instanceof Model ? [$related] : ($related ?? []) as $model) {
                $emails = [...$emails, ...($model->collection('emails')->pluck('value')->all())];
            }
        }

        return array_values(array_unique($emails));
    }

    protected static function resource(Model $record): ?string
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getPanel('main', isStrict: false);

        return $panel?->getModelResource($record::class);
    }
}
