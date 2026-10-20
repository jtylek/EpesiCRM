<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Epesi's epesi_archive saved Roundcube's placeholder ("mid:4", the message's
 * position in its folder) as the Message-ID of some mail, and the import
 * carried it over. The Archive button then took any message at that position
 * for this one ("Message already archived"). Mail without a Message-ID is
 * matched by sender, recipients, subject and date (AI-shared/Mail-archiving.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('epesi_mails')->where('message_id', 'like', 'mid:%')->update(['message_id' => null]);
    }

    public function down(): void
    {
        // The placeholders meant nothing; there is nothing to restore.
    }
};
