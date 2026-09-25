<?php

namespace App\Services\LegacyImport;

/**
 * Decodes the handful of raw string encodings Epesi's RecordBrowser storage
 * uses for non-scalar field types, confirmed against the live legacy
 * database rather than guessed (see contact_data_1.f_group,
 * task_data_1.f_customers, phonecall_data_1.f_customer samples).
 */
class LegacyValue
{
    /**
     * A plain multiselect: "__3__6__" -> ['3', '6']. Used for both
     * commondata multiselects (f_group: "__office__field__") and
     * single-recordset multiselects (f_employees: "__5__6__").
     *
     * @return list<string>
     */
    public static function multi(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(explode('__', trim($raw, '_'))));
    }

    /**
     * A single dual-recordset reference: "contact/95" -> ['type' => 'contact', 'id' => 95].
     * Used for phonecall's Customer field.
     */
    public static function typedRef(?string $raw): ?array
    {
        $raw = trim((string) $raw);

        if ($raw === '' || ! str_contains($raw, '/')) {
            return null;
        }

        [$type, $id] = explode('/', $raw, 2);

        return ['type' => $type, 'id' => (int) $id];
    }

    /**
     * A dual-recordset multiselect: "__company/49__contact/54__" ->
     * [['type' => 'company', 'id' => 49], ['type' => 'contact', 'id' => 54]].
     * Used for task/meeting's Customers field.
     *
     * @return list<array{type: string, id: int}>
     */
    public static function typedRefMulti(?string $raw): array
    {
        return array_values(array_filter(array_map(
            fn (string $token) => self::typedRef($token),
            self::multi($raw)
        )));
    }
}
