<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auto-archive of the inbox is gone: nothing is archived without a person
 * looking at the message first. Only the archive folder is fetched now, so the
 * per-account option and its folder list have nothing left to say.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->dropColumn(['auto_archive', 'auto_archive_folders']);
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->boolean('auto_archive')->default(false)->after('archive_folder');
            $table->json('auto_archive_folders')->nullable()->after('auto_archive');
        });
    }
};
