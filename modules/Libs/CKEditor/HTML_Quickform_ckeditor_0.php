<?php
/**
 * This module uses cKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * cKeditor - The text editor for Internet - http://www.ckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2011, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage ckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Custom HTML_Quickform elementtype voor cKeditor textarea
 *
 * This elementtype builds an cKeditor instance for PEAR::HTML_Quickform
 * class. It extends HTML_Quickform
 * 
 * @author Jordi Backx <jbackx@westsitemedia.nl> and Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.1
 */
class HTML_Quickform_ckeditor extends HTML_Quickform_element
{
    /**
     * Path to cK class
     *
     * @var string Path to PHP cK class
     * @access private
     */
    var $_sCkBasePath = 'modules/Libs/CKEditor/ckeditor/';
    /**
     * Toolbar
     *
     * @var string Requested toolbarset
     * @access private
     */
    var $_sToolbarSet = NULL;
    /**
     * Height of editor
     *
     * @var string Height
     * @access private
     */
    var $_sHeight = NULL;
    /**
     * Width of editor
     *
     * @var string Width
     * @access private
     */
    var $_sWidth = NULL;
    /**
     * CK properties
     *
     * @var array Set of CK only properties
     * @access private
     */
    var $_aCkConfigProps = array('CustomConfigurationsPath' => NULL
                                 ,'EditorAreaCSS'            => NULL
                                 ,'Debug'                    => NULL
                                 ,'SkinPath'                 => NULL
                                 ,'PluginsPath'              => NULL
                                 ,'AutoDetectLanguage'       => NULL
                                 ,'DefaultLanguage'          => NULL
                                 ,'EnableXHTML'              => NULL
                                 ,'EnableSourceXHTML'        => NULL
                                 ,'GeckoUseSPAN'             => NULL
                                 ,'StartupFocus'             => NULL
                                 ,'ForcePasteAsPlainText'    => NULL
                                 ,'ForceSimpleAmpersand'     => NULL
                                 ,'TabSpaces'                => NULL
                                 ,'UseBROnCarriageReturn'    => NULL
                                 ,'LinkShowTargets'          => NULL
                                 ,'LinkTargets'              => NULL
                                 ,'LinkDefaultTarget'        => NULL
                                 ,'ToolbarStartExpanded'     => NULL
                                 ,'ToolbarCanCollapse'       => NULL
                                 ,'StylesXmlPath'            => NULL
                                  );
    /**
     * Class constructor
     *
     * @param string $sElementName  Name attribute of element
     * @param mixed  $mElementLabel Label attribute of element
     * @param mixed  $mAttributes   Other non-CK optional attributes
     *
     * @access public
     * @return void
     */
    function HTML_Quickform_ckeditor($sElementName  = NULL
                                      ,$mElementLabel = NULL
                                      ,$mAttributes   = NULL) {
        HTML_Quickform_element::HTML_Quickform_element($sElementName, $mElementLabel, $mAttributes);
        $this->_persistantFreeze = TRUE;
        $this->_type             = 'ckeditor';
    }// End constructor
    /**
     * Set properties for CKeditor instance
     *
     * @param string $sWidth             Width of the editor
     * @param string $sHeight            Height of the editor
     * @param boolean $bToolbarAdvanced        Toolbar
     * @param mixed  $mCkRequestedAttrs Set of CK only attributes
     * @access public
     * @return void
     */
    function setCKProps ($sWidth             = NULL
                         ,$sHeight            = NULL
                         ,$bToolbarAdvanced   = false
                         ,$mCkRequestedAttrs = NULL) {
        /*
         * Set public CK attributes
         */
        $this->_sWidth      = $sWidth;
        $this->_sHeight     = $sHeight;
        $this->_sToolbarSet = ($bToolbarAdvanced)?'Default':'Basic';
        $this->_aCkConfigProps['DefaultLanguage'] = Base_LangCommon::get_lang_code(); 
        /*
         * Set configuration array if not NULL
         */
        if ($mCkRequestedAttrs !== NULL) {
            // Collect keys of requested attributes
            $aCkRequestedAttrKeys = array_keys($mCkRequestedAttrs);
            // Search in supported attribute array for the keys
            foreach ($this->_aCkConfigProps as $sCkProp => $sCkValue) {
                $mArraySearchResult = array_search($sCkProp, $aCkRequestedAttrKeys);
                if ($mArraySearchResult === FALSE) {
                    unset($this->_aCkConfigProps[$sCkProp]);
                } else {
                    $this->_aCkConfigProps[$sCkProp] = $mCkRequestedAttrs[$sCkProp];
                }
            }
        } else {
            // No properties requested
            $this->_aCkConfigProps = NULL;
        }
    }
    
