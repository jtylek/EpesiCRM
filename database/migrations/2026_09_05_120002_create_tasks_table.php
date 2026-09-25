<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Port of Epesi's CRM_Tasks "task" recordset (CRM_TasksInstall::install()).
     * See the phone_calls migration's docblock for what's deliberately not
     * ported (Related links, Watchdog, Calendar handler) and why Customers is
     * a plain Contact multiselect rather than Epesi's Company-or-Contact type.
     * `longterm` was ported here but dropped in
     * 2026_09_05_212618_drop_longterm_from_tasks_table.php (Jasiek: won't be
     * used) — left in this file rather than edited out, since it did exist
     * and this migration already ran; TasksImporter ignores the legacy field.
     */
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedTinyInteger('priority')->default(1);
            $table->unsignedTinyInteger('permission')->default(0);
            $table->boolean('longterm')->default(false);
            $table->dateTime('deadline')->nullable();
            $table->boolean('timeless')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_employee', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'contact_id']);
        });

        Schema::create('task_customer', function (Blueprint $table) {
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->primary(['task_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_customer');
        Schema::dropIfExists('task_employee');
        Schema::dropIfExists('tasks');
    }
};
