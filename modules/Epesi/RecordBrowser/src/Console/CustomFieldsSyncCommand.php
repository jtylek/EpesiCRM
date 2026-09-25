<?php

namespace Epesi\Modules\RecordBrowser\Console;

use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldRegistry;
use Epesi\Modules\RecordBrowser\CustomFields\CustomFieldSchema;
use Illuminate\Console\Command;

/**
 * Rebuilds the columns behind the `custom_fields` definitions.
 *
 * The definitions table is the source of truth and the schema is derived from
 * it — that is what makes runtime DDL acceptable. This is the command that
 * makes the claim true: the repair path when a column is missing, and how a set
 * of definitions copied to a second install materialises there.
 */
class CustomFieldsSyncCommand extends Command
{
    protected $signature = 'customfields:sync';

    protected $description = 'Create any column a custom-field definition needs but the database is missing';

    public function handle(CustomFieldSchema $schema): int
    {
        $created = $schema->sync();

        CustomFieldRegistry::refresh();

        if ($created === []) {
            $this->components->info('Every custom field already has its column.');

            return self::SUCCESS;
        }

        foreach ($created as $description) {
            $this->components->twoColumnDetail($description, '<fg=green>created</>');
        }

        $this->components->info(count($created).' column(s) created.');

        return self::SUCCESS;
    }
}
