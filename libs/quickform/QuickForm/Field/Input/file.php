<?php
/**
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2011 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 */

/**
 * HTML class for a file upload field
 *
 * @package     HTML_QuickForm
 * @author      Adam Daniel <adaniel1@eesus.jnj.com>
 * @author      Bertrand Mansion <bmansion@mamasam.com>
 * @author      Alexey Borzov <avb@php.net>
 */
class HTML_QuickForm_file extends HTML_QuickForm_input
{
   /**
    * Uploaded file data, from $_FILES
    * @var array
    */
    var $_value = null;

    /**
     * Class constructor
     *
     * @param     string   $elementName Input field name attribute
     * @param     string   $elementLabel Input field label
     * @param     mixed    $attributes (optional)Either a typical HTML attribute string or an associative array
     */
    public function __construct($elementName=null, $elementLabel=null, $attributes=null)
    {
        // register file-related rules
        $registry =& HTML_QuickForm_RuleRegistry::singleton();
        $registry->registerRule('uploadedfile', 'callback', '_ruleIsUploadedFile', $this);
        $registry->registerRule('maxfilesize', 'callback', '_ruleCheckMaxFileSize', $this);
        $registry->registerRule('mimetype', 'callback', '_ruleCheckMimeType', $this);
        $registry->registerRule('filename', 'callback', '_ruleCheckFileName', $this);

        parent::__construct($elementName, $elementLabel, $attributes);
        $this->setType('file');
    }

    /**
     * Sets size of file element
     *
     * @param     int   $size Size of file element
     */
    public function setSize($size)
    {
        $this->updateAttributes(array('size' => $size));
    }

    /**
     * Returns size of file element
     *
     * @return    int
     */
    public function getSize()
    {
        return $this->getAttribute('size');
    }

    /**
     * Freeze the element so that only its value is returned
     *
     * @return    bool
     */
    public function freeze()
    {
        return false;
    }

    /**
     * Sets value for file element.
     *
     * Actually this does nothing. The function is defined here to override
     * HTML_Quickform_input's behaviour of setting the 'value' attribute. As
     * no sane user-agent uses <input type="file">'s value for anything
     * (because of security implications) we implement file's value as a
     * read-only property with a special meaning.
     *
     * @param     mixed  $value  Value for file element
     * @return null
     */
    public function setValue($value)
    {
        return null;
    }

    /**
     * Returns information about the uploaded file
     *
     * @return    array
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     *
     * @param     string  $event  Name of event
     * @param     mixed   $arg  event arguments
     * @param     object  $caller  calling object
     * @return    bool
     * @throws HTML_QuickForm_Error
     */
    public function onQuickFormEvent($event, $arg, &$caller)
    {
        switch ($event) {
            case 'updateValue':
                if ($caller->getAttribute('method') == 'get') {
                    throw new HTML_QuickForm_Error('Cannot add a file upload field to a GET method form');
                }
                $this->_value = $this->_findValue($_FILES);
                $caller->updateAttributes(array('enctype' => 'multipart/form-data'));
                $caller->setMaxFileSize();
                break;
            case 'addElement':
                $this->onQuickFormEvent('createElement', $arg, $caller);
                return $this->onQuickFormEvent('updateValue', null, $caller);
                break;
            case 'createElement':
                $this->__construct($arg[0], $arg[1], $arg[2]);
                break;
        }
        return true;
    }

    /**
     * Moves an uploaded file into the destination
     *
     * @param    string $dest Destination directory path
     * @param    string $fileName New file name
     * @return   bool    Whether the file was moved successfully
     */
    public function moveUploadedFile($dest, $fileName = '')
    {
        if ($dest != ''  && substr($dest, -1) != '/') {
            $dest .= '/';
        }
        $fileName = ($fileName != '') ? $fileName : basename($this->_value['name']);
        return move_uploaded_file($this->_value['tmp_name'], $dest . $fileName);
    }

