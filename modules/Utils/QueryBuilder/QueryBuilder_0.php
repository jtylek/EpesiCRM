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

    private $form;

    private $filters;
    private $rules;
    private $options = array('allow_empty' => true);
    private $plugins = array('invert' => array('display_rules_button' => true));

    public function construct($form = null, $form_element_id = null)
    {
        $this->instance_id = 'builder_' . md5($this->get_path());
        $this->form_element_id = $form_element_id ? $form_element_id : $this->instance_id . '_form';

        $this->form = $form ? $form : $this->init_module('Libs/QuickForm');
        $this->form->addElement('hidden', $this->form_element_id, '', array('id' => $this->form_element_id));
    }

    public function body()
    {
        $this->load_libs();

        $theme = $this->pack_module('Base/Theme');
        $theme->assign('builder_id', $this->instance_id);
        $theme->assign('width', '80%');
        $theme->assign('form', $this->get_html_of_module($this->form));
        $theme->display();
        $this->options['filters'] = $this->filters;
        if ($this->plugins) {
            $this->options['plugins'] = $this->plugins;
        }
        $options_json = json_encode($this->options);
        $rules_json = $this->rules ? json_encode($this->rules) : json_encode(array());
        $error_msg = __('Please fix query builder rules');
        $error_msg = json_encode($error_msg);
        eval_js("Utils_QueryBuilder('{$this->form->get_name()}', '{$this->form_element_id}', '{$this->instance_id}', {$options_json}, {$rules_json}, {$error_msg});");
    }

    public function validate()
    {
        if ($this->form->validate()) {
            return $this->form->exportValue($this->form_element_id);
        }
        return false;
    }

    public function add_save_button()
    {
        $this->form->addElement('submit', 'submit', __('Save'));
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
        return;
        // example
        $rules_json = '{
  "condition": "AND",
  "rules": [
    {
      "id": "price",
      "field": "price",
      "type": "double",
      "input": "text",
      "operator": "less",
      "value": "10.25"
    },
    {
      "condition": "OR",
      "rules": [
        {
          "id": "category",
          "field": "category",
          "type": "integer",
          "input": "select",
          "operator": "equal",
          "value": "2"
        },
        {
          "id": "category",
          "field": "category",
          "type": "integer",
          "input": "select",
          "operator": "equal",
          "value": "1"
        }
      ]
    }
  ]
}';
        $this->rules = json_decode($rules_json);
    }

    public function set_filters($filters)
    {
        $this->filters = $filters;
        return;
        // example
        $filters_json = '[{"id":"name","label":"Name","type":"string"},{"id":"category","label":"Category","type":"integer","input":"select","values":{"1":"Books","2":"Movies","3":"Music","4":"Tools","5":"Goodies","6":"Clothes"},"operators":["equal","not_equal","in","not_in","is_null","is_not_null"]},{"id":"in_stock","label":"In stock","type":"integer","input":"radio","values":{"0":"No","1":"Yes"},"operators":["equal"]},{"id":"price","label":"Price","type":"double","validation":{"min":0,"step":0.01}},{"id":"id","label":"Identifier","type":"string","placeholder":"____-____-____","operators":["equal","not_equal"],"validation":{"format":{}}}]';
        //
        $this->filters = json_decode($filters_json);
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