    //compatibility
    function setFCKProps($a=null,$b=null,$c=false,$d=null) {
        $this->setCKProps($a,$b,$c,$d);
    }
    /**
     * Register name atribute
     *
     * @param string $sName Name attribute of element
     * @access public
     * @return void
     */
    function setName($sName) {
        $this->updateAttributes(array('name' => $sName));
    }// End function setName
    /**
     * Naam teruggeven (name attribute)
     *
     * @access public
     * @return string Name attribute element
     */
    function getName() {
        return $this->getAttribute('name');
    }// End function getName
    /**
     * Waarde/inhoud registreren (value attribute)
     *
     * @param string $sWaarde Value attribute of element
     * @access public
     * @return void
     */
    function setValue($sValue) {
        $this->updateAttributes(array('value' => $sValue));
    }// End function setValue
    /**
     * Return Value (value attribute)
     *
     * @access public
     * @return string Value attribute element
     */
    function getValue() {
        return $this->getAttribute('value');
    }// End function getValue
    /**
     * Generate and return HTML code for editor
     *
     * @access public
     * @return string HTML code element
     */
    function toHtml() {
        if ($this->_flagFrozen) {
            return $this->getFrozenHtml();
        } else {
            /*
             * Create CK editor
             */
            // Load CKeditor class
            require_once('ckeditor/ckeditor.php');
            // Create instance
            $oCKeditor = new CKEditor();
            // Set parameters
            if ($this->_sCkBasePath !== NULL) {
                $oCKeditor->BasePath                    = $this->_sCkBasePath;
            }
            if ($this->_sToolbarSet !== NULL) {
                $oCKeditor->ToolbarSet                  = $this->_sToolbarSet;
            }
            if ($this->_sWidth !== NULL) {
                $oCKeditor->Width                       = $this->_sWidth;
            }
            if ($this->_sHeight !== NULL) {
                $oCKeditor->Height                      = $this->_sHeight;
            }
            if ($this->_aCkConfigProps !== NULL) {
                $oCKeditor->config                      = $this->_aCkConfigProps;
                // If a relative path is given, then precede it with the editor's basepath (like in ckconfig.js)'
                if (isset($oCKeditor->config['SkinPath']) && substr($oCKeditor->config['SkinPath'], 0, 1) != '/') {
                    $oCKeditor->config['SkinPath']      = $this->_sCkBasePath.$oCKeditor->config['SkinPath'];
                }
                if (isset($oCKeditor->config['EditorAreaCSS']) && substr($oCKeditor->config['EditorAreaCSS'], 0, 1) != '/') {
                    $oCKeditor->config['EditorAreaCSS'] = $this->_sCkBasePath.$oCKeditor->config['EditorAreaCSS'];
                }
            }
//            $oCKeditor->Value                           = $this->getValue();
            // Generate the HTML code for the editor
            $sCKCode = $oCKeditor->editor($this->getAttribute('name'),$this->getValue());
            // Destroy CKeditor object
            unset($oCKeditor);
            /*
             * return code
             */
            return $this->_gettabs().$sCKCode;
        }
    }// End function toHtml
    /**
     * Return contents without HTML tags
     *
     * @access public
     * @return string Text contents element
     */
    function getFrozenHtml() {
        $sValue = htmlspecialchars($this->getValue());
        if ($this->getAttribute('wrap') == 'off') {
            $sHtml = $this->_getTabs(). '<pre>' .$sValue. '</pre>' . "\n";
        } else {
            $sHtml = nl2br($sValue). "\n";
        }
        return $sHtml.$this->_getPersistantData();
    }// End function getFrozenHtml
}
?>
