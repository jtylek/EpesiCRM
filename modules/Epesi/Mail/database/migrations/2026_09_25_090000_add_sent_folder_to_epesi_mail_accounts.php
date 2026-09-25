<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a message sent from the CRM is copied on the IMAP server, so it
 * shows up in the user's own mail client like anything else they sent (the
 * SMTP server doesn't do that by itself).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table) {
            $table->boolean('save_to_sent')->default(true)->after('archive_on_sending');
            $table->string('sent_folder')->nullable()->default('Sent')->after('save_to_sent');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table) {
            $table->dropColumn(['save_to_sent', 'sent_folder']);
        });
    }
};
