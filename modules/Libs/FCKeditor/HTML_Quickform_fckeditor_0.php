<?php
/**
 * This module uses FCKeditor editor released under
 * GNU Lesser General Public License Version 2.1 or later (the "LGPL")
 * FCKeditor - The text editor for Internet - http://www.fckeditor.net
 * Copyright (C) 2003-2007 Frederico Caldeira Knabben
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2007, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage fckeditor
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Custom HTML_Quickform elementtype voor FCKeditor textarea
 *
 * This elementtype builds an FCKeditor instance for PEAR::HTML_Quickform
 * class. It extends HTML_Quickform
 * 
 * @author Jordi Backx <jbackx@westsitemedia.nl> and Paul Bukowski <pbukowski@telaxus.com>
 * @version 1.1
 */
class HTML_Quickform_fckeditor extends HTML_Quickform_element
{
    /**
     * Path to FCK class
     *
     * @var string Path to PHP FCK class
     * @access private
     */
    var $_sFckBasePath = 'modules/Libs/FCKeditor/2.4.2/';
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
     * FCK properties
     *
     * @var array Set of FCK only properties
     * @access private
     */
    var $_aFckConfigProps = array('CustomConfigurationsPath' => NULL
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
     * @param mixed  $mAttributes   Other non-FCK optional attributes
     *
     * @access public
     * @return void
     */
    function HTML_Quickform_fckeditor($sElementName  = NULL
                                      ,$mElementLabel = NULL
                                      ,$mAttributes   = NULL) {
        HTML_Quickform_element::HTML_Quickform_element($sElementName, $mElementLabel, $mAttributes);
        $this->_persistantFreeze = TRUE;
        $this->_type             = 'fckeditor';
    }// End constructor
    /**
     * Set properties for FCKeditor instance
     *
     * @param string $sWidth             Width of the editor
     * @param string $sHeight            Height of the editor
     * @param boolean $bToolbarAdvanced        Toolbar
     * @param mixed  $mFckRequestedAttrs Set of FCK only attributes
     * @access public
     * @return void
     */
    function setFCKProps ($sWidth             = NULL
                         ,$sHeight            = NULL
                         ,$bToolbarAdvanced   = false
                         ,$mFckRequestedAttrs = NULL) {
        /*
         * Set public FCK attributes
         */
        $this->_sWidth      = $sWidth;
        $this->_sHeight     = $sHeight;
        $this->_sToolbarSet = ($bToolbarAdvanced)?'Default':'Basic';
        $this->_aFckConfigProps['DefaultLanguage'] = Base_LangCommon::get_lang_code(); 
        /*
         * Set configuration array if not NULL
         */
        if ($mFckRequestedAttrs !== NULL) {
            // Collect keys of requested attributes
            $aFckRequestedAttrKeys = array_keys($mFckRequestedAttrs);
            // Search in supported attribute array for the keys
            foreach ($this->_aFckConfigProps as $sFckProp => $sFckValue) {
                $mArraySearchResult = array_search($sFckProp, $aFckRequestedAttrKeys);
                if ($mArraySearchResult === FALSE) {
                    unset($this->_aFckConfigProps[$sFckProp]);
                } else {
                    $this->_aFckConfigProps[$sFckProp] = $mFckRequestedAttrs[$sFckProp];
                }
            }
        } else {
            // No properties requested
            $this->_aFckConfigProps = NULL;
        }
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
             * Create FCK editor
             */
            // Load FCKeditor class
            require_once('2.4.2/fckeditor.php');
            // Create instance
            $oFCKeditor = new FCKeditor($this->getAttribute('name'));
            // Set parameters
            if ($this->_sFckBasePath !== NULL) {
                $oFCKeditor->BasePath                    = $this->_sFckBasePath;
            }
            if ($this->_sToolbarSet !== NULL) {
                $oFCKeditor->ToolbarSet                  = $this->_sToolbarSet;
            }
            if ($this->_sWidth !== NULL) {
                $oFCKeditor->Width                       = $this->_sWidth;
            }
            if ($this->_sHeight !== NULL) {
                $oFCKeditor->Height                      = $this->_sHeight;
            }
            if ($this->_aFckConfigProps !== NULL) {
                $oFCKeditor->Config                      = $this->_aFckConfigProps;
                // If a relative path is given, then precede it with the editor's basepath (like in fckconfig.js)'
                if (isset($oFCKeditor->Config['SkinPath']) && substr($oFCKeditor->Config['SkinPath'], 0, 1) != '/') {
                    $oFCKeditor->Config['SkinPath']      = $this->_sFckBasePath.$oFCKeditor->Config['SkinPath'];
                }
                if (isset($oFCKeditor->Config['EditorAreaCSS']) && substr($oFCKeditor->Config['EditorAreaCSS'], 0, 1) != '/') {
                    $oFCKeditor->Config['EditorAreaCSS'] = $this->_sFckBasePath.$oFCKeditor->Config['EditorAreaCSS'];
                }
            }
            $oFCKeditor->Value                           = $this->getValue();
            // Generate the HTML code for the editor
            $sFCKCode = $oFCKeditor->CreateHTML();
            // Destroy FCKeditor object
            unset($oFCKeditor);
            /*
             * return code
             */
            return $this->_gettabs().$sFCKCode;
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
