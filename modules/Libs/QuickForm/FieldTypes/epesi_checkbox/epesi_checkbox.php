<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Extends openpsa HTML_QuickForm_checkbox to restore Epesi's image-based frozen display
 * (checkbox_on.png / checkbox_off.png from the active theme) instead of openpsa's plain-text [x]/[ ].
 */
class HTML_QuickForm_epesi_checkbox extends HTML_QuickForm_checkbox {
    public function getFrozenHtml() {
        if ($this->getChecked()) {
            return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" />' .
                   $this->_getPersistantData();
        }
        return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" />';
    }
}
