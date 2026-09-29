<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The extra addresses `epesi_mail_addresses` held (rc_multiple_emails) become
 * further items of their contact's or company's E-mail addresses collection,
 * of kind Other, after the primary (Work) item Contacts'/Companies' own
 * migrations already moved. Then the table, MailAddress and the "E-mail
 * addresses" tab all go — the collection is edited on the owner's own form
 * now.
 *
 * Runs after Contacts' and Companies' equivalent migrations (by filename
 * timestamp), so an extra address already claimed there — or by an earlier
 * row of this same table — is logged instead of moved: the address can only
 * belong to one record. Nothing here is worth undoing past a restore: by the
 * time down() could run, further edits to the collection would already be
 * indistinguishable from what this migration moved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('epesi_mail_addresses')) {
            return;
        }

        $now = now();
        /** @var array<int, int> owner_type|owner_id => next position */
        $positions = [];

        DB::table('epesi_recordbrowser_email_addresses')
            ->select('owner_type', 'owner_id')
            ->selectRaw('MAX(position) as max_position')
            ->groupBy('owner_type', 'owner_id')
            ->get()
            ->each(function ($row) use (&$positions): void {
                $positions["{$row->owner_type}|{$row->owner_id}"] = (int) $row->max_position;
            });

        DB::table('epesi_mail_addresses')->orderBy('id')->each(function ($address) use ($now, &$positions): void {
            $value = mb_strtolower(trim((string) $address->email));

            if ($value === '') {
                return;
            }

            $holder = DB::table('epesi_recordbrowser_email_addresses')->where('value', $value)->first(['owner_type', 'owner_id']);

            if ($holder !== null) {
                Log::warning("move_mail_addresses_into_email_items: {$address->addressable_type} #{$address->addressable_id}'s extra address \"{$value}\" is already used by {$holder->owner_type} #{$holder->owner_id}, left unmigrated");

                return;
            }

            $key = "{$address->addressable_type}|{$address->addressable_id}";
            $position = ($positions[$key] ?? 0) + 1;
            $positions[$key] = $position;

            DB::table('epesi_recordbrowser_email_addresses')->insert([
                'owner_type' => $address->addressable_type,
                'owner_id' => $address->addressable_id,
                'field' => 'emails',
                'kind' => 'other',
                'position' => $position,
                'created_at' => $now,
                'updated_at' => $now,
                'value' => $value,
            ]);
        });

        Schema::dropIfExists('epesi_mail_addresses');
    }

    /** Nothing to undo: the table is gone for good, absorbed into the collection. */
    public function down(): void {}
};
