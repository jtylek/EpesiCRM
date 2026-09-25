<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field definitions an administrator added from the GUI — the port of Epesi's
 * per-recordset `<table>_field` table, kept as one shared table instead.
 *
 * Epesi needs a `<tab>_field` table per recordset because it creates each
 * recordset's storage from nothing, so the metadata naturally lives beside it.
 * Here the models and their tables already exist, so one table keyed by
 * `model_type` is simpler and keeps every definition queryable in one place.
 *
 * **This table is the source of truth; the schema is derived from it.** Each row
 * owns one real column on the model's own table (`cf_<id>`, added by
 * CustomFieldSchema), which is what makes an administrator's field behave like a
 * shipped one everywhere — Eloquent sees an ordinary attribute, Filament sorts,
 * searches and filters it with no special-casing, and raw SQL and reporting see
 * it too. `recordbrowser:customfields-sync` rebuilds the columns from these rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            // Morph alias, not an FQCN — a model has to stay movable between
            // namespaces without orphaning its field definitions.
            $table->string('model_type', 64);

            // "cf_17", generated from this row's own id after insert. Named
            // after the id rather than the name so a field can be renamed
            // without touching data, an admin-chosen name can never collide,
            // and no user-supplied string ever reaches DDL. Epesi's `f_<id>`,
            // with a prefix reserved to keep module migrations clear of it.
            //
            // Nullable only because the id it is derived from doesn't exist
            // until the row is inserted — CustomField's `created` hook fills it
            // in immediately, and deletes the row if the column can't be built.
            $table->string('column', 32)->nullable();

            // Stable slug for code and imports; `label` is what the admin typed
            // and can be renamed freely.
            $table->string('name', 64);
            $table->string('label');

            $table->string('type', 32);
            $table->json('params')->nullable();

            // Enforced in validation, never in the schema: a column added to a
            // table that already has rows cannot be NOT NULL. Epesi does the
            // same.
            $table->boolean('required')->default(false);

            $table->integer('position')->default(0);

            // Epesi's page_split, as a property of the field.
            $table->string('section')->nullable();

            $table->boolean('show_in_table')->default(false);
            $table->boolean('show_in_view')->default(true);
            $table->boolean('show_in_form')->default(true);
            $table->boolean('filterable')->default(false);
            $table->boolean('exportable')->default(true);
            $table->text('help')->nullable();

            // How a field is removed by default — the column and its data stay.
            // Dropping the column is a separate, explicitly confirmed action,
            // for the same reason uninstalling a module leaves its tables alone.
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['model_type', 'name']);
            $table->unique(['model_type', 'column']);
            $table->index(['model_type', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
