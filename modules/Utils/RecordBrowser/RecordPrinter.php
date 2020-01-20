<?php

/**
 * RecordBrowser record printer
 *
 * @author  Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2014, Janusz Tylek
 * @license MIT
 * @version 1.9.0
 * @package epesi-utils
 * @subpackage RecordBrowser
 */

defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_RecordBrowser_RecordPrinter extends Base_Print_Printer
{

    /**
     * Document name is a string used to identify the document type printed
     * by your printer class.
     *
     * @return string NOT translated document name, mark to translate with _M()
     */
    public function document_name()
    {
        return _M('Generic RecordBrowser Record');
    }

    /**
     * This method is responsible for printing document.
     *
     * Example code:
     * <code>
     * $section = $this->new_section();
     * $section->assign('data', $data);
     * $this->print_section('section_name', $section);
     * </code>
     *
     * @param mixed $data This is a value that is passed to get_href method
     *
     * @see new_section
     * @see print_section
     * @see set_footer
     * @return null It doesn't have to return value
     */
    protected function print_document($data)
    {
        $tab = $data['tab'];
        $record_id = $data['record_id'];
        $printable_data = $this->format_values($tab, $record_id);
        $this->get_document()->set_filename($tab . '_' . $record_id);
        $section = $this->new_section();
        $section->assign('cols', $this->cols());
        $section->assign('caption', Utils_RecordBrowserCommon::get_caption($tab));
        $section->assign('record_id', $record_id);
        $section->assign('data', $printable_data);
        $this->print_section('record', $section);
    }

    protected function cols()
    {
        return 2;
    }

    protected function fill_empty_rows()
    {
        return true;
    }

    protected function format_values($tab, $record_id)
    {
        $rb_obj = new RBO_RecordsetAccessor($tab);
        $record = $rb_obj->get_record($record_id);
        if (!$record) {
            return array();
        }
        $access = Utils_RecordBrowserCommon::get_access($tab, 'view', $record);
        if (!$access) {
            return array();
        }
        // use RB object instance for better display callback compatibility
        // some of them uses Utils_RecordBrowser::$rb_obj instance
        $rb = ModuleManager::new_instance('Utils_RecordBrowser', null, 'rb');
        $rb->construct($tab);
        $rb->init($tab);
        $fields = Utils_RecordBrowserCommon::init($tab);
        $printable_data = array();
        foreach ($fields as $f) {
            if ($access[$f['id']]) {
                $printable_data[] = array('label' => _V($f['name']), 'value' => $record->get_val($f['id'], true));
            }
        }
        // fill rows - it's easier here than in template
        if ($this->fill_empty_rows()) {
            while (count($printable_data) % $this->cols() != 0) {
                $printable_data[] = array('label' => '', 'value' => '');;
            }
        }
        return $printable_data;
    }

    public function default_templates()
    {
        $tpl_obj = new Base_Print_Template_Template();
        $file = 'Utils/RecordBrowser/RecordPrint';
        $section_obj = new Base_Print_Template_SectionFromFile($file);
        $tpl_obj->set_section_template('record', $section_obj);

        return array('Default' => $tpl_obj);
    }

    public function sample_data()
    {
        $ret = array();
        $me = CRM_ContactsCommon::get_my_record();
        if ($me) {
            $ret[] = array('tab' => 'contact', 'record_id' => $me['id']);
            if ($me['company_name']) {
                $ret[] = array('tab' => 'company', 'record_id' => $me['company_name']);
            }
        }
        return $ret;
    }


}