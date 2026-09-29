<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A contact's `email` column becomes the first item of its E-mail addresses
 * collection (the EmailAddress type, epesi_recordbrowser_email_addresses), of
 * kind Work. Then the column goes.
 *
 * Runs before Companies' and Mail's equivalent migrations (by filename
 * timestamp), into an empty e-mail addresses table: contacts.email already
 * carried its own unique index, so no two contacts can collide here — this
 * migration never has a conflict to report. A blank column moves nothing.
 * History recorded against the old column stays readable: a key with no
 * field of its own shows under its column's name ("Email").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'email')) {
            return;
        }

        $now = now();

        DB::table('contacts')->whereNotNull('email')->where('email', '!=', '')->orderBy('id')
            ->chunkById(500, function ($contacts) use ($now): void {
                $items = [];

                foreach ($contacts as $contact) {
                    $value = mb_strtolower(trim((string) $contact->email));

                    if ($value === '') {
                        continue;
                    }

                    $items[] = [
                        'owner_type' => 'contact',
                        'owner_id' => $contact->id,
                        'field' => 'emails',
                        'kind' => 'work',
                        'position' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'value' => $value,
                    ];
                }

                if ($items !== []) {
                    DB::table('epesi_recordbrowser_email_addresses')->insert($items);
                }
            });

        // The unique index has to go explicitly: SQLite's ALTER TABLE
        // emulation doesn't drop an index tied to the dropped column on its
        // own (unlike MySQL), and dropColumn() alone leaves a dangling one.
        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropUnique('contacts_email_unique');
            $table->dropColumn('email');
        });
    }

    /**
     * The column back, filled from each contact's first email item, and
     * those items gone, so up() can run again. Any other address a contact
     * had by then is lost: the column holds one.
     */
    public function down(): void
    {
        if (Schema::hasColumn('contacts', 'email')) {
            return;
        }

        Schema::table('contacts', fn (Blueprint $table) => $table->string('email')->nullable()->unique());

        $first = DB::table('epesi_recordbrowser_email_addresses')
            ->where('owner_type', 'contact')
            ->where('field', 'emails')
            ->where('kind', 'work')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->unique('owner_id');

        foreach ($first as $item) {
            DB::table('contacts')->where('id', $item->owner_id)->update(['email' => $item->value]);
        }

        DB::table('epesi_recordbrowser_email_addresses')->where('owner_type', 'contact')->where('field', 'emails')->where('kind', 'work')->delete();
    }
};
