<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account's spam folder, where Mark as junk in the Mailbox moves mail.
 * Empty: Roundcube finds it at login (the server's \Junk flag, then by name).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table) {
            $table->string('junk_folder')->nullable()->after('sent_folder');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table) {
            $table->dropColumn('junk_folder');
        });
    }
};
