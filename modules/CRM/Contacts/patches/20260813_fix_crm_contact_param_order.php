<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

// CRM_ContactsCommon::crm_contact_datatype() used to encode a crm_contact field's
// select param as "<tab>::<cols>;<format callback>;<crits callback>", but every real
// consumer (QFfield_contact/display_contact/display_contacts_with_notification, and
// the generic RecordBrowser Filters machinery via decode_select_param()) expects
// "<tab>::<cols>;<crits callback>;<format callback>" - the two were reversed, papered
// over for one narrow case by a late/fragile swap in Filters_0.php. The encoder and
// its consumers have been fixed to agree on the correct order, but that only affects
// what a *future* install/upgrade writes - it does not retroactively fix the 'param'
// string already stored in every existing crm_contact-type field's *_field row. This
// patch swaps segments 1 and 2 of that stored string for every such field, across
// every installed RecordBrowser table (core and Premium alike).
class CRM_Contacts_Patch_FixContactParamOrder
{
    protected function execute()
    {
        $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
        $checkpoint = Patch::checkpoint('recordsets');
        if ($checkpoint->get('log_info', true)) {
            $this->log('This log stores the execution log for patch: modules/CRM/Contacts/patches/20260813_fix_crm_contact_param_order.php');
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
            if (count($parts) >= 3) {
                // segment 1 was the format callback, segment 2 the crits callback -
                // swap to match the corrected crm_contact_datatype() encoding.
                $tmp = $parts[1];
                $parts[1] = $parts[2];
                $parts[2] = $tmp;
                $new_param = implode(';', array_slice($parts, 0, 3));
                $this->log("FIELD tab={$tab}, field={$f}: '{$param}' -> '{$new_param}'");
                DB::Execute("UPDATE {$tab}_field SET param=%s WHERE field=%s", array($new_param, $f));
            } else {
                $this->log("FIELD tab={$tab}, field={$f}: param '{$param}' has fewer than 3 segments, skipped");
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

CRM_Contacts_Patch_FixContactParamOrder::run();
