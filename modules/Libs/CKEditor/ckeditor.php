<?php
class HTML_QuickForm_ckeditor extends HTML_QuickForm_element {
    private $config;
    private $_value = null;
//    private $e2module;

    function HTML_QuickForm_ckeditor($elementName=null, $elementLabel=null, $attributes=null) {
        load_js('modules/Libs/CKEditor/ckeditor/ckeditor.js','');
        load_js('modules/Libs/CKEditor/ck.js','');
        static $num = 0;
        HTML_QuickForm_element::HTML_QuickForm_element($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'text';
        $this->config = array();
        if(!isset($this->_attributes['id']))
        	$this->_attributes['id'] = 'ckeditor_'.$elementName;
    } //end constructor
    
    function setFCKProps ($sWidth             = NULL
                         ,$sHeight            = NULL
                         ,$bToolbarAdvanced   = false
                         ,$mCkRequestedAttrs = NULL) {
        $basic_toolbar = array(array('Bold', 'Italic', '-', 'NumberedList', 'BulletedList', '-', 'Link', 'Unlink', '-', 'Source', '-', 'About'));
        $this->setConfig(array('width'=>$sWidth,'height'=>$sHeight,'toolbar'=>($bToolbarAdvanced)?'Full':$basic_toolbar));
    }
    
    function setConfig(array $conf) {
    	$this->config = array_merge($this->config,$conf);
    }
    
    /**
     * Sets the input field name
     * 
     * @param     string    $name   Input field name attribute
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setName($name)
    {
        $this->updateAttributes(array('name'=>$name));
    } //end func setName
    
    // }}}
    // {{{ getName()

    /**
     * Returns the element name
     * 
     * @since     1.0
     * @access    public
     * @return    string
     */
    function getName()
    {
        return $this->getAttribute('name');
    } //end func getName

    // }}}

    /**
     * Sets value for textarea element
     * 
     * @param     string    $value  Value for textarea element
     * @since     1.0
     * @access    public
     * @return    void
     */
    function setValue($value)
    {
        $this->_value = $value;
    } //end func setValue
    
    // }}}
    // {{{ getValue()

    /**
     * Returns the value of the form element
     *
     * @since     1.0
     * @access    public
     * @return    string
     */
    function getValue()
    {
        return $this->_value;
    } // end func getValue

   /**
    * Returns a 'safe' element's value
    *
    * @param  array   array of submitted values to search
    * @param  bool    whether to return the value as associative array
    * @access public
    * @return mixed
    */

   /**
    * Returns a 'safe' element's value
    *
    * @param  array   array of submitted values to search
    * @param  bool    whether to return the value as associative array
    * @access public
    * @return mixed
    */
    function exportValue(&$submitValues, $assoc = false)
    {
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
            if(!isset($this->config['language']))
            	$this->config['language'] = substr(Base_LangCommon::get_lang_code(),0,2);
            if(!isset($this->config['scayt_sLang']))
                $this->config['scayt_sLang'] = Base_LangCommon::get_lang_code();
            if(!isset($this->config['scayt_autoStartup']))
                $this->config['scayt_autoStartup'] = 0;
      	    eval_js('ckeditors_hib["'.$this->_attributes['id'].'"]='.json_encode($this->config));
            return $this->_getTabs() .
                   '<textarea' . $this->_getAttrString($this->_attributes) . '>' .
                   // because we wrap the form later we don't want the text indented
                   preg_replace("/(\r\n|\n|\r)/", '&#010;', htmlspecialchars($this->_value)) .
                   '</textarea>';
        }
    } //end func toHtml

    function getFrozenHtml()
    {
        $value = htmlspecialchars($this->getValue());
        if ($this->getAttribute('wrap') == 'off') {
            $html = $this->_getTabs() . '<pre>' . $value."</pre>\n";
        } else {
            $html = nl2br($value)."\n";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml
}
?>