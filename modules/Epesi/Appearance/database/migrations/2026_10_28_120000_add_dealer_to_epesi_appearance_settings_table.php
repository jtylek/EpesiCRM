<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The dealer portal's own title and logos (light and dark mode), like the
     * customer portal's portal_title / portal_logo / portal_logo_dark. Empty
     * means the defaults: "Dealer Portal" and the built-in epesi logo.
     */
    private const COLUMNS = ['dealer_title', 'dealer_logo', 'dealer_logo_dark'];

    public function up(): void
    {
        $after = 'portal_logo_dark';

        foreach (self::COLUMNS as $column) {
            if (! Schema::hasColumn('epesi_appearance_settings', $column)) {
                Schema::table('epesi_appearance_settings', function (Blueprint $table) use ($column, $after): void {
                    $table->string($column)->nullable()->after($after);
                });
            }

            $after = $column;
        }
    }

    public function down(): void
    {
        $existing = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('epesi_appearance_settings', $column),
        ));

        if ($existing) {
            Schema::table('epesi_appearance_settings', function (Blueprint $table) use ($existing): void {
                $table->dropColumn($existing);
            });
        }
    }
};
