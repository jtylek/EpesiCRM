<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a Roundcube session logged in to an OAuth mail account asks Epesi for
 * a new access token with (Services\OAuthGrants). The secret lives only in
 * that Roundcube session, encrypted; here it is stored as its sha256.
 * `expires_at` is a Unix timestamp, moved forward on every use.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_roundcube_oauth_grants', function (Blueprint $table) {
            $table->id();
            $table->char('token', 64)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('epesi_mail_accounts')->cascadeOnDelete();
            $table->unsignedBigInteger('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_roundcube_oauth_grants');
    }
};
