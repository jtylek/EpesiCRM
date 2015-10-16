<?php
/**
 * @author     Adam Bukowski <abukowski@telaxus.com>
 * @copyright  Copyright &copy; 2015, Telaxus LLC
 * @version    1.0
 * @license    MIT
 * @package    epesi-utils
 * @subpackage QueryBuilder
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_QueryBuilder extends Module
{
    private $instance_id;
    private $form_element_id;

    private $editor_element_name;

    private $form;
    private $form_initialized = false;
    private $width = '100%';

    private $filters;
    private $rules;
    private $options = array('allow_empty' => true);
    private $plugins = array('invert' => array('display_rules_button' => true));

    private static $empty_rules = array('condition' => 'AND', 'rules' => array());

    public function construct($form = null, $form_element_id = null)
    {
        $this->instance_id = 'builder_' . md5($this->get_path());
        $this->form_element_id = $form_element_id ? $form_element_id : $this->instance_id . '_form';

        $this->form = $form;
    }

    public function body()
    {
        $this->generate_query_builder();

        $theme = $this->pack_module('Base/Theme');
        $theme->assign('builder_id', $this->instance_id);
        $theme->assign('width', $this->width);
        $theme->assign('form', $this->get_html_of_module($this->form));
        $theme->display();
    }

    public function set_width($width)
    {
        $this->width = $width;
    }

    public function add_to_form($form, $form_element_id, $editor_element_label, $editor_element_name)
    {
        $this->form = $form;
        $this->form_element_id = $form_element_id;
        $this->editor_element_name = $editor_element_name;

        $this->form->addElement('static', $editor_element_name, $editor_element_label, "<div id=\"{$this->instance_id}\"></div>");

        $this->generate_query_builder();
    }

    protected function generate_query_builder()
    {
        $this->load_libs();

        $this->init_form();

        $this->options['filters'] = $this->filters;
        if ($this->plugins) {
            $this->options['plugins'] = $this->plugins;
        }
        $options_json = json_encode($this->options);
        $rules_json = $this->rules ? json_encode($this->rules) : json_encode(self::$empty_rules);
        $error_msg = __('Please fix query builder rules');
        $error_msg = json_encode($error_msg);
        eval_js("Utils_QueryBuilder('{$this->form->get_name()}', '{$this->form_element_id}', '{$this->instance_id}', {$options_json}, {$rules_json}, {$error_msg});");
    }

    public function validate()
    {
        if ($this->get_form()->validate()) {
            return $this->get_form()->exportValue($this->form_element_id);
        }
        return false;
    }

    public function closed()
    {
        return $this->is_back() || $this->get_form()->validate();
    }

    /**
     * Init standalone form and add Save/Cancel buttons
     * @param mixed $save_label true for default label, false to disable, string to enable with custom label.
     * @param mixed $cancel_label true for default label, false to disable, string to enable with custom label.
     * @param string $cancel_action custom action on cancel button. By default $this->create_back_href() - works with $this->closed()
     */
    public function add_buttons($save_label = true, $cancel_label = true, $cancel_action = null)
    {
        $this->init_form();
        $buttons = array();
        if ($cancel_label) {
            if ($cancel_label === true) $cancel_label = __('Cancel');
            if ($cancel_action === null) $cancel_action = $this->create_back_href();
            $buttons[] = $this->get_form()->createElement('button', 'cancel', $cancel_label, $cancel_action);
        }
        if ($save_label) {
            if ($save_label === true) $save_label = __('Save');
            $buttons[] = $this->get_form()->createElement('submit', 'submit', $save_label);
        }
        if ($buttons) {
            $this->get_form()->addGroup($buttons);
        }
    }

    public function init_form()
    {
        if (!$this->form_initialized) {
            $this->form_initialized = true;
            if (!$this->form) {
                $this->form = $this->init_module('Libs/QuickForm');
            }
            $this->form->addElement('hidden', $this->form_element_id, '', array('id' => $this->form_element_id));
            $last_valid_el_id = $this->form_element_id . '_last_valid';
            $this->form->addElement('hidden', $last_valid_el_id, '', array('id' => $last_valid_el_id));
            $this->form->addFormRule(array($this, 'check_for_error'));
        }
    }

    public function check_for_error($form_values)
    {
        if (isset($form_values[$this->form_element_id])
            && $form_values[$this->form_element_id] == '{}'
        ) {
            $error_element = $this->editor_element_name ?: $this->form_element_id;
            return array($error_element => __('Please fix query builder rules'));
        }
        return array();
    }

    public function get_form()
    {
        return $this->form;
    }

    public function get_form_element_id()
    {
        return $this->form_element_id;
    }

    public function set_rules($rules)
    {
        $this->rules = $rules;
    }

    public function set_filters($filters)
    {
        $this->filters = $filters;
    }

    public function set_option($option_name, $value)
    {
        $this->options[$option_name] = $value;
    }

    public function get_option($option_name, $default = null)
    {
        return isset($this->options[$option_name]) ? $this->options[$option_name] : $default;
    }

    public function get_options()
    {
        return $this->options;
    }

    protected function load_libs()
    {
        $m = $this->get_module_dir();
        load_css($m . 'bootstrap-compat.css');
        load_css($m . 'query-builder.default.css');
        load_js($m . 'query-builder.standalone.js');
        load_js($m . 'helper.js');

        $lang_code = Base_LangCommon::get_lang_code();
        if ($lang_code) {
            $lang_file = $this->get_module_dir() . 'i18n/query-builder.' . $lang_code . '.js';
            if (file_exists($lang_file)) {
                load_js($lang_file);
            }
        }

    }
}
