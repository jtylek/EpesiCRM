<?php
/**
 * Quill Editor - https://quilljs.com
 * Copyright (c) 2017-2024, Slab. Copyright (c) 2014, Jason Chen. Copyright (c) 2013, salesforce.com
 * Released under the BSD 3-Clause License.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage Quill
 */

// Quill's own toolbar module accepts an array of format groups (each inner
// array a group of buttons). Kept as a plain function rather than a class
// constant so setToolbarPreset() can build on it without a static-property
// PHP version dance.
class HTML_QuickForm_quill extends HTML_QuickForm_element {
    private $config;
    private $_value = null;

    function __construct($elementName=null, $elementLabel=null, $attributes=null) {
        load_js('modules/Libs/Quill/quill.js','');
        load_js('modules/Libs/Quill/qu.js','');
        // CSS loaded here rather than QuillCommon_0.php's top level (where the
        // 'ckeditor'/'quill' type registration this element depends on is also
        // duplicated, redundantly but harmlessly, by include/epesi.php's own
        // eager custom-type registration): a Common file's top-level code only
        // runs on a request if ModuleManager's commons_with_code cache already
        // lists it as "has code" - true for every *other* module tested tonight,
        // but observed unreliable for this newly-installed module in this dev
        // environment (quill.snow.css silently never sent on some otherwise-
        // correct requests, even fresh-session ones, with no code change able to
        // explain it). This constructor's load_js() calls, by contrast, verified
        // reliably every single time in the same testing - so load_css() moved
        // here too rather than chasing the cache inconsistency further.
        load_css('modules/Libs/Quill/quill.snow.css');
        load_css('modules/Libs/Quill/frontend.css');
        load_css('modules/Libs/Quill/theme.css');
        parent::__construct($elementName, $elementLabel, $attributes); // PHP4 ctor → openpsa parent::__construct
        $this->_persistantFreeze = true;
        $this->_type = 'text';
        $this->config = array();
        if(!isset($this->_attributes['id']))
        	$this->_attributes['id'] = 'quill_'.$elementName;
        $this->setToolbarPreset(false);
    }

    // $sWidth/$sHeight: bare number (px) or any valid CSS length ('99%', '20em', ...) -
    // same convention as the CKEditor element this replaces, so the 4 existing call
    // sites' arguments port over unchanged. $bAdvanced picks the Basic vs Full toolbar
    // preset, matching the per-user 'editor' setting (Base_User_SettingsCommon) that
    // already drives this today.
    function setQuillProps($sWidth = null, $sHeight = null, $bAdvanced = false) {
        $this->config['width'] = $sWidth;
        $this->config['height'] = $sHeight;
        $this->setToolbarPreset($bAdvanced);
    }

    function setToolbarPreset($bAdvanced) {
        $this->config['advanced'] = $bAdvanced;
        $this->config['toolbar'] = $bAdvanced ? $this->toolbarAdvanced() : $this->toolbarBasic();
    }

    private function toolbarBasic() {
        return array(
            array('bold','italic','underline'),
            array(array('list'=>'ordered'),array('list'=>'bullet')),
            array('link'),
            array('clean'),
        );
    }

    private function toolbarAdvanced() {
        return array(
            array('header'=>array(1,2,3,false)),
            array('bold','italic','underline','strike'),
            array(array('color'=>array()),array('background'=>array())),
            array(array('list'=>'ordered'),array('list'=>'bullet')),
            array(array('indent'=>-1),array('indent'=>1)), // must be JS numbers, not strings - Quill's own toolbar/format matching is type-strict and silently no-ops on a string ("quill:toolbar ignoring attaching to nonexistent format" in the console) rather than erroring
            array('blockquote','code-block'),
            array('link','image'),
            array('clean'),
        );
    }

    // Adds a live "switch toolbar" button (mirrors CKEditor's old toolbarswitch
    // plugin, not ported when this element replaced ckeditor.php - see
    // AI-shared/ckeditor-to-quill-migration.md's "Not ported" note) that lets the
    // user flip between the Basic and Advanced presets for just this instance,
    // without touching their saved Base_User_SettingsCommon 'editor' default -
    // the starting toolbar is still whatever setQuillProps()/setToolbarPreset()
    // was called with. Must be called after setQuillProps() so $this->config
    // ['advanced'] already reflects the intended starting state.
    function enableToolbarSwitch() {
        $basic = $this->toolbarBasic();
        $basic[] = array('switchtoolbar');
        $advanced = $this->toolbarAdvanced();
        $advanced[] = array('switchtoolbar');
        $this->config['toolbarBasic'] = $basic;
        $this->config['toolbarAdvanced'] = $advanced;
        $this->config['toolbar'] = !empty($this->config['advanced']) ? $advanced : $basic;
        $this->config['switchable'] = true;
        $this->config['switchTitleToAdvanced'] = __('Switch to advanced toolbar');
        $this->config['switchTitleToBasic'] = __('Switch to basic toolbar');
    }

    function setConfig(array $conf) {
        $this->config = array_merge($this->config ?? array(), $conf);
    }

    function setName($name) {
        $this->updateAttributes(array('name'=>$name));
    }

    function getName() {
        return $this->getAttribute('name');
    }

    function setValue($value) {
        $this->_value = $value;
    }

    function getValue() {
        return $this->_value;
    }

    function exportValue(&$submitValues, $assoc = false) {
        $value = $this->_findValue($submitValues);
        if (null === $value) {
            $value = $this->getValue();
        }
        return $this->_prepareValue($value, $assoc);
    }

    function toHtml() {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            $id = $this->_attributes['id'];
            $style = '';
            if (!empty($this->config['width'])) $style .= 'width:'.self::to_css_length($this->config['width']).';';
            if (!empty($this->config['height'])) $style .= 'height:'.self::to_css_length($this->config['height']).';';
            $hib = array('toolbar' => $this->config['toolbar']);
            if (!empty($this->config['switchable'])) {
                $hib['switchable'] = true;
                $hib['advanced'] = !empty($this->config['advanced']);
                $hib['toolbarBasic'] = $this->config['toolbarBasic'];
                $hib['toolbarAdvanced'] = $this->config['toolbarAdvanced'];
                $hib['switchTitleToAdvanced'] = $this->config['switchTitleToAdvanced'];
                $hib['switchTitleToBasic'] = $this->config['switchTitleToBasic'];
            }
            eval_js('quills_hib["'.$id.'"]='.json_encode($hib));
            // Hidden input carries the value through form serialization exactly like
            // the textarea it replaces did for CKEditor - qu.js keeps it synced from
            // the live Quill instance (on every edit, and again right before
            // destroy/submit). The container div's initial innerHTML is the current
            // value too (unescaped, it's rendered HTML content, not an attribute) so
            // there is a readable, already-correct fallback visible even before/if
            // Quill's own JS init runs - progressive enhancement, not a blank box.
            return $this->_getTabs() .
                   '<input type="hidden"' . $this->_getAttrString($this->_attributes) .
                   ' value="' . htmlspecialchars($this->_value ?? '') . '">' .
                   '<div id="' . $id . '_editor" class="epesi-quill-editor" style="' . $style . '">' .
                   ($this->_value ?? '') .
                   '</div>';
        }
    }

    function getFrozenHtml() {
        $value = $this->getValue();
        return $this->_getTabs() . '<div class="epesi-quill-frozen">' . $value . '</div>' . $this->_getPersistantData();
    }

    private static function to_css_length($v) {
        return is_numeric($v) ? $v.'px' : $v;
    }
}
?>
