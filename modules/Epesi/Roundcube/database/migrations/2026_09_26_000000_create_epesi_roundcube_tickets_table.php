<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time logins into Roundcube. The Mailbox page stores a ticket and puts
 * its token in the iframe URL; Roundcube's epesi_sso plugin redeems (deletes)
 * it. The token is stored only as its sha256, and the account's credentials
 * travel encrypted in `payload`. `expires_at` is a Unix timestamp because
 * Roundcube compares it in its own timezone-less PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_roundcube_tickets', function (Blueprint $table) {
            $table->id();
            $table->char('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('epesi_mail_accounts')->cascadeOnDelete();
            $table->text('payload');
            $table->unsignedBigInteger('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_roundcube_tickets');
    }
};
