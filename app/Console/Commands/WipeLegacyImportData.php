<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The wipe that comes before the real `import:legacy all`: every table an
 * importer fills, so record ids come out the same as in the legacy database
 * (a row made locally takes an id the import then cannot use).
 *
 * A dry run by default. It runs only with --force, and then only when
 * --confirm names this database and --backup points at a dump made in the
 * last day: the dump is the one way back once the tables are truncated.
 *
 * It leaves settings alone (modules, Dashboard, roles, regional settings,
 * login audits, common data, custom field definitions, currencies and their
 * rates, Roundcube's own rc_* tables, the Shoutbox). The files the wiped tables held are
 * pruned afterwards (filestorage:prune), so the import doesn't stack a second copy of every
 * row on them. Tables a
 * module doesn't have on this install are skipped. A table that holds a
 * legacy import but isn't listed here is a bug: add it, with the importer.
 */
class WipeLegacyImportData extends Command
{
    protected $signature = 'import:legacy:wipe
        {--force : actually wipe; without it only the counts are shown}
        {--confirm= : the name of this database, to confirm it is the one to wipe}
        {--backup= : path of a dump of this database made within the last 24 hours}';

    protected $description = 'Wipe the CRM, mail, accounting and ledger data before a legacy cutover import (dry run unless --force)';

    /** @var list<string> */
    public const TABLES = [
        // Core CRM and its pivots
        'companies', 'contacts', 'tasks', 'meetings', 'phone_calls',
        'company_contact', 'task_customer', 'task_customer_company', 'task_employee',
        'meeting_customer', 'meeting_customer_company', 'meeting_employee', 'phone_call_employee',
        // RecordBrowser collections of those records
        'epesi_recordbrowser_addresses', 'epesi_recordbrowser_email_addresses', 'epesi_recordbrowser_phone_numbers',
        'epesi_recordbrowser_links', 'epesi_recordbrowser_online_accounts',
        // Mail
        'epesi_mails', 'epesi_mail_accounts', 'epesi_mail_threads', 'epesi_mail_addresses',
        'epesi_mail_attachments', 'epesi_mail_links', 'epesi_mail_account_folders', 'epesi_roundcube_tickets',
        // Projects, tickets, sales opportunities, timesheets
        'premium_projectstickets_projects', 'premium_projectstickets_project_employees',
        'premium_projectstickets_tickets', 'premium_projectstickets_ticket_assignees', 'premium_projectstickets_ticket_required',
        'premium_salesopportunity_opportunities', 'premium_salesopportunity_opportunity_employees',
        'premium_timesheets_timesheets', 'premium_timesheets_rates', 'premium_timesheets_rate_employees',
        // Attachments
        'epesi_attachments', 'epesi_attachment_links',
        // Accounting documents, payments and the books opened from them (expense categories and
        // reusable lines are settings, not imported data)
        'premium_accounting_invoices', 'premium_accounting_invoice_items', 'premium_accounting_expenses', 'premium_accounting_expense_items',
        'premium_accounting_payments', 'premium_accounting_bank_accounts', 'premium_accounting_numbering_series',
        'premium_accounting_opening_balances', 'premium_accounting_ledger_links',
        // Tax rates (core): the import brings them back with the legacy ids the lines above point at
        'tax_rates',
        // The ledger (eloquent-ifrs): entries point at the documents above, so it goes with them
        'ifrs_applied_vats', 'ifrs_assignments', 'ifrs_balances', 'ifrs_closing_rates', 'ifrs_closing_transactions',
        'ifrs_exchange_rates', 'ifrs_ledgers', 'ifrs_line_items', 'ifrs_recycled_objects', 'ifrs_transactions',
        'ifrs_vats', 'ifrs_categories', 'ifrs_accounts', 'ifrs_reporting_periods', 'ifrs_currencies', 'ifrs_entities',
        // Record-scoped convenience data that goes stale when ids change (category subscriptions aren't)
        'epesi_recordbrowser_favorites', 'epesi_recordbrowser_recent', 'epesi_watchdog_subscriptions',
        'epesi_reminders', 'epesi_reminder_recipients', 'epesi_priority_list_entries',
    ];

    /**
     * Record history. Not a blanket truncate: module, user and custom field
     * history is infrastructure, not data the import brings back.
     *
     * @var list<string>
     */
    public const HISTORY_SUBJECTS = [
        'company', 'contact', 'task', 'meeting', 'phone_call', 'mail', 'attachment',
        'premium_projectstickets_project', 'premium_projectstickets_ticket', 'premium_salesopportunity_opportunity', 'premium_timesheets_timesheet', 'premium_timesheets_timesheet_rate',
        'premium_accounting_invoice', 'premium_accounting_invoice_item', 'premium_accounting_expense', 'premium_accounting_expense_item',
        'premium_accounting_payment', 'premium_accounting_bank_account', 'premium_accounting_numbering_series',
        'premium_accounting_opening_balance', 'tax_rate',
    ];

    public function handle(): int
    {
        $database = DB::connection()->getDatabaseName();
        $tables = array_values(array_filter(self::TABLES, fn (string $table): bool => Schema::hasTable($table)));
        $history = Schema::hasTable('activity_log') ? DB::table('activity_log')->whereIn('subject_type', self::HISTORY_SUBJECTS)->count() : 0;

        $this->line("Database: {$database}");
        $this->table(['Table', 'Rows'], collect($tables)->map(fn (string $table): array => [$table, DB::table($table)->count()])->all());
        $this->line("activity_log rows of those records: {$history}");

        if (! $this->option('force')) {
            $this->info('Dry run: nothing was changed. Back the database up, then rerun with --force --confirm='.$database.' --backup=<dump.sql>.');

            return self::SUCCESS;
        }

        if ($this->option('confirm') !== $database) {
            $this->error("--confirm must be the name of this database ({$database}).");

            return self::FAILURE;
        }

        $backup = (string) $this->option('backup');
        clearstatcache(true, $backup);

        if ($backup === '' || ! is_file($backup) || filesize($backup) === 0 || filemtime($backup) < time() - 86400) {
            $this->error('--backup must be a non-empty dump made within the last 24 hours: it is the only way back.');

            return self::FAILURE;
        }

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }

            if ($history > 0) {
                DB::table('activity_log')->whereIn('subject_type', self::HISTORY_SUBJECTS)->delete();
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->call('filestorage:prune', ['--force' => true, '--older-than' => 0]);

        $this->info('Wiped '.count($tables)." tables and {$history} history rows. Now: php artisan import:legacy all");

        return self::SUCCESS;
    }
}
