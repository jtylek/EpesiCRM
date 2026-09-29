<?php

use Epesi\Modules\CommonData\CommonDataRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The kinds a phone number can be (`Phone_Kinds`), the messengers that can
 * reach one (`Phone_Messengers`) and the services an online account can be
 * on (`Online_Account_Kinds`), each in its order.
 *
 * As Address_Kinds: only a list that is missing, and readonly, so the
 * defaults stay and an administrator can add more ("Pager", "Discord").
 * The query builder, not the CommonData facade: on a fresh install this runs
 * before CommonData's classes are loaded.
 */
return new class extends Migration
{
    /** List => keys and labels, in their order. The labels are translated when shown. */
    private const LISTS = [
        'Phone_Kinds' => [
            'work' => 'Work',
            'mobile' => 'Mobile',
            'home' => 'Home',
            'fax' => 'Fax',
            'other' => 'Other',
        ],
        // The links each opens live in PhoneNumber, by key.
        'Phone_Messengers' => [
            'whatsapp' => 'WhatsApp',
            'signal' => 'Signal',
            'viber' => 'Viber',
            'telegram' => 'Telegram',
        ],
        // The links a handle makes live in OnlineAccount, by key.
        'Online_Account_Kinds' => [
            'website' => 'Website',
            'linkedin' => 'LinkedIn',
            'telegram' => 'Telegram',
            'teams' => 'Microsoft Teams',
            'facebook' => 'Facebook',
            'x' => 'X',
            'instagram' => 'Instagram',
            'other' => 'Other',
        ],
    ];

    public function up(): void
    {
        foreach (self::LISTS as $list => $items) {
            $this->createList($list, $items);
        }

        if (class_exists(CommonDataRepository::class)) {
            CommonDataRepository::invalidate();
        }
    }

    /** Nothing to undo: administrators may have added entries, and items use them. */
    public function down(): void {}

    /**
     * @param  array<string, string>  $items
     */
    private function createList(string $list, array $items): void
    {
        $table = DB::table('common_data');

        if ($table->clone()->where('path', $list)->exists()) {
            return;
        }

        $now = now();
        $parentId = $table->clone()->insertGetId([
            'parent_id' => null,
            'key' => $list,
            'value' => null,
            'path' => $list,
            'readonly' => true,
            'position' => (int) $table->clone()->whereNull('parent_id')->max('position') + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $position = 0;

        foreach ($items as $key => $value) {
            $table->clone()->insert([
                'parent_id' => $parentId,
                'key' => $key,
                'value' => $value,
                'path' => $list.'/'.$key,
                'readonly' => true,
                'position' => ++$position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
