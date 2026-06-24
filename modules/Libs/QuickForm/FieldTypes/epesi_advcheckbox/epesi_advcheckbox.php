<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Extends openpsa HTML_QuickForm_advcheckbox to restore Epesi's image-based frozen display.
 * advcheckbox always appends _getPersistantData() (both states), unlike plain checkbox.
 */
class HTML_QuickForm_epesi_advcheckbox extends HTML_QuickForm_advcheckbox {
    public function getFrozenHtml() {
        $img = $this->getChecked()
            ? '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" />'
            : '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" />';
        return $img . $this->_getPersistantData();
    }
}
