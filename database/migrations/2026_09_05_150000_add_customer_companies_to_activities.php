<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the Company side of Epesi's Company-or-Contact Customer field,
     * previously dropped (see create_tasks_table/create_meetings_table/
     * create_phone_calls_table's docblocks) since a true dual-polymorphic
     * relationship has no clean Filament form field to hang off it. Kept as
     * a second, parallel field instead: Task/Meeting keep their existing
     * Contact-only `customers` multiselect and gain a `customerCompanies`
     * one; PhoneCall keeps its existing singular `contact_id` and gains a
     * singular `company_id`. Neither pairing is mutually exclusive at the
     * schema level — both may be set on the same record.
     */
    public function up(): void
    {
        Schema::create('task_customer_company', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'company_id']);
        });

        Schema::create('meeting_customer_company', function (Blueprint $table) {
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->primary(['meeting_id', 'company_id']);
        });

        Schema::table('phone_calls', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('phone_calls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('meeting_customer_company');
        Schema::dropIfExists('task_customer_company');
    }
};
