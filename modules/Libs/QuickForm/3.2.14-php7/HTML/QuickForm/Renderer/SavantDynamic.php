<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4.0                                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Bertrand Mansion <bmansion@mamasam.com>                      |
// +----------------------------------------------------------------------+
//
// $Id$

require_once 'HTML/QuickForm/Renderer/Array.php';

/**
 * A concrete renderer for HTML_QuickForm, makes an array of form contents
 * suitable to be used with Savant template engine.
 * 
 * The form array structure is the following:
 * array(
 *   'frozen'           => 'whether the form is frozen',
 *   'javascript'       => 'javascript for client-side validation',
 *   'attributes'       => 'attributes for <form> tag',
 *   'requirednote      => 'note about the required elements',
 *   'hasrequired       => 'whether the form contains any required fields',
 *   // if we set the option to collect hidden elements
 *   'hidden'           => 'collected html of all hidden elements',
 *   // if there were some validation errors:
 *   'errors' => array(
 *     '1st element name' => 'Error for the 1st element',
 *     ...
 *     'nth element name' => 'Error for the nth element'
 *   ),
 *   'sections' => array(
 *     array(
 *       'header'   => 'Header text for the first header',
 *       'name'     => 'Header name for the first header',
 *       'elements' => array(
 *          element_1,
 *          ...
 *          element_K1
 *       )
 *     ),
 *     ...
 *     array(
 *       'header'   => 'Header text for the Mth header',
 *       'name'     => 'Header name for the Mth header',
 *       'elements' => array(
 *          element_1,
 *          ...
 *          element_KM
 *       )
 *     )
 *   )
 * );
 * 
 * where element_i is an array of the form:
 * array(
 *   'name'      => 'element name',
 *   'value'     => 'element value',
 *   'type'      => 'type of the element',
 *   'frozen'    => 'whether element is frozen',
 *   'label'     => 'label for the element',
 *   'required'  => 'whether element is required',
 *   'error'     => 'error associated with the element',
 *   'style'     => 'some information about element style',
 *   // if element is not a group
 *   'html'      => 'HTML for the element'
 *   // if element is in a group
 *   'separator' => 'separator for this element',
 *   // if element is a group
 *   'elements'  => array(
 *     element_1,
 *     ...
 *     element_N
 *   )
 * );
 * 
 * @access public
 */
class HTML_QuickForm_Renderer_SavantDynamic extends HTML_QuickForm_Renderer_Array
{
    
   /**
    * A separator for group elements
    * @var mixed
    */
    var $_groupSeparator = null;

   /**
    * The current element index inside a group
    * @var integer
    */
    var $_groupElementIdx = 0;

   /**
    * The number of elements in the current group
    * @var integer
    */
    var $_groupElementCount = 0;

   /**
    * Constructor
    *
    * @param  bool    true: collect all hidden elements into string; false: process them as usual form elements
    * @access public
    */
    function HTML_QuickForm_Renderer_SavantDynamic($collectHidden = false)
    {
        parent::HTML_QuickForm_Renderer_Array($collectHidden);
    } // end constructor


    function startForm(&$form)
    {
        parent::startForm($form);
        $this->_ary['hasrequired'] = false;
        $this->_currentSection = 0;
        $this->_sectionCount   = 1;
    } // end func startForm


    function renderHeader(&$header)
    {
        $this->_currentSection = $this->_sectionCount++;
        $this->_ary['sections'][$this->_currentSection] = array(
            'header' => $header->toHtml(),
            'name'   => $header->getName()
        );
    } // end func renderHeader


    function startGroup(&$group, $required, $error)
    {
        $this->_groupElementIdx = 0;
        $this->_groupElementCount = count($group->getElements());
        if ($required) {
            $this->_ary['hasrequired'] = true;
        }

        $this->_currentGroup = $this->_elementToArray($group, $required, $error);
        if (!empty($error)) {
            $this->_ary['errors'][$this->_currentGroup['name']] = $error;
        }
    } // end func startGroup


    function finishGroup(&$group)
    {
        $this->_storeArray($this->_currentGroup);
        $this->_currentGroup = null;
        $this->_groupSeparator = null;
    } // end func finishGroup


   /**
    * Creates an array representing an element
    * 
    * @access private
    * @param  object    An HTML_QuickForm_element object
    * @param  bool      Whether an element is required
    * @param  string    Error associated with the element
    * @return array
    */
    function _elementToArray(&$element, $required, $error)
    {
        $ret = array(
            'name'      => $element->getName(),
            'value'     => $element->getValue(),
            'type'      => $element->getType(),
            'frozen'    => $element->isFrozen(),
            'required'  => $required,
            'error'     => $error
        );

        if ($required) {
            $this->_ary['hasrequired'] = true;
        }

        // render label(s)
        $labels = $element->getLabel();
        if (is_array($labels)) {
            foreach($labels as $key => $label) {
                $key = is_int($key)? $key + 1: $key;
                if (1 === $key) {
                    $ret['label'] = $label;
                } else {
                    $ret['label_' . $key] = $label;
                }
            }
        } else {
            $ret['label'] = $labels;
        }
        
        // set the style for the element
        if (isset($this->_elementStyles[$ret['name']])) {
            $ret['style'] = $this->_elementStyles[$ret['name']];
        } else {
            $ret['style'] = null;
        }
        if ('group' == $ret['type']) {
            $this->_groupSeparator = (empty($element->_separator) ? '' : $element->_separator);
            $ret['elements'] = array();
        } else {
            $ret['html'] = $element->toHtml();
            if (isset($this->_groupSeparator)) {
                if (is_array($this->_groupSeparator)) {
                    $ret['separator'] = $this->_groupSeparator[($this->_groupElementIdx) % count($this->_groupSeparator)];
                } elseif ($this->_groupElementIdx < $this->_groupElementCount - 1) {
                    $ret['separator'] = (string)$this->_groupSeparator;
                } else {
                    $ret['separator'] = '';
                }
                $this->_groupElementIdx++;
            }
        }
        return $ret;
    }
}
?>