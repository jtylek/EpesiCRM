<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            // password, or an OAuth provider key (microsoft, google): the
            // token lives in epesi_oauth_tokens, owned by the account.
            $table->string('auth_method', 32)->default('password')->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('epesi_mail_accounts', function (Blueprint $table): void {
            $table->dropColumn('auth_method');
        });
    }
};
