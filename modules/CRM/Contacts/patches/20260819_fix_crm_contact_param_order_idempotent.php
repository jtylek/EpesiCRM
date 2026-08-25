<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// patches/20260813_fix_crm_contact_param_order.php swapped segments 1 and 2 of
// every crm_contact-typed field's stored param unconditionally, assuming every
// such field still held the pre-fix "format;crits" order. That's only true for
// fields created before the crm_contact_datatype() fix in that same commit - a
// field created *after* the fix (e.g. a Premium module (re)installed the same
// day, before that patch got a chance to run) was already stored correctly as
// "crits;format", and the blind swap flipped it back into the broken order,
// reproducing the exact TypeError (no_wrap() fed a plain crits array instead of
// a formatted string) the original patch was meant to eliminate - reproduced
// live by premium_salesopportunity's Opportunity Manager/Employees and
// premium_listmanager's List Maintainers fields.
//
// This patch is idempotent: instead of assuming a direction, it inspects the
// callback names actually stored (every crits callback in this codebase has
// "crits" in its name, e.g. employees_crits/salesopportunity_employees_crits;
// no format callback does, e.g. contact_format_no_company) and swaps only a
// field that is currently wrong - self-correcting regardless of which patches
// already ran or what order a field happens to be in.
class CRM_Contacts_Patch_FixContactParamOrderIdempotent
{
    protected function execute()
    {
        $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
        $checkpoint = Patch::checkpoint('recordsets');
        if ($checkpoint->get('log_info', true)) {
            $this->log('This log stores the execution log for patch: modules/CRM/Contacts/patches/20260819_fix_crm_contact_param_order_idempotent.php');
            $this->log('');
            $checkpoint->set('log_info', false);
        }
        $processed = $checkpoint->get('processed', array());
        foreach ($recordsets as $tab => $caption) {
            if (isset($processed[$tab])) {
                continue;
            }
            $this->process_recordset($tab);
            $processed[$tab] = true;
            $checkpoint->set('processed', $processed);
        }
    }

    public static function run()
    {
        $x = new self();
        $x->execute();
    }

    protected function process_recordset($tab)
    {
        Patch::require_time(1);
        $all_fields = Utils_RecordBrowserCommon::init($tab, true, true);
        $fields = DB::GetCol("SELECT field FROM {$tab}_callback WHERE callback=%s", array('CRM_ContactsCommon::QFfield_contact'));
        foreach ($fields as $f) {
            if (!isset($all_fields[$f])) {
                continue;
            }
            $cp = Patch::checkpoint('RS_' . $tab . '_' . $f);
            if ($cp->is_done()) continue;
            Patch::require_time(1);

            $param = $all_fields[$f]['param'];
            $parts = explode(';', $param);
            if (count($parts) >= 3 && str_contains($parts[2], 'crits') && !str_contains($parts[1], 'crits')) {
                $tmp = $parts[1];
                $parts[1] = $parts[2];
                $parts[2] = $tmp;
                $new_param = implode(';', array_slice($parts, 0, 3));
                $this->log("FIELD tab={$tab}, field={$f}: '{$param}' -> '{$new_param}'");
                DB::Execute("UPDATE {$tab}_field SET param=%s WHERE field=%s", array($new_param, $f));
            } else {
                $this->log("FIELD tab={$tab}, field={$f}: param '{$param}' already in crits;format order (or unrecognized), skipped");
            }
            $cp->done();
        }
    }

    protected function log($msg)
    {
        $msg .= "\n";
        epesi_log($msg, 'crm_contact_param_order.log');
    }
}

CRM_Contacts_Patch_FixContactParamOrderIdempotent::run();