    /**
     * Checks if the element contains an uploaded file
     *
     * @return    bool      true if file has been uploaded, false otherwise
     */
    public function isUploadedFile()
    {
        return $this->_ruleIsUploadedFile($this->_value);
    }

    /**
     * Checks if the given element contains an uploaded file
     *
     * @param     array   $elementValue  Uploaded file info (from $_FILES)
     * @access    private
     * @return    bool      true if file has been uploaded, false otherwise
     */
    function _ruleIsUploadedFile($elementValue)
    {
        if ((isset($elementValue['error']) && $elementValue['error'] == 0) ||
            (!empty($elementValue['tmp_name']) && $elementValue['tmp_name'] != 'none')) {
            return is_uploaded_file($elementValue['tmp_name']);
        } else {
            return false;
        }
    }

    /**
     * Checks that the file does not exceed the max file size
     *
     * @param     array    $elementValue Uploaded file info (from $_FILES)
     * @param     int      $maxSize Max file size
     * @access    private
     * @return    bool      true if filesize is lower than maxsize, false otherwise
     */
    function _ruleCheckMaxFileSize($elementValue, $maxSize)
    {
        if (!empty($elementValue['error']) &&
            (UPLOAD_ERR_FORM_SIZE == $elementValue['error'] || UPLOAD_ERR_INI_SIZE == $elementValue['error'])) {
            return false;
        }
        if (!$this->_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        return ($maxSize >= @filesize($elementValue['tmp_name']));
    }

    /**
     * Checks if the given element contains an uploaded file of the right mime type
     *
     * @param     array    $elementValue Uploaded file info (from $_FILES)
     * @param     mixed    $mimeType Mime Type (can be an array of allowed types)
     * @access    private
     * @return    bool      true if mimetype is correct, false otherwise
     */
    function _ruleCheckMimeType($elementValue, $mimeType)
    {
        if (!$this->_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        if (is_array($mimeType)) {
            return in_array($elementValue['type'], $mimeType);
        }
        return $elementValue['type'] == $mimeType;
    }

    /**
     * Checks if the given element contains an uploaded file of the filename regex
     *
     * @param     array    $elementValue Uploaded file info (from $_FILES)
     * @param     string   $regex Regular expression
     * @access    private
     * @return    bool      true if name matches regex, false otherwise
     */
    function _ruleCheckFileName($elementValue, $regex)
    {
        if (!$this->_ruleIsUploadedFile($elementValue)) {
            return true;
        }
        return (bool)preg_match($regex, $elementValue['name']);
    }

   /**
    * Tries to find the element value from the values array
    *
    * Needs to be redefined here as $_FILES is populated differently from
    * other arrays when element name is of the form foo[bar]
    *
    * @return    mixed
    */
    protected function _findValue(&$values)
    {
        if (empty($values)) {
            return null;
        }
        $elementName = $this->getName();
        if (isset($values[$elementName])) {
            return $values[$elementName];
        } elseif (false !== ($pos = strpos($elementName, '['))) {
            $base  = str_replace(
                        array('\\', '\''), array('\\\\', '\\\''),
                        substr($elementName, 0, $pos)
                    );
            $idx   = "['" . str_replace(
                        array('\\', '\'', ']', '['), array('\\\\', '\\\'', '', "']['"),
                        substr($elementName, $pos + 1, -1)
                     ) . "']";
            $props = array('name', 'type', 'size', 'tmp_name', 'error');
            $code  = "if (!isset(\$values['{$base}']['name']{$idx})) {\n" .
                     "    return null;\n" .
                     "} else {\n" .
                     "    \$value = array();\n";
            foreach ($props as $prop) {
                $code .= "    \$value['{$prop}'] = \$values['{$base}']['{$prop}']{$idx};\n";
            }
            return eval($code . "    return \$value;\n}\n");
        } else {
            return null;
        }
    }
}
?>
