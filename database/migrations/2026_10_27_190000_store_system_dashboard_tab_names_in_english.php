<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Main, Agenda and Notes used to be stored translated into the language of
 * whoever first opened the dashboard; on the demo, where visitors share the
 * accounts, that was anybody's. They are stored in English now and translated
 * when shown (DashboardTab::label()). A system tab still under a translation
 * of its default name goes back to the English one; one the user renamed
 * stays as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        $locales = collect(glob(lang_path('*.json')) ?: [])
            ->map(fn (string $file): string => Str::before(basename($file), '.'))
            ->unique();

        foreach (config('dashboard.keys', []) as $name => $key) {
            $translations = $locales
                ->map(fn (string $locale): string => (string) trans($name, [], $locale))
                // Names the dashboard gave these tabs before they had keys.
                ->merge(['main' => ['Główna'], 'agenda' => ['Terminarz'], 'notes' => ['Notatki']][$key] ?? [])
                ->reject(fn (string $translation): bool => $translation === $name)
                ->unique()
                ->values()
                ->all();

            if ($translations !== []) {
                DB::table('dashboard_tabs')
                    ->where('key', $key)
                    ->whereIn('name', $translations)
                    ->update(['name' => $name]);
            }
        }
    }

    public function down(): void
    {
        // Nothing to undo: an English name is shown translated either way.
    }
};
