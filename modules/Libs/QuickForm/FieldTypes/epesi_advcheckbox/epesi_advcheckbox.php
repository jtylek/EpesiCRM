<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Extends openpsa HTML_QuickForm_advcheckbox to restore Epesi's image-based frozen display.
 * advcheckbox always appends _getPersistantData() (both states), unlike plain checkbox.
 * See HTML_QuickForm_epesi_checkbox's own comment for why the <img> is wrapped in a
 * <span class="epesi-frozen-checkbox epesi-frozen-checkbox-on|off"> - same reasoning, shared
 * AdminLTE-theme switch-glyph styling in Libs/QuickForm/theme_adminltedark/default.css.
 */
class HTML_QuickForm_epesi_advcheckbox extends HTML_QuickForm_advcheckbox {
    public function getFrozenHtml() {
        $checked = $this->getChecked();
        $img = $checked
            ? '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" />'
            : '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" />';
        $cls = $checked ? 'epesi-frozen-checkbox-on' : 'epesi-frozen-checkbox-off';
        return '<span class="epesi-frozen-checkbox '.$cls.'">'.$img.'</span>' . $this->_getPersistantData();
    }
}
