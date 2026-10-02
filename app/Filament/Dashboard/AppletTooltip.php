<?php

namespace App\Filament\Dashboard;

use BackedEnum;
use Filament\Support\Enums\IconSize;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

use function Filament\Support\generate_icon_html;

/**
 * Shared tooltip content for dashboard applets.
 */
final class AppletTooltip
{
    /** Cut to 500 characters, escaped, line breaks kept. Null when blank, so there is no tooltip. */
    public static function text(?string $value): ?HtmlString
    {
        $value = trim((string) $value);

        return $value === '' ? null : new HtmlString(nl2br(e(Str::limit($value, 500))));
    }

    /**
     * @param  list<string>  $customers
     */
    public static function details(
        string $type,
        string|BackedEnum|Htmlable|null $icon,
        string $title,
        ?string $description,
        string $dateLabel,
        ?string $date,
        array $customers = [],
    ): HtmlString {
        $iconHtml = generate_icon_html($icon, size: IconSize::Small)?->toHtml() ?? '';
        $description = trim((string) $description);
        $description = $description === '' ? '-' : nl2br(e(Str::limit($description, 500)));
        $customers = collect($customers)
            ->map(fn (string $customer): string => trim($customer))
            ->filter()
            ->unique()
            ->implode(', ');

        return new HtmlString('<div class="epesi-calendar-tooltip" style="font-size: 0.8125rem; line-height: 1.35;">'
            .'<div class="epesi-calendar-tooltip-type" style="display: flex; align-items: center; flex-wrap: nowrap; gap: 0.3rem; white-space: nowrap;">'.$iconHtml.'<span>'.e(__($type)).'</span></div>'
            .'<div><strong>'.e($title).'</strong></div>'
            .'<div>'.$description.'</div>'
            .self::row($dateLabel, e($date ?? '-'))
            .self::row('Customers', e($customers !== '' ? $customers : '-'))
            .'</div>');
    }

    /** @return list<string> */
    public static function customers(Model $record): array
    {
        $customers = [];

        foreach (['customers', 'customerCompanies'] as $relation) {
            if (! method_exists($record, $relation)) {
                continue;
            }

            $record->loadMissing($relation);

            foreach ($record->getRelation($relation) as $customer) {
                $customers[] = self::customerName($customer);
            }
        }

        if (method_exists($record, 'customer')) {
            $record->loadMissing('customer');
            $customer = $record->getRelation('customer');

            if ($record->getAttribute('other_customer')) {
                $customers[] = (string) $record->getAttribute('other_customer_name');
            } elseif ($customer instanceof Model) {
                $customers[] = self::customerName($customer);
            }
        }

        return collect($customers)->map(fn (string $customer): string => trim($customer))->filter()->unique()->values()->all();
    }

    private static function row(string $label, string $value): string
    {
        return '<div><strong>'.e(__($label)).':</strong> '.$value.'</div>';
    }

    private static function customerName(Model $customer): string
    {
        foreach (['full_name', 'company_name', 'name', 'title'] as $attribute) {
            $value = $customer->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return '#'.$customer->getKey();
    }
}
