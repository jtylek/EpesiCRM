<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The installation's registration with the Epesi Store: its identity (UUID and
 * the secret only it and the store know), the state the store last reported,
 * and what the daily check-in learned about new epesi versions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->uuid('instance_uuid')->nullable();
            $table->text('instance_secret')->nullable();
            $table->string('registration_status', 20)->default('unregistered');
            $table->string('registered_email')->nullable();
            $table->string('registered_url')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->boolean('url_mismatch')->default(false);
            $table->boolean('transfer_pending')->default(false);
            $table->boolean('diagnostics')->default(false);
            $table->string('web_server')->nullable();
            $table->string('latest_core_version', 32)->nullable();
            $table->boolean('latest_core_security')->default(false);
            $table->string('notified_version', 32)->nullable();
            $table->timestamp('last_check_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('epesi_store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'instance_uuid', 'instance_secret', 'registration_status', 'registered_email', 'registered_url',
                'registered_at', 'url_mismatch', 'transfer_pending', 'diagnostics', 'web_server',
                'latest_core_version', 'latest_core_security', 'notified_version', 'last_check_at',
            ]);
        });
    }
};
