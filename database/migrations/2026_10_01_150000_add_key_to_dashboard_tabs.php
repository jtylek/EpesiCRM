<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A system tab (Main, Agenda, Notes) carries a key: it is part of every
 * dashboard and can't be deleted, and the Notes tab holds only the Notes
 * applet. Tabs a user adds have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_tabs', function (Blueprint $table) {
            $table->string('key', 16)->nullable()->after('name');
        });

        // Dashboards made before keys existed: the tabs the default dashboard created, by their names.
        foreach (['main' => ['Main', 'Główna'], 'agenda' => ['Agenda', 'Terminarz'], 'notes' => ['Notes', 'Notatki']] as $key => $names) {
            $tabs = DB::table('dashboard_tabs')->whereIn('name', $names)->orderBy('id')->get(['id', 'user_id']);

            foreach ($tabs->unique('user_id') as $tab) {
                DB::table('dashboard_tabs')->where('id', $tab->id)->update(['key' => $key]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('dashboard_tabs', function (Blueprint $table) {
            $table->dropColumn('key');
        });
    }
};
