<?php
/**
 * Regression guard for the N+1 fixes in AI-shared/REFERENCE-optimization-opus-AI.md (item 2.4).
 *
 * It measures *slope*, not a fixed budget. Each scenario runs twice - once over a small
 * set of records, once over a set five times larger - and the invariant asserted is that
 * the query count does not grow with the number of records. That is the property every
 * Tier 1 fix actually established, and it is the property a later edit ("just call
 * get_record() here, it's cached") silently destroys.
 *
 * A fixed budget was the obvious first design and it was the wrong one: the honest count
 * for a cold scenario includes one-off schema/metadata reads (RecordBrowser's _field and
 * _callback lookups, Watchdog's category id) that are memoized per request and have
 * nothing to do with row count. Picking a number big enough to cover those is picking a
 * number big enough to hide a real per-row query on a small fixture. So each scenario is
 * warmed once, discarded, and only the row-dependent part is measured.
 *
 * What this does NOT cover: a whole page. Epesi renders through Epesi::process(), driven
 * by module-tree state that lives in a browser session, so a faithful page render needs a
 * browser. The page-level procedure stays the manual one in section 7 of the plan ("How
 * these numbers were taken") - minus the config.php edit it used to require, since a
 * super-admin can now switch the SQL panel on for their own session (item 2.3,
 * include/profiling.php).
 *
 *     php console.php dev:query:budget
 *     php console.php dev:query:budget -v      # list every measured query
 *
 * Exit code is non-zero when any scenario's query count grows with the row count, so it
 * works as a pre-push check. It needs a populated database, which is why it is a local
 * command and not a CI job - see AI-shared/REFERENCE-optimization-opus-AI.md section 10.
 */
namespace Epesi\Console\Develop;

