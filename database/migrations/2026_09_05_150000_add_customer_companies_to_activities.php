<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the Company side of Epesi's Company-or-Contact Customer field,
     * previously dropped (see create_tasks_table/create_meetings_table's
     * docblocks) since a true dual-polymorphic relationship had no clean
     * Filament form field to hang off it — Field::customer() (see the
     * create_phone_calls_table migration) closes that gap, but only
     * PhoneCall has been moved onto it so far. Task/Meeting keep their
     * existing Contact-only `customers` multiselect and gain a
     * `customerCompanies` one instead, as a second, parallel field; neither
     * pairing is mutually exclusive at the schema level — both may be set
     * on the same record.
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
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_customer_company');
        Schema::dropIfExists('task_customer_company');
    }
};
