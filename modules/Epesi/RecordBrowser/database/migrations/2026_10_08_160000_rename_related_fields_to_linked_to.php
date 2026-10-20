<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Related field on tasks, meetings and phone calls is now "Linked to"
 * (Field::linkedTo()), named `linked_to`: their links and any administrator
 * settings for the field move to the new name. Other recordsets' fields of
 * the same type (a module's own, an administrator's) keep their names.
 */
return new class extends Migration
{
    private const RECORDSETS = ['task', 'meeting', 'phone_call'];

    public function up(): void
    {
        $this->rename('related', 'linked_to');
    }

    public function down(): void
    {
        $this->rename('linked_to', 'related');
    }

    private function rename(string $from, string $to): void
    {
        if (Schema::hasTable('epesi_recordbrowser_links')) {
            DB::table('epesi_recordbrowser_links')
                ->whereIn('source_type', self::RECORDSETS)
                ->where('field', $from)
                ->update(['field' => $to]);
        }

        if (Schema::hasTable('epesi_recordbrowser_field_overrides')) {
            DB::table('epesi_recordbrowser_field_overrides')
                ->whereIn('model_type', self::RECORDSETS)
                ->where('field', $from)
                ->update(['field' => $to]);
        }
    }
};