use Acl;
use CRM_ContactsCommon;
use CRM_RoundcubeCommon;
use DB;
use Profiling;
use Utils_RecordBrowserCommon;
use Utils_WatchdogCommon;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class QueryBudgetCommand extends Command
{
    /** Row counts for the two passes. The gap has to be wide enough that a per-row query
     *  is unmistakable and narrow enough that a small dev fixture still has the records. */
    const ROWS_SMALL = 5;
    const ROWS_LARGE = 25;

    protected function configure() {
        $this
            ->setName('dev:query:budget')
            ->setDescription('Assert that per-row query counts stay flat as row count grows');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $verbose = $output->isVerbose();
        $failed = 0;
        $skipped = 0;
        $ran = 0;

        foreach ($this->scenarios() as $name => $s) {
            $small = call_user_func($s['ids'], self::ROWS_SMALL);
            $large = call_user_func($s['ids'], self::ROWS_LARGE);
            if ($small === null || $large === null || count($large) <= count($small)) {
                $output->writeln(sprintf('  <comment>SKIP</comment>  %-44s not enough fixture data, or module not installed', $name));
                $skipped++;
                continue;
            }

            // Warm every per-request static the scenario touches, so the two measurements
            // below differ only in how many records they handle.
            $this->measure($s['run'], $small);

            $qs = $this->measure($s['run'], $small);
            $ql = $this->measure($s['run'], $large);
            $ran++;

            $flat = count($ql) <= count($qs);
            if (!$flat) $failed++;
            $output->writeln(sprintf(
                '  %s  %-44s %d rows: %d queries   %d rows: %d queries',
                $flat ? '<info>FLAT</info>' : '<error>N+1 </error>', $name,
                count($small), count($qs), count($large), count($ql)
            ));
            if (!$flat || $verbose) {
                foreach ($ql as $q) {
                    $output->writeln('        '.$q['func'].' '.$this->sql_of($q).(isset($q['caller']) ? '   <comment>'.$q['caller'].'</comment>' : ''));
                }
            }
        }

        $output->writeln('');
        if ($failed) {
            $output->writeln(sprintf('<error>%d scenario(s) scale with row count.</error> A per-row query came back - see AI-shared/REFERENCE-optimization-opus-AI.md section A1.', $failed));
            return Command::FAILURE;
        }
        $output->writeln(sprintf('<info>All %d scenario(s) flat.</info>%s', $ran, $skipped ? " $skipped skipped." : ''));
        return Command::SUCCESS;
    }

    /**
     * Run one scenario with the record caches emptied and the SQL log on, and hand back
     * exactly the queries it caused. Profiling::$sql is the runtime switch added for item
     * 2.3 - before it, this command would have had to ask the operator to edit
     * data/config.php first, then remember to put it back.
     */
    private function measure(callable $run, array $ids) {
        $this->reset_record_caches();
        $was = Profiling::$sql;
        Profiling::$sql = true;
        $before = count(DB::GetQueries());
        try {
            call_user_func($run, $ids);
        } finally {
            Profiling::$sql = $was;
        }
        return array_slice(DB::GetQueries(), $before);
    }

    /**
     * Empty the per-record memoization, and only that. Schema-level caches (init()'s field
     * definitions, the display-callback table, Watchdog's category ids) are deliberately
     * left warm: they are per-request and row-count-independent, so including them would
     * add the same constant to both passes and only blur the comparison.
     */
    private function reset_record_caches() {
        $rb = new ReflectionClass('Utils_RecordBrowserCommon');
        foreach (array('record_cache', 'record_info_cache') as $prop) {
            $p = $rb->getProperty($prop);
            $p->setAccessible(true);
            $p->setValue(null, array());
        }
    }

    private function sql_of($q) {
        $sql = is_array($q['args']) ? ($q['args'][0] ?? '') : $q['args'];
        if (!is_string($sql)) $sql = '';
        return substr(preg_replace('/\s+/', ' ', $sql), 0, 110);
    }

    private function ids_from($tab, $limit) {
        if (!DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', array($tab))) return null;
        $ids = DB::GetCol('SELECT id FROM '.$tab.'_data_1 ORDER BY id LIMIT '.((int) $limit));
        return count($ids) >= 2 ? $ids : null;
    }

    private function scenarios() {
        return array(
            // A1.2 - RecordBrowser_0::add_info() calls get_record_info() once per row.
            'A1.2 get_record_info() over a grid page' => array(
                'ids' => fn($n) => $this->ids_from('contact', $n),
                'run' => function ($ids) {
                    Utils_RecordBrowserCommon::prefetch_record_info('contact', $ids);
                    foreach ($ids as $id) Utils_RecordBrowserCommon::get_record_info('contact', $id);
                },
            ),
            // A1.4 - a linked column (Contacts' Company) resolving one record per row.
            'A1.4 get_company() over a grid page' => array(
                'ids' => fn($n) => $this->ids_from('company', $n),
                'run' => function ($ids) {
                    Utils_RecordBrowserCommon::prefetch_records('company', $ids);
                    foreach ($ids as $id) CRM_ContactsCommon::get_company($id);
                },
            ),
            // The same invariant one level down, for any recordset - this is the one a new
            // module gets for free, so it is worth guarding on its own.
            'A1.4 get_record() over a grid page' => array(
                'ids' => fn($n) => $this->ids_from('contact', $n),
                'run' => function ($ids) {
                    Utils_RecordBrowserCommon::prefetch_records('contact', $ids);
                    foreach ($ids as $id) Utils_RecordBrowserCommon::get_record('contact', $id);
                },
            ),
            // A1.1 - the mail-account check behind every email cell. Reached through
            // get_mailto_link(), the public caller; the memoized helper itself is private.
            'A1.1 Roundcube mailto link per row' => array(
                'ids' => fn($n) => class_exists('CRM_RoundcubeCommon') ? range(1, $n) : null,
                'run' => function ($ids) {
                    foreach ($ids as $i) CRM_RoundcubeCommon::get_mailto_link('row'.$i.'@example.com');
                },
            ),
            // A1.3 - Watchdog's residual MAX(id) per subscribed row.
            'A1.3 Watchdog check_if_notified() per row' => array(
                'ids' => fn($n) => $this->ids_from('contact', $n),
                'run' => function ($ids) {
                    $user = DB::GetOne('SELECT id FROM user_login ORDER BY id LIMIT 1');
                    if (!$user) return;
                    Acl::set_user($user, true);
                    foreach ($ids as $id) Utils_WatchdogCommon::user_check_if_notified($user, 'contact', $id);
                },
            ),
        );
    }
}
