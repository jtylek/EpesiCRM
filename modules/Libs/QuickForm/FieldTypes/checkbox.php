<?php

class Epesi_QuickForm_checkbox extends HTML_QuickForm_checkbox
{
    function getFrozenHtml()
    {
      if ($this->getChecked()) {
          return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" />' .
                 $this->_getPersistantData();
      } else {
          return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" />';
      }
    }
}
?>
