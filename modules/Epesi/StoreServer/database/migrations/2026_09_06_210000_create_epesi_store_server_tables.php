<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epesi_store_server_products', function (Blueprint $table) {
            $table->id();
            $table->string('module_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('price_amount')->nullable();
            $table->string('price_currency', 3)->default('USD');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('epesi_store_server_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('epesi_store_server_products')->cascadeOnDelete();
            $table->string('version');
            $table->string('epesi_core')->default('*');
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->text('changelog')->nullable();
            $table->string('file_path');
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'version']);
        });

        Schema::create('epesi_store_server_licences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('epesi_store_server_products')->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('purchaser_name')->nullable();
            $table->string('purchaser_email')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epesi_store_server_licences');
        Schema::dropIfExists('epesi_store_server_releases');
        Schema::dropIfExists('epesi_store_server_products');
    }
};
