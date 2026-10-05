<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the owner of an e-mail address proved it is theirs, by opening the link
 * e-mailed to it (the customer portal, AI-shared/Customer-portal.md). Null:
 * not verified. Nullable, so MySQL adds no ON UPDATE CURRENT_TIMESTAMP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_recordbrowser_email_addresses', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_recordbrowser_email_addresses', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};
