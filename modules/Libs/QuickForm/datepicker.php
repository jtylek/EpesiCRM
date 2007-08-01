<?php
/**
 * @package epesi-libs
 * @subpackage QuickForm
 */
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997, 1998, 1999, 2000, 2001 The PHP Group             |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Adam Daniel <adaniel1@eesus.jnj.com>                        |
// |          Bertrand Mansion <bmansion@mamasam.com>                     |
// +----------------------------------------------------------------------+
//
// $Id: text.php,v 1.5 2003/06/18 19:36:20 avb Exp $

require_once("HTML/QuickForm/input.php");

/**
 * HTML class for a text field
 * 
 * @author       Adam Daniel <adaniel1@eesus.jnj.com>
 * @author       Bertrand Mansion <bmansion@mamasam.com>
 * @version      1.0
 * @since        PHP4.04pl1
 * @access       public
 */
class HTML_QuickForm_datepicker extends HTML_QuickForm_input
{
                
    function HTML_QuickForm_datepicker($elementName=null, $elementLabel=null, $attributes=null) {
        HTML_QuickForm_input::HTML_QuickForm_input($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->setType('text');
        if( $this->getAttribute('format') == '' ) 
        	$this->setFormat('%Y.%m.%d');
    } //end constructor
        
    function setFormat($format) {
        $this->updateAttributes(array('format'=>$format));
    } //end func setSize
    
    function setSize($size) {
        $this->updateAttributes(array('size'=>$size));
    } //end func setSize

    function setMaxlength($maxlength) {
        $this->updateAttributes(array('maxlength'=>$maxlength));
    } //end func setMaxlength

	function getElementJs() {
		$js = '';
		if (!defined('HTML_QUICKFORM_datepicker_popUpCalendar_EXISTS')) {
			define('HTML_QUICKFORM_datepicker_popUpCalendar_EXISTS', true);
			
			$js = "
			popUpCalendar = function(URL) {
				day = new Date();
				id = day.getTime();
				calendar = window.open(URL, 'calendar', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=400,height=250,left=490,top=250');
			}
			";
		}
		return $js;
	}
	
    function toHtml()
    {
		$str = "";
        if ($this->_flagFrozen) {
            $str .= $this->getFrozenHtml();
        } else {
            $str .= $this->_getTabs() . '<input' . $this->_getAttrString($this->_attributes) . ' /> <a HREF="javascript:popUpCalendar(\'modules/Libs/QuickForm/datepicker/datepicker.php?field_name='.$this->getName().'&format='.$this->getAttribute('format').'\')">cal</a>';
        }
		return $str;
    } //end func toHtml
    
} //end class HTML_QuickForm_text
?>
