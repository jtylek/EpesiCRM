<?php

class Epesi_QuickForm_advcheckbox extends HTML_QuickForm_advcheckbox
{
    function getFrozenHtml()
    {
      if ($this->getChecked()) {
      	return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_on.png').'" alt="'.__('Yes').'" />' .
        	$this->_getPersistantData();
      } else {
      	return '<img src="'.Base_ThemeCommon::get_template_file('images','checkbox_off.png').'" alt="'.__('No').'" />' .
        	$this->_getPersistantData();
      }
    }
}
?>
