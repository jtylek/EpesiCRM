<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company's phone and fax become items of its Phone numbers collection (the
 * PhoneNumber type, epesi_recordbrowser_phone_numbers), of kinds Work and
 * Fax, in that order; its web address becomes an online account of kind
 * Website (epesi_recordbrowser_online_accounts). Then the three columns go.
 *
 * A blank column moves nothing. History recorded against the old columns
 * stays readable: a key with no field of its own shows under its column's
 * name ("Web Address").
 */
return new class extends Migration
{
    /** Column => the kind its number moves in as, in order. */
    private const PHONES = ['phone' => 'work', 'fax' => 'fax'];

    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'phone')) {
            return;
        }

        $now = now();

        DB::table('companies')->orderBy('id')->chunkById(500, function ($companies) use ($now): void {
            $phones = [];
            $accounts = [];

            foreach ($companies as $company) {
                $item = ['owner_type' => 'company', 'owner_id' => $company->id, 'created_at' => $now, 'updated_at' => $now];
                $position = 0;

                foreach (self::PHONES as $column => $kind) {
                    // As wide as the item's column: the form never allowed more.
                    $value = mb_substr(trim((string) $company->{$column}), 0, 64);

                    if ($value !== '') {
                        $digits = preg_replace('/\D+/', '', $value);
                        $phones[] = [...$item, 'field' => 'phones', 'kind' => $kind, 'position' => ++$position, 'value' => $value, 'digits' => $digits === '' ? null : $digits];
                    }
                }

                if (($web = trim((string) $company->web_address)) !== '') {
                    $accounts[] = [...$item, 'field' => 'online_accounts', 'kind' => 'website', 'position' => 1, 'value' => $web];
                }
            }

            if ($phones !== []) {
                DB::table('epesi_recordbrowser_phone_numbers')->insert($phones);
            }

            if ($accounts !== []) {
                DB::table('epesi_recordbrowser_online_accounts')->insert($accounts);
            }
        });

        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn([...array_keys(self::PHONES), 'web_address']));
    }

    /**
     * The columns back, each filled from the company's first item of its
     * kind, and the companies' items gone, so up() can run again. Any other
     * number or account a company had by then is lost: the columns hold one
     * of each.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'phone')) {
            return;
        }

        Schema::table('companies', function (Blueprint $table): void {
            foreach ([...array_keys(self::PHONES), 'web_address'] as $column) {
                $table->string($column)->nullable();
            }
        });

        $items = ['epesi_recordbrowser_phone_numbers' => ['phones', self::PHONES], 'epesi_recordbrowser_online_accounts' => ['online_accounts', ['web_address' => 'website']]];

        foreach ($items as $table => [$field, $columns]) {
            foreach ($columns as $column => $kind) {
                $first = DB::table($table)
                    ->where('owner_type', 'company')
                    ->where('field', $field)
                    ->where('kind', $kind)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get()
                    ->unique('owner_id');

                foreach ($first as $item) {
                    DB::table('companies')->where('id', $item->owner_id)->update([$column => $item->value]);
                }
            }

            DB::table($table)->where('owner_type', 'company')->where('field', $field)->delete();
        }
    }
};
