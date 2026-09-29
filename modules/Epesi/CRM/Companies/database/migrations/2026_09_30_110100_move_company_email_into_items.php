<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * A company's `email` column becomes the first item of its E-mail addresses
 * collection (the EmailAddress type, epesi_recordbrowser_email_addresses), of
 * kind Work. Then the column goes.
 *
 * Runs after Contacts' equivalent migration (by filename timestamp), so a
 * company sharing its address with an already-imported contact — nothing
 * stopped that before, since the two columns' unique indexes were separate —
 * isn't moved: it's logged instead, for an administrator to settle (the
 * address can only belong to one record). A blank column moves nothing.
 * History recorded against the old column stays readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'email')) {
            return;
        }

        $now = now();

        DB::table('companies')->whereNotNull('email')->where('email', '!=', '')->orderBy('id')
            ->chunkById(500, function ($companies) use ($now): void {
                foreach ($companies as $company) {
                    $value = mb_strtolower(trim((string) $company->email));

                    if ($value === '') {
                        continue;
                    }

                    $holder = DB::table('epesi_recordbrowser_email_addresses')->where('value', $value)->first(['owner_type', 'owner_id']);

                    if ($holder !== null) {
                        Log::warning("move_company_email_into_items: company #{$company->id}'s address \"{$value}\" is already used by {$holder->owner_type} #{$holder->owner_id}, left unmigrated");

                        continue;
                    }

                    DB::table('epesi_recordbrowser_email_addresses')->insert([
                        'owner_type' => 'company',
                        'owner_id' => $company->id,
                        'field' => 'emails',
                        'kind' => 'work',
                        'position' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'value' => $value,
                    ]);
                }
            });

        // The unique index has to go explicitly: SQLite's ALTER TABLE
        // emulation doesn't drop an index tied to the dropped column on its
        // own (unlike MySQL), and dropColumn() alone leaves a dangling one.
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropUnique('companies_email_unique');
            $table->dropColumn('email');
        });
    }

    /**
     * The column back, filled from each company's first email item, and
     * those items gone, so up() can run again. Any other address a company
     * had by then is lost: the column holds one.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'email')) {
            return;
        }

        Schema::table('companies', fn (Blueprint $table) => $table->string('email')->nullable()->unique());

        $first = DB::table('epesi_recordbrowser_email_addresses')
            ->where('owner_type', 'company')
            ->where('field', 'emails')
            ->where('kind', 'work')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->unique('owner_id');

        foreach ($first as $item) {
            DB::table('companies')->where('id', $item->owner_id)->update(['email' => $item->value]);
        }

        DB::table('epesi_recordbrowser_email_addresses')->where('owner_type', 'company')->where('field', 'emails')->where('kind', 'work')->delete();
    }
};
