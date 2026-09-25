<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('last_name');
            $table->string('first_name');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->json('groups')->nullable();
            $table->string('title')->nullable();
            $table->string('work_phone')->nullable();
            $table->string('mobile_phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('web_address')->nullable();
            $table->string('address_1')->nullable();
            $table->string('address_2')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('zone')->nullable();
            $table->string('postal_code')->nullable();
            $table->unsignedTinyInteger('permission')->default(0);
            $table->string('home_phone')->nullable();
            $table->string('home_address_1')->nullable();
            $table->string('home_address_2')->nullable();
            $table->string('home_city')->nullable();
            $table->string('home_country')->nullable();
            $table->string('home_zone')->nullable();
            $table->string('home_postal_code')->nullable();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
