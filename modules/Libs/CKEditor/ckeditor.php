<?php
class HTML_QuickForm_ckeditor extends HTML_QuickForm_element {
    use toHtml;
    private $config;
    private $_value = null;
//    private $e2module;

    function __construct($elementName=null, $elementLabel=null, $attributes=null) {
        load_js('modules/Libs/CKEditor/ckeditor/ckeditor.js','');
        load_js('modules/Libs/CKEditor/ck.js','');
        static $num = 0;
        parent::__construct($elementName, $elementLabel, $attributes);
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
        $this->setConfig(array('width'=>$sWidth,'height'=>$sHeight,'toolbar'=>($bToolbarAdvanced)?'Full':'Basic'));
    }
    
    function setConfig(array $conf) {
    	$this->config = array_merge($this->config,$conf);
    }
    
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

    function getHtml() {

        if(!isset($this->config['language']))
            $this->config['language'] = substr(Base_LangCommon::get_lang_code(),0,2);
        if(!isset($this->config['scayt_sLang']))
            $this->config['scayt_sLang'] = Base_LangCommon::get_lang_code();
        if(!isset($this->config['scayt_autoStartup']))
            $this->config['scayt_autoStartup'] = 0;
        eval_js('ckeditors_hib["'.$this->_attributes['id'].'"]='.json_encode($this->config));
        return '<textarea' . $this->_getAttrString($this->_attributes) . '>' .
            // because we wrap the form later we don't want the text indented
            preg_replace("/(\r\n|\n|\r)/", '&#010;', htmlspecialchars($this->_value)) .
            '</textarea>';
    } //end func toHtml

    function getFrozenHtml()
    {
        $value = htmlspecialchars($this->getValue());
        if ($this->getAttribute('wrap') == 'off') {
            $html = '<pre>' . $value."</pre>\n";
        } else {
            $html = nl2br($value)."\n";
        }
        return $html . $this->_getPersistantData();
    } //end func getFrozenHtml
}
?>