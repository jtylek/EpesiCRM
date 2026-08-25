<?php
/**
 * Filestorage
 *
 * @author     Adam Bukowski <abukowski@telaxus.com>
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

        // Kept short (2 words) on purpose: .epesi-actionbar-label (Base_ActionBar/
        // theme_adminltedark/default.css) reserves exactly 2 lines at 76px wide with
        // overflow-wrap:anywhere, which breaks wherever it needs to rather than at the
        // '/' - the original "Scan for Orphaned/Missing Files" wrapped to 3 lines with
        // "Missing" itself split mid-word. Full explanation is still on the tooltip.
        Base_ActionBarCommon::add('search', __('Orphaned / Missing'), $this->create_callback_href($this->show_scan_report(...)),
            __('Scan for files whose reference no longer exists (e.g. after uninstalling a module or deleting the record they were attached to), or whose content is missing from disk'));

        $filters = $this->filter_form();

        /** @var Utils_GenericBrowser $gb */
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'files');
        $cols = [
            ['name' => __('Created on'), 'order' => 'created_on', 'width' => 3],
            ['name' => __('Created by'), 'order' => 'created_by', 'width' => 3],
            ['name' => __('Filename'), 'search' => 'filename', 'order' => 'filename', 'width' => 10],
            ['name' => __('Occurrence'), 'width' => 5],
            ['name' => __('Deleted'), 'search' => 'deleted', 'width' => 3],
            // ['name' => __('Link'), 'search' => 'link', 'order' => 'link', 'width' => 5], // rarely populated (see FileStorageCommon_0.php write_metadata()/add_link()) - hidden for now
        ];
        $gb->set_table_columns($cols);
        $gb->set_default_order([__('Created on') => 'DESC']);

        $sqlPart = $this->get_sql_query($gb, $filters);

        foreach (DB::GetAll("SELECT * $sqlPart") as $r) {
            $filename = Utils_FileStorageCommon::get_file_label($r['id']);
            $created_on = Base_RegionalSettingsCommon::time2reg($r['created_on']);
            $created_by = Base_UserCommon::get_user_label($r['created_by']);
            $deleted = $r['deleted'] ? __('Yes') : __('No');
            // $link = $r['link']; // Link column hidden for now, see cols above
            $backref = $this->create_backref_link($r['backref']);

            $row = $gb->get_new_row();
            $row->add_data_array([$created_on, $created_by, $filename, $backref, $deleted]);
            // Same view/download access log the file's own "File History" popup
            // link already offers (Utils_FileStorage_FileLeightbox::get_file_leightbox()
            // -> file_history()'s "File access history" tab) - surfaced here as a
            // direct row action too, one click instead of two, same as
            // RecordBrowser's "View edit history" row action (icon='history').
            $row->add_action($this->create_callback_href($this->show_file_history(...), [$r['id']]), __('History'), __('View file view/download history'), 'history');
        }

        $this->display_module($gb);
    }

    public function show_file_history($id)
    {
        self::getHistory($id);
        return false;
    }

    // Own QuickForm instance, separate from Utils_GenericBrowser's internal
    // search form ($gb->form_s) - lets Created On/Created By be filtered by
    // real value (date range, exact user match) instead of the generic
    // browser's per-column free-text LIKE search (see get_search_query()),
    // which has no concept of a range or a select's option list. Same
    // pattern as Apps_ActivityReport::body()'s own filter form.
    private function filter_form()
    {
        $form = $this->init_module(Libs_QuickForm::module_name(), null, 'files_filter');

        $creators = DB::GetAssoc('SELECT DISTINCT created_by, created_by AS uid FROM utils_filestorage');
        foreach ($creators as $k => $u) {
            $creators[$k] = Base_UserCommon::get_user_label($u, true);
        }
        asort($creators);
        $creators = ['' => '[' . __('All') . ']'] + $creators;

        $form->addElement('datepicker', 'created_from', __('Created On') . ' (' . __('From') . ')');
        $form->addElement('datepicker', 'created_to', __('Created On') . ' (' . __('To') . ')');
        $form->addElement('select', 'created_by', __('Created By'), $creators);
        $form->addElement('submit', 'submit_filter', __('Filter'));

        $filters = $this->get_module_variable('filters', ['created_from' => '', 'created_to' => '', 'created_by' => '']);
        if ($form->validate()) {
            $filters = $form->exportValues();
            $this->set_module_variable('filters', $filters);
        }
        $form->setDefaults($filters);

        // display_as_row() renders via Libs_QuickForm's own shared row.tpl/
        // row.css (used by every "compact filter bar" in the app, e.g.
        // RecordBrowser's admin "Show records" filter) - wrapping its output
        // in a module-specific class instead of editing that shared CSS
        // keeps the smaller font scoped to just this filter bar. See
        // admin.css.
        Base_ThemeCommon::load_css(self::module_name(), 'admin');
        echo '<div class="epesi-filestorage-filter">';
        $form->display_as_row();
        echo '</div>';

        return $filters;
    }

    private function get_sql_query(Utils_GenericBrowser $gb, array $filters = [])
    {
        $orderSql = $gb->get_query_order();
        $whereSql = $gb->get_search_query();

        $conditions = [];
        if ($whereSql) {
            $conditions[] = "($whereSql)";
        }
        if (!empty($filters['created_by'])) {
            $conditions[] = 'created_by=' . (int)$filters['created_by'];
        }
        // created_on is a native DATETIME column (Install.php declares it as
        // ADODB Data Dictionary type 'T' - DB::Execute()'s %T placeholder
        // stores a formatted datetime string, not a raw unix int - see
        // BindTimeStamp()/DBTimeStamp() in vendor/adodb), so the filter
        // needs a quoted datetime literal here too, not (int)strtotime(...).
        if (!empty($filters['created_from'])) {
            $conditions[] = 'created_on>=' . DB::qstr(date('Y-m-d H:i:s', strtotime($filters['created_from'] . ' 00:00:00')));
        }
        if (!empty($filters['created_to'])) {
            $conditions[] = 'created_on<=' . DB::qstr(date('Y-m-d H:i:s', strtotime($filters['created_to'] . ' 23:59:59')));
        }

        $whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $sqlPart = trim("FROM utils_filestorage $whereSql $orderSql");
        $total = DB::GetOne("SELECT count(*) $sqlPart");
        $limit = $gb->get_limit($total);
        $sqlPart .= " LIMIT $limit[numrows] OFFSET $limit[offset]";
        return $sqlPart;
    }

    private function create_backref_link($backref)
    {
        $parsed = $this->parse_backref($backref);
        if ($parsed && Utils_RecordBrowserCommon::check_table_name($parsed[0], false, false)) {
            return Utils_RecordBrowserCommon::create_default_linked_label($parsed[0], $parsed[1]);
        }
        // Fall back to the raw reference, still stripped of its 'rb:' prefix for display
        return str_starts_with($backref, 'rb:') ? substr($backref, 3) : $backref;
    }

    /**
     * @param string $backref
     * @return array{0: string, 1: int}|null [tab, record id], or null if $backref isn't a
     *         recognized "rb:tab/id" or "rb:tab/id/field_id" reference at all
     */
    private function parse_backref($backref)
    {
        if (str_starts_with($backref, 'rb:')) {
            $backref = substr($backref, 3);
        }
        // Generic RecordBrowser file fields write backrefs as "tab/id/field_id"
        // (see RecordBrowserCommon_0.php's save_val() calls to
        // Utils_FileStorageCommon::add_files()), not the plain "tab/id" pair
        // decode_record_token() expects - its array_pop() would grab the
        // trailing field_id as the record id instead of the real id in the
        // middle segment, so parse position 1 explicitly instead.
        $parts = explode('/', $backref);
        if (count($parts) < 2 || !is_numeric($parts[1])) {
            return null;
        }
        return [$parts[0], (int)$parts[1]];
    }

    /**
     * Whether $backref still points at something real - false for a dead reference left
     * behind by an uninstalled module (unknown table) or a deleted record (known table,
     * missing row). Unrecognized/non-"rb:" backref formats are reported as existing since
     * we can't confidently say they're orphaned.
     */
    private function backref_exists($backref)
    {
        $parsed = $this->parse_backref($backref);
        if (!$parsed) {
            return true;
        }
        [$tab, $id] = $parsed;
        if (!Utils_RecordBrowserCommon::check_table_name($tab, false, false)) {
            return false;
        }
        return (bool)Utils_RecordBrowserCommon::get_record($tab, $id, false);
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
            $file_leightbox_href[$id] = Utils_FileStorage_FileLeightbox::get_file_leightbox($meta, $action_urls, true);
            $file = '<a '.$file_leightbox_href[$id].'>'.$col['filename'].'</a>';
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

    public function show_scan_report()
    {
        Base_BoxCommon::push_module('Utils_FileStorage', 'scan_report', []);
        return false;
    }

    public function scan_report()
    {
        if ($this->is_back()) {
            return Base_BoxCommon::pop_main();
        }

        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

        [$orphaned, $missing] = $this->scan_orphaned_and_missing();

        $tb = $this->init_module(Utils_TabbedBrowser::module_name());

        $tb->start_tab(__('Orphaned Files') . ' (' . count($orphaned) . ')');
        echo '<p>' . __('Files whose reference no longer exists - typically left behind after uninstalling a module or deleting the record they were attached to. Their content is not reachable through the application anymore and can be safely removed.') . '</p>';
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'orphaned');
        $gb->set_inline_display();
        $gb->set_table_columns([
            ['name' => __('Created on'), 'width' => 20],
            ['name' => __('Created by'), 'width' => 20],
            ['name' => __('Filename'), 'width' => 30],
            ['name' => __('Dead reference'), 'width' => 30],
        ]);
        foreach ($orphaned as $r) {
            $row = $gb->get_new_row();
            $row->add_data(
                Base_RegionalSettingsCommon::time2reg($r['created_on']),
                Base_UserCommon::get_user_label($r['created_by']),
                Utils_FileStorageCommon::get_file_label($r['id']),
                htmlspecialchars($r['backref'])
            );
            $row->add_action($this->create_confirm_callback_href(__('Permanently remove this file record? This cannot be undone.'), $this->purge_file(...), [$r['id']], null, null, true),
                __('Delete'), __('Permanently remove this orphaned file record'), 'delete');
        }
        if (!$orphaned) {
            echo '<p><em>' . __('No orphaned files found.') . '</em></p>';
        }
        $this->display_module($gb);
        $tb->end_tab();

        $tb->start_tab(__('Missing Files') . ' (' . count($missing) . ')');
        echo '<p>' . __('Files whose content is no longer present on disk, even though their record and reference are still intact - the file can no longer be viewed or downloaded. Marking these as deleted flags them (and any record still referencing them) consistently, without removing the reference itself.') . '</p>';
        $gb2 = $this->init_module(Utils_GenericBrowser::module_name(), null, 'missing');
        $gb2->set_inline_display();
        $gb2->set_table_columns([
            ['name' => __('Created on'), 'width' => 20],
            ['name' => __('Created by'), 'width' => 20],
            ['name' => __('Filename'), 'width' => 30],
            ['name' => __('Occurrence'), 'width' => 30],
        ]);
        foreach ($missing as $r) {
            $row = $gb2->get_new_row();
            $row->add_data(
                Base_RegionalSettingsCommon::time2reg($r['created_on']),
                Base_UserCommon::get_user_label($r['created_by']),
                Utils_FileStorageCommon::get_file_label($r['id']),
                $this->create_backref_link($r['backref'])
            );
            $row->add_action($this->create_confirm_callback_href(__('Mark this file as deleted?'), $this->mark_missing_deleted(...), [$r['file_id']]),
                __('Delete'), __('Mark the file, and any record still referencing it, as deleted'), 'delete');
        }
        if (!$missing) {
            echo '<p><em>' . __('No missing files found.') . '</em></p>';
        }
        $this->display_module($gb2);
        $tb->end_tab();

        $this->display_module($tb);

        $this->caption = __('Orphaned / Missing Files');

        return true;
    }

    public function purge_file($id)
    {
        Utils_FileStorageCommon::purge($id);
    }

    public function mark_missing_deleted($file_id)
    {
        Utils_FileStorageCommon::delete_file_on_disk($file_id);
    }

    /**
     * @return array{0: array[], 1: array[]} [orphaned utils_filestorage rows, missing utils_filestorage rows]
     */
    private function scan_orphaned_and_missing()
    {
        $orphaned = [];
        $missing = [];
        foreach (DB::GetAll('SELECT * FROM utils_filestorage ORDER BY created_on DESC') as $r) {
            if ($r['backref'] && !$this->backref_exists($r['backref'])) {
                $orphaned[] = $r;
            }
            if (!$r['deleted'] && !Utils_FileStorageCommon::file_exists($r['id'], false)) {
                $missing[] = $r;
            }
        }
        return [$orphaned, $missing];
    }
}