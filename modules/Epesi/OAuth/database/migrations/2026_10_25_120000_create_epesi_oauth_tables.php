<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The app each installation registers with a provider (the redirect
        // URI holds its own domain): one row per provider and environment,
        // since a sandbox has apps and credentials of its own.
        Schema::create('epesi_oauth_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('environment', 32)->default('production');
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->string('tenant')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'environment'], 'epesi_oauth_clients_provider_environment_unique');
        });

        // One token per owner and provider: two seller accounts are two
        // owners, each with its own token from the same app.
        Schema::create('epesi_oauth_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('environment', 32)->default('production');
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            // Nullable, with no default: a first NOT NULL timestamp can get
            // ON UPDATE CURRENT_TIMESTAMP on MariaDB (AI-shared/cron.md).
            $table->timestamp('expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status', 32)->default('active');
            $table->text('last_error')->nullable();
            // Who signed in at the provider, as its ID token says (for display only).
            $table->string('account')->nullable();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            // Named: the generated names run past MySQL's 64 characters.
            $table->unique(['owner_type', 'owner_id', 'provider'], 'epesi_oauth_tokens_owner_provider_unique');
            $table->index(['status', 'expires_at'], 'epesi_oauth_tokens_status_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_oauth_tokens');
        Schema::dropIfExists('epesi_oauth_clients');
    }
};
