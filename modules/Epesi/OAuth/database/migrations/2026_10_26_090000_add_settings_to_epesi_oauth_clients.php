<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The provider's own settings of each app (OAuthProvider::appFields()), such
 * as the User-Agent an Allegro app was registered with: entered on the app's
 * OAuth Apps record, so each environment's app keeps its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('epesi_oauth_clients', 'settings')) {
            return;
        }

        Schema::table('epesi_oauth_clients', function (Blueprint $table): void {
            $table->json('settings')->nullable()->after('tenant');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('epesi_oauth_clients', 'settings')) {
            Schema::table('epesi_oauth_clients', function (Blueprint $table): void {
                $table->dropColumn('settings');
            });
        }
    }
};
