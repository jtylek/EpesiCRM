<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Every dashboard has a Notes tab (key "notes"), the only place the Notes
 * applet lives. One made before that tab existed, or a tab a user named Notes
 * by hand (it has no key), never got it, so the applet showed nowhere. Give
 * such a user's own Notes tab the key, or add the tab.
 */
return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('dashboard_tabs')->distinct()->pluck('user_id')
            ->intersect(DB::table('users')->pluck('id'));
        $hasNotes = DB::table('dashboard_tabs')->where('key', 'notes')->pluck('user_id')->all();

        foreach ($users->diff($hasNotes) as $userId) {
            $own = DB::table('dashboard_tabs')
                ->where('user_id', $userId)
                ->whereNull('key')
                ->whereIn('name', ['Notes', 'Notatki'])
                ->orderBy('id')
                ->first();

            if ($own) {
                DB::table('dashboard_tabs')->where('id', $own->id)->update(['key' => 'notes']);

                continue;
            }

            DB::table('dashboard_tabs')->insert([
                'user_id' => $userId,
                'name' => 'Notes',
                'key' => 'notes',
                'pos' => (int) DB::table('dashboard_tabs')->where('user_id', $userId)->max('pos') + 1,
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the tab is part of the dashboard now.
    }
};
