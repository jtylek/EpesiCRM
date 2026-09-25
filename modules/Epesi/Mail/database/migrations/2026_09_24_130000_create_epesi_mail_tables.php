<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Port of CRM/Mail's recordsets:
 *
 *   rc_accounts          → epesi_mail_accounts (+ epesi_mail_account_folders,
 *                          the per-folder IMAP sync position Roundcube used to
 *                          keep for us)
 *   rc_mail_threads      → epesi_mail_threads
 *   rc_mails             → epesi_mails; its Contacts and Related multiselects
 *                          become one polymorphic epesi_mail_links table
 *   rc_mails_attachments → epesi_mail_attachments (files in the file storage since 2026_09_27_020000)
 *   rc_multiple_emails   → epesi_mail_addresses
 *
 * Account passwords are encrypted at rest with the application key (Laravel's
 * `encrypted` cast), where Epesi used a module key file with AES-256-GCM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_mail_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 64);
            $table->string('email', 128);
            $table->string('from_name')->nullable();

            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->nullable();
            $table->string('imap_security', 8)->default('ssl');
            $table->string('imap_login')->nullable();
            $table->text('imap_password')->nullable();

            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_security', 8)->default('tls');
            $table->boolean('smtp_auth')->default(true);
            $table->string('smtp_login')->nullable();
            $table->text('smtp_password')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('archive_on_sending')->default(true);
            $table->string('archive_folder')->nullable()->default('CRM Archive');
            $table->boolean('auto_archive')->default(false);
            $table->json('auto_archive_folders')->nullable();
            $table->text('signature')->nullable();

            $table->timestamp('last_fetched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('epesi_mail_account_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('epesi_mail_accounts')->cascadeOnDelete();
            $table->string('folder');
            $table->unsignedBigInteger('uid_validity')->nullable();
            $table->unsignedBigInteger('last_uid')->default(0);
            $table->timestamps();

            $table->unique(['account_id', 'folder']);
        });

        Schema::create('epesi_mail_threads', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 512)->nullable();
            $table->dateTime('first_date')->nullable();
            $table->dateTime('last_date')->nullable();
            $table->unsignedInteger('message_count')->default(0);
            $table->timestamps();
        });

        Schema::create('epesi_mails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->nullable()->constrained('epesi_mail_threads')->nullOnDelete();
            $table->string('message_id', 512)->nullable()->index();
            $table->string('in_reply_to', 512)->nullable();
            $table->text('references')->nullable();
            $table->string('subject', 512)->nullable();
            $table->text('from')->nullable();
            $table->text('to')->nullable();
            $table->text('cc')->nullable();
            $table->dateTime('date')->nullable()->index();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('headers')->nullable();
            $table->string('direction', 8)->nullable();
            $table->foreignId('employee_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('epesi_mail_accounts')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('epesi_mail_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_id')->constrained('epesi_mails')->cascadeOnDelete();
            $table->morphs('linkable');
            $table->timestamps();

            $table->unique(['mail_id', 'linkable_type', 'linkable_id'], 'epesi_mail_links_unique');
        });

        Schema::create('epesi_mail_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_id')->constrained('epesi_mails')->cascadeOnDelete();
            $table->string('name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('content_id')->nullable();
            $table->boolean('inline')->default(false);
            $table->string('path');
            $table->timestamps();
        });

        Schema::create('epesi_mail_addresses', function (Blueprint $table) {
            $table->id();
            $table->morphs('addressable');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_mail_addresses');
        Schema::dropIfExists('epesi_mail_attachments');
        Schema::dropIfExists('epesi_mail_links');
        Schema::dropIfExists('epesi_mails');
        Schema::dropIfExists('epesi_mail_threads');
        Schema::dropIfExists('epesi_mail_account_folders');
        Schema::dropIfExists('epesi_mail_accounts');
    }
};
