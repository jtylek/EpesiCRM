<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Port of Epesi's CRM_Meeting "crm_meeting" recordset (CRM_MeetingInstall::install()).
     * See the phone_calls migration's docblock for what's deliberately not
     * ported. Epesi's recurrence fields (Recurrence type/end/hash, which drive
     * a repeat-this-meeting UI) are dropped too — meetings here are single
     * events, a disclosed gap rather than an attempt at the full RRULE-style
     * recurrence machinery.
     */
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('date');
            $table->time('time');
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->unsignedTinyInteger('permission')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meeting_employee', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'contact_id']);
        });

        Schema::create('meeting_customer', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_customer');
        Schema::dropIfExists('meeting_employee');
        Schema::dropIfExists('meetings');
    }
};
