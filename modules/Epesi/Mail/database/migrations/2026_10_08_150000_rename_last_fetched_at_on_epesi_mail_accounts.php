<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheduled job no longer fetches mail into the archive: `mail:check` only
 * asks each account's server for its INBOX unread count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->renameColumn('last_fetched_at', 'last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->renameColumn('last_checked_at', 'last_fetched_at');
        });
    }
};
