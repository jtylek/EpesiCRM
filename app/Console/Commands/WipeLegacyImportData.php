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
 * rates, Roundcube's own rc_* tables, the Shoutbox, stored files). Tables a
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
        'epesi_projects', 'epesi_project_customer_companies', 'epesi_project_customer_contacts', 'epesi_project_employees',
        'epesi_tickets', 'epesi_ticket_assignees', 'epesi_ticket_customer_companies', 'epesi_ticket_customer_contacts', 'epesi_ticket_required',
        'epesi_sales_opportunities', 'epesi_sales_opportunity_employees',
        'epesi_timesheets', 'epesi_timesheet_rates', 'epesi_timesheet_rate_employees',
        // Attachments
        'epesi_attachments', 'epesi_attachment_links',
        // Accounting documents, payments and the books opened from them (expense categories and
        // reusable lines are settings, not imported data)
        'epesi_accounting_invoices', 'epesi_accounting_invoice_items', 'epesi_accounting_expenses', 'epesi_accounting_expense_items',
        'epesi_accounting_payments', 'epesi_accounting_bank_accounts', 'epesi_accounting_numbering_series', 'epesi_accounting_tax_rates',
        'epesi_accounting_opening_balances', 'epesi_accounting_ledger_links',
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
        'project', 'ticket', 'sales_opportunity', 'timesheet', 'timesheet_rate',
        'accounting_invoice', 'accounting_invoice_item', 'accounting_expense', 'accounting_expense_item',
        'accounting_payment', 'accounting_bank_account', 'accounting_numbering_series', 'accounting_tax_rate',
        'accounting_opening_balance',
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

        $this->info('Wiped '.count($tables)." tables and {$history} history rows. Now: php artisan import:legacy all");

        return self::SUCCESS;
    }
}
