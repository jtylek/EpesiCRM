<?php
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Extends openpsa HTML_QuickForm_checkbox to restore Epesi's image-based frozen display
 * (checkbox_on.png / checkbox_off.png from the active theme) instead of openpsa's plain-text [x]/[ ].
 * The <img> is wrapped in a <span class="epesi-frozen-checkbox epesi-frozen-checkbox-on|off"> -
 * inert under the legacy default theme (no matching CSS there, img displays exactly as before),
 * but lets the AdminLTE theme (Libs/QuickForm/theme_adminltedark/default.css) hide the image and
 * show an on/off switch glyph instead, matching the same look active (non-frozen) checkboxes get
 * via the 'epesi-switch' class. The wrapping span stays inline (unlike the field template's own
 * block-level wrapper div, which every field gets regardless of type) so the glyph and the image
 * share the same line - no extra empty line from a hidden block.
 */
class HTML_QuickForm_epesi_checkbox extends HTML_QuickForm_checkbox {
    public function getFrozenHtml() {
        if ($this->getChecked()) {
            return '<span class="epesi-frozen-checkbox epesi-frozen-checkbox-on"><img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" /></span>' .
                   $this->_getPersistantData();
        }
        return '<span class="epesi-frozen-checkbox epesi-frozen-checkbox-off"><img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" /></span>';
    }
}
