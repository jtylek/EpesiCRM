<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Notes tab shows the Notes applet only if it has the applet's row. A tab
 * given its key later (2026_10_15_100000) or a hand-made one adopted as the
 * Notes tab has none, so it came up empty: put the applet on every Notes tab
 * that lacks it.
 */
return new class extends Migration
{
    private const WIDGET = 'Epesi\\Modules\\StickyNotes\\Filament\\Widgets\\StickyNotesWidget';

    public function up(): void
    {
        $has = DB::table('dashboard_applets')->where('widget', self::WIDGET)->pluck('dashboard_tab_id')->all();

        foreach (DB::table('dashboard_tabs')->where('key', 'notes')->whereNotIn('id', $has)->get(['id', 'user_id']) as $tab) {
            DB::table('dashboard_applets')->insert([
                'user_id' => $tab->user_id,
                'dashboard_tab_id' => $tab->id,
                'widget' => self::WIDGET,
                'col' => 0,
                'pos' => 0,
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: the applet is part of the Notes tab.
    }
};
