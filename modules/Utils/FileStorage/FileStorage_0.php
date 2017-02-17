<?php
/**
 * Filestorage
 *
 * @author     Adam Bukowski <abukowski@telaxus.com>
 * @copyright  Telaxus LLC
 * @license    MIT
 * @version    0.1
 * @package    epesi-Utils
 * @subpackage FileStorage
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Class Utils_FileStorage
 */
class Utils_FileStorage extends Module
{
    public function admin()
    {
        if ($this->back_button()) {
            return;
        }

        /** @var Utils_GenericBrowser $gb */
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'files');
        $cols = [
            ['name' => __('Created on'), 'order' => 'created_on', 'width' => 3],
            ['name' => __('Created by'), 'order' => 'created_by', 'width' => 3],
            ['name' => __('Filename'), 'search' => 'filename', 'order' => 'filename', 'width' => 10],
            ['name' => __('Occurrence'), 'width' => 5],
            ['name' => __('Deleted'), 'search' => 'deleted', 'width' => 1],
            ['name' => __('Link'), 'search' => 'link', 'order' => 'link', 'width' => 5]
        ];
        $gb->set_table_columns($cols);
        $gb->set_default_order([__('Created on') => 'DESC']);

        $sqlPart = $this->get_sql_query($gb);

        foreach (DB::GetAll("SELECT * $sqlPart") as $r) {
            $filename = $r['filename'];
            $created_on = Base_RegionalSettingsCommon::time2reg($r['created_on']);
            $created_by = Base_UserCommon::get_user_label($r['created_by']);
            $deleted = $r['deleted'] ? __('Yes') : __('No');
            $link = $r['link'];
            $backref = $this->create_backref_link($r['backref']);

            $gb->add_row($created_on, $created_by, $filename, $backref, $deleted, $link);
        }

        $this->display_module($gb);
    }

    private function get_sql_query(Utils_GenericBrowser $gb)
    {
        $orderSql = $gb->get_query_order();
        $whereSql = $gb->get_search_query();
        $whereSql = $whereSql ? "WHERE $whereSql" : "";
        $sqlPart = trim("FROM utils_filestorage $whereSql $orderSql");
        $total = DB::GetOne("SELECT count(*) $sqlPart");
        $limit = $gb->get_limit($total);
        $sqlPart .= " LIMIT $limit[numrows] OFFSET $limit[offset]";
        return $sqlPart;
    }

    private function create_backref_link($backref)
    {
        if (substr($backref, 0, 3) == 'rb:') {
            $backref = substr($backref, 3);
        }
        $recordToken = Utils_RecordBrowserCommon::decode_record_token($backref);
        if ($recordToken) {
            return Utils_RecordBrowserCommon::create_default_linked_label($recordToken['tab'], $recordToken['id']);
        }
        return $backref;
    }

    private function back_button()
    {
        if ($this->is_back()) {
            if ($this->parent->get_type() == 'Base_Admin') {
                $this->parent->reset();
            } else {
                location(array());
            }
            return true;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        return false;
    }
}