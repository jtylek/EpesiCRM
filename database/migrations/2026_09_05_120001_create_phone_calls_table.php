<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Port of Epesi's CRM_PhoneCall "phonecall" recordset (CRM_PhoneCallInstall::install()).
     *
     * Simplified from the source: Epesi's Customer field is a "crm_company_contact"
     * type (pick a whole Company or one Contact) with a chained phone-number
     * select driven off whichever is picked; here Customer is a plain Contact
     * FK and the phone number is a single free-text field — a real, disclosed
     * gap, not an oversight, since a polymorphic Company-or-Contact selection
     * has no clean Filament relationship to hang a form field off. Epesi's
     * "Related" cross-record links, Watchdog notify-on-change subscriptions,
     * and the Calendar-module event handler are not ported either — none of
     * those source modules exist in this port yet.
     */
    public function up(): void
    {
        Schema::create('phone_calls', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('other_customer')->default(false);
            $table->string('other_customer_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->unsignedTinyInteger('permission')->default(0);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->dateTime('called_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('phone_call_employee', function (Blueprint $table) {
            $table->foreignId('phone_call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['phone_call_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_call_employee');
        Schema::dropIfExists('phone_calls');
    }
};
