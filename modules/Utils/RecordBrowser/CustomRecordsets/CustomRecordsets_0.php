<?php
/**
 * @author Arkadiusz Bisaga <abisaga@telaxus.com>
 *         Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2008, 2014 Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_CustomRecordsets extends Module
{
    public function body()
    {
        if (isset($_REQUEST['tab']))
            $this->set_module_variable('tab', $_REQUEST['tab']);
        $tab = $this->get_module_variable('tab');
        $this->rb = $this->init_module(Utils_RecordBrowser::module_name(), $tab, 'custom_rset_' . $tab);
        $this->display_module($this->rb);
    }

    public function admin()
    {
        if ($this->is_back()) {
            if ($this->parent->get_type() == 'Base_Admin') {
                $this->parent->reset();
            } elseif (Base_BoxCommon::main_module_instance()->get_type() == $this->get_type()) {
                Base_BoxCommon::pop_main();
            }
            return;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'rb_custom');
        $gb->set_table_columns(array(
            array('name' => __('Table')),
            array('name' => __('Caption')),
            array('name' => __('Menu Entry'))
        ));
        $tabs = DB::GetAll('SELECT * FROM recordbrowser_custom_recordsets ORDER BY tab ASC');
        foreach ($tabs as $t) {
            $gbr = $gb->get_new_row();
            if (!$t['active']) $gbr->add_action($this->create_callback_href(array($this, 'set_active'), array($t['id'], true)), 'Activate', null, 'active-off');
            else $gbr->add_action($this->create_callback_href(array($this, 'set_active'), array($t['id'], false)), 'Deactivate', null, 'active-on');
            $gbr->add_action($this->create_callback_href(array($this, 'edit_rset'), array($t['id'])), 'edit');
            $table_name = $t['tab'];
            $table_href = $this->create_callback_href(array($this, 'manage_recordset'), array($table_name));
            $gbr->add_data("<a $table_href>$table_name</a>", Utils_RecordBrowserCommon::get_caption($t['tab']), str_replace(Utils_RecordBrowser_CustomRecordsetsCommon::$sep, ' -> ', $t['menu']));
        }
        Base_ActionBarCommon::add('new', __('Create new'), $this->create_callback_href(array($this, 'edit_rset')));
        print('<div class="card "><div class="card-body">');
        $this->display_module($gb);
        print('</div></div>');
    }

    public function manage_recordset($recordset)
    {
        if ($this->is_back()) {
            return false;
        }
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
        $this->pack_module(Utils_RecordBrowser::module_name(), $recordset, 'record_management');
        return true;
    }

    public function set_active($id, $state = true)
    {
        DB::Execute('UPDATE recordbrowser_custom_recordsets SET active=%d WHERE id=%d', array($state ? 1 : 0, $id));
        return false;
    }

    public function edit_rset($id = null)
    {
        if ($this->is_back()) return false;
        $form = $this->init_module(Libs_QuickForm::module_name());

        $menu_deep = 3;

        $form->addElement('header', null, $id ? __('Edit RecordSet properties') : __('Create new RecordSet'));
        $form->addElement('text', 'tab', __('Table name'));
        $form->addElement('text', 'caption', __('Caption'));
        $m = array();
        for ($i = 0; $i < $menu_deep; $i++) {
            $m[] = $form->createElement('text', 'menu_' . $i, __('Menu'), array('placeholder' => __('Enter Menu or Submenu label')));
        }
        $form->addGroup($m, 'menu', __('Menu'));
        $form->addElement('checkbox', 'recent', __('Enable Recent'));
        $form->addElement('checkbox', 'favs', __('Enable Favorites'));
        $form->addRule('tab', __('Field required'), 'required');
        $form->addRule('caption', __('Field required'), 'required');
        $form->addFormRule(array($this, 'check_form'));

        if ($id !== null) {
            $tab = DB::GetOne('SELECT tab FROM recordbrowser_custom_recordsets WHERE id=%d', array($id));
            $menu = DB::GetOne('SELECT menu FROM recordbrowser_custom_recordsets WHERE id=%d', array($id));
            $p = DB::getRow('SELECT * FROM recordbrowser_table_properties WHERE tab=%s', array($tab));
            $form->setDefaults(array('tab' => $tab, 'caption' => $p['caption'], 'recent' => $p['recent'], 'favs' => $p['favorites']));
            $menu = explode(Utils_RecordBrowser_CustomRecordsetsCommon::$sep, $menu);
            foreach ($menu as $k => $v)
                $form->setDefaults(array('menu[menu_' . $k . ']' => $v));
            $form->freeze('tab');
        }

        if ($form->validate()) {
            $vals = $form->exportValues();
            $menu = array();
            for ($i = 0; $i < $menu_deep; $i++) {
                if ($vals['menu']['menu_' . $i]) {
                    $menu[] = $vals['menu']['menu_' . $i];
                }
            }
            if ($id === null) {
                $vals['tab'] = strtolower($vals['tab']);
                Utils_RecordBrowserCommon::install_new_recordset($vals['tab']);
                Utils_RecordBrowserCommon::add_default_access($vals['tab']);
                DB::Execute('INSERT INTO recordbrowser_custom_recordsets (active, tab, menu) VALUES (1, %s, %s)', array($vals['tab'], implode(Utils_RecordBrowser_CustomRecordsetsCommon::$sep, $menu)));
                $tab = $vals['tab'];
            } else {
                DB::Execute('UPDATE recordbrowser_custom_recordsets SET menu=%s WHERE id=%d', array(implode(Utils_RecordBrowser_CustomRecordsetsCommon::$sep, $menu), $id));
            }
            Utils_RecordBrowserCommon::set_caption($tab, $vals['caption']);
            Utils_RecordBrowserCommon::set_recent($tab, isset($vals['recent']) ? 15 : 0);
            Utils_RecordBrowserCommon::set_favorites($tab, isset($vals['favs']) ? true : false);
            return false;
        }

        $form->display_as_column();
        Base_ActionBarCommon::add('back', __('Cancel'), $this->create_back_href());
        Base_ActionBarCommon::add('save', __('Save'), $form->get_submit_form_href());
        return true;
    }

    public function check_form($data)
    {
        $ret = array();
        if (isset($data['tab'])) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z_0-9]+$/', $data['tab'])) $ret['tab'] = __('Invalid table name');
            if (strlen($data['tab']) > 39) $ret['tab'] = __('Maximum length for this field is %s characters.', array(39));
            if (DB::GetOne('SELECT 1 FROM recordbrowser_table_properties WHERE tab=%s', array($data['tab']))) $ret['tab'] = __('RecordSet already exists');
        }
        if (strlen($data['caption']) > 32) $ret['caption'] = __('Maximum length for this field is %s characters.', array(32));
        foreach ($data['menu'] as $menu_label) {
            if (strpos($menu_label, Utils_RecordBrowser_CustomRecordsetsCommon::$sep) !== false) {
                $ret['menu'] = __('Menu label cannot contain %s', array(Utils_RecordBrowser_CustomRecordsetsCommon::$sep));
            }
        }
        return $ret;
    }
}

?>