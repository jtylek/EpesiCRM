<?php

namespace Epesi\Modules\Mail\Support;

use Epesi\Modules\CRM\Companies\Models\Company;
use Epesi\Modules\CRM\Contacts\Models\Contact;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

        return filled($title) ? strip_tags((string) $title) : Str::headline($record->getMorphClass()).' #'.$record->getKey();
    }

    public static function label(Model $record): string
    {
        return Str::headline($record->getMorphClass()).': '.static::title($record);
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
            return array_filter([$record->email]);
        }

        $emails = [];

        foreach (['customers', 'customerCompanies', 'contact', 'company'] as $relation) {
            if (! method_exists($record, $relation)) {
                continue;
            }

            $related = $record->{$relation};

            foreach ($related instanceof Model ? [$related] : ($related ?? []) as $model) {
                if (filled($model->email ?? null)) {
                    $emails[] = $model->email;
                }
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
