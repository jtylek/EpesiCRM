<?php
/**
 * Filestorage
 *
 * @author      Janusz Tylek <j@epe.si>
 * @copyright  Janusz Tylek
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
            $filename = Utils_FileStorageCommon::get_file_label($r['id']);
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

    /*
    * Display history of views, downloads and remote access for record's file field's file.
    *
    * @param integer $filestorageId is the record's file field's file id (`utils_filestorage`.`id`)
    *
    * @return nothing
    */
    public static function getHistory($filestorageId)
    {
        Base_BoxCommon::push_module('Utils_FileStorage','file_history',[$filestorageId]);
    }

    public function file_history($id)
    {
        if($this->is_back()) {
            return Base_BoxCommon::pop_main();
        }

        Base_ActionBarCommon::add('back',__('Back'),$this->create_back_href());

        $file_leightbox_href = array();
        $file = '';

        $tb = $this->init_module(Utils_TabbedBrowser::module_name());
        $tb->start_tab('File history');
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'hua'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
            array('name'=>__('Deleted'), 'order'=>'deleted','width'=>10),
            array('name'=>__('Date'), 'order'=>'upload_on','width'=>25),
            array('name'=>__('Who'), 'order'=>'upload_by','width'=>25),
            array('name'=>__('File'), 'order'=>'uaf.original')
        ));
        $gb->set_default_order(array(__('Date')=>'DESC'));

        $file_history = DB::GetAssoc('SELECT * FROM utils_filestorage WHERE id=%d',[$id]);
        foreach($file_history as $col) {
            $r = $gb->get_new_row();
            $meta = is_numeric($id) ? Utils_FileStorageCommon::meta($id) : $id;
            $acion_handler = new Utils_FileStorage_ActionHandler();
            $action_urls = $acion_handler->getActionUrls($meta['id']);
            $file_leightbox_href[$col['id']] = Utils_FileStorage_FileLeightbox::get_file_leightbox($meta, $action_urls, true);
            $file = '<a '.$file_leightbox_href[$col['id']].'>'.$col['filename'].'</a>';
            $r->add_data($col['deleted']?__('Yes'):__('No'),Base_RegionalSettingsCommon::time2reg($col['created_on']),Base_UserCommon::get_user_label($col['created_by']),$file);
        }
        $this->display_module($gb);
        $tb->end_tab();
        $tb->start_tab('File access history');
        $gb = $this->init_module(Utils_GenericBrowser::module_name(),null,'hda'.$id);
        $gb->set_inline_display();
        $gb->set_table_columns(array(
            array('name'=>__('File'), 'order'=>'original','width'=>15),
            array('name'=>__('Download Date'), 'order'=>'download_on','width'=>15),
            array('name'=>__('Who'), 'order'=>'created_by','width'=>15),
            array('name'=>__('IP Address'), 'order'=>'ip_address', 'width'=>15),
            array('name'=>__('Host Name'), 'order'=>'host_name', 'width'=>15),
            array('name'=>__('Accessed Using'), 'order'=>'description', 'width'=>20),
        ));
        $gb->set_default_order(array(__('Download Date')=>'DESC'));

        $file_access_history = DB::GetAssoc('SELECT * FROM utils_filestorage_access WHERE file_id=%d',[$id]);
        foreach($file_access_history as $col) {
            $r = $gb->get_new_row();
            $r->add_data($file,Base_RegionalSettingsCommon::time2reg($col['date_accessed']),Base_UserCommon::get_user_label($col['accessed_by']), $col['ip_address'], $col['host_name'],array_flip(Utils_FileStorage_ActionHandler::actions)[$col['type']]);
        }
        $this->display_module($gb);
        $tb->end_tab();
        $this->display_module($tb);

        $this->caption = 'File history';

        return true;
    }
}