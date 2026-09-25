<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mail is now always linked to the contact of whoever archived it (its
 * employee), so it shows on that contact's E-mails tab; earlier archiving
 * left that link out. Adds it to mail archived before.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('epesi_mail_links')->insertUsing(
            ['mail_id', 'linkable_type', 'linkable_id', 'created_at', 'updated_at'],
            DB::table('epesi_mails as m')
                ->whereNotNull('m.employee_id')
                ->whereNotExists(fn ($query) => $query->from('epesi_mail_links as l')
                    ->whereColumn('l.mail_id', 'm.id')
                    ->where('l.linkable_type', 'contact')
                    ->whereColumn('l.linkable_id', 'm.employee_id'))
                ->select(['m.id', DB::raw("'contact'"), 'm.employee_id', DB::raw('?'), DB::raw('?')])
                ->addBinding([$now, $now], 'select'),
        );
    }

    public function down(): void
    {
        // The links are indistinguishable from ones archiving made; nothing to undo.
    }
};
