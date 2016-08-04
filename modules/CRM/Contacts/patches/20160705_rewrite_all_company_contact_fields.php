<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_Contacts_Patch_RewriteCompanyContact
{

    protected function execute()
    {
        $recordsets = Utils_RecordBrowserCommon::list_installed_recordsets();
        $checkpoint = Patch::checkpoint('recordsets');
        if ($checkpoint->get('log_info', true)) {
            $this->log('This log stores the execution log for patch: modules/CRM/Contacts/patches/20160705_rewrite_all_company_contact_fields.php');
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
        $fields = DB::GetCol("SELECT field FROM {$tab}_callback WHERE callback=%s", array("CRM_ContactsCommon::QFfield_company_contact"));
        foreach ($fields as $f) {
            if (!isset($all_fields[$f])) {
                continue;
            }
            $cp = Patch::checkpoint("RS_" . $tab);
            if ($cp->is_done()) continue;
            $field_id = $all_fields[$f]['id'];
            $field_type = $all_fields[$f]['type'];
            // REPLACE works for both: mysql and postgresql
            $this->log("FIELD tab={$tab}, field={$field_id}, type={$field_type}");
            Patch::require_time(5);
            if ($cp->get('contact', false) == false) {
                $this->log("   Replace P: to contact/");
                DB::Execute("UPDATE {$tab}_data_1 SET f_{$field_id}=REPLACE(f_{$field_id},%s,%s)", array('P:','contact/'));
                $cp->set('contact', true);
            }
            Patch::require_time(5);
            if ($cp->get('company', false) == false) {
                $this->log("   Replace C: to company/");
                DB::Execute("UPDATE {$tab}_data_1 SET f_{$field_id}=REPLACE(f_{$field_id},%s,%s)", array('C:', 'company/'));
                $cp->set('company', true);
            }
            $new_param = 'contact,company::';
            if ($field_type != 'multiselect') $field_type = 'select';
            $this->log("   UPDATE param=$new_param, type=$field_type");
            DB::Execute("UPDATE {$tab}_field SET param=%s,type=%s WHERE field=%s", array($new_param, $field_type, $f));
            $this->log("   DELETE QFfield callback");
            Utils_RecordBrowserCommon::unset_QFfield_callback($tab, $f);
            $cp->done();
        }
    }

    protected function log($msg)
    {
        $msg .= "\n";
        epesi_log($msg, 'company_contact.log');
    }
}

CRM_Contacts_Patch_RewriteCompanyContact::run();
