<?php
/**
 * HTML class for a multiselect
 *
 * @author       Arkadiusz Bisaga <abisaga@telaxus.com> based on HTML_QuickForm select.php
 * @license MIT
 * @version 1.0
 * @package epesi-libs
 * @subpackage QuickForm
 */

class HTML_QuickForm_multiselect extends HTML_QuickForm_multi
{

    /**
     * Contains the select options
     *
     * @var       array
     * @access    private
     */
    var $_options = array();

	private $on_add_js_code = '';
	private $on_remove_js_code = '';

    public function on_add_js($js) {
    	$this->on_add_js_code .= $js;
    }
    public function on_remove_js($js) {
    	$this->on_remove_js_code .= $js;
    }

	private $list_sep = '__SEP__';
    /**
     * Class constructor
     *
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @access    public
     * @return    void
     */
    function __construct($elementName=null, $elementLabel=null, $options=null, $attributes=null)
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        $this->_persistantFreeze = true;
        $this->_type = 'select';
        if (isset($options)) {
            $this->load($options);
        }
    }

    /**
     * Adds a new OPTION to the SELECT
     *
     * @param     string    $text       Display text for the OPTION
     * @param     string    $value      Value for the OPTION
     * @param     mixed     $attributes Either a typical HTML attribute string
     *                                  or an associative array
     * @access    public
     * @return    void
     */
    function addOption($text, $value, $attributes=null)
    {
        if (null === $attributes) {
            $attributes = array('value' => $value);
        } else {
            $attributes = $this->_parseAttributes($attributes);
            if (isset($attributes['selected'])) {
                // the 'selected' attribute will be set in toHtml()
                $this->_removeAttr('selected', $attributes);
                if (is_null($this->_values)) {
                    $this->_values = array($value);
                } elseif (!in_array($value, $this->_values)) {
                    $this->_values[] = $value;
                }
            }
            $this->_updateAttrArray($attributes, array('value' => $value));
        }
        $this->_options[] = array('text' => $text, 'attr' => $attributes);
    }

    /**
     * Loads the options from an associative array
     *
     * @param     array    $arr     Associative array of options
     * @param     mixed    $values  (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function loadArray($arr, $values=null)
    {
        if (!is_array($arr)) {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadArray is not a valid array');
        }
        if (isset($values)) {
            $this->setSelected($values);
        }
        foreach ($arr as $key => $val) {
            // Warning: new API since release 2.3
            $this->addOption($val, $key);
        }
        return true;
    }

    /**
     * Loads the options from DB_result object
     *
     * If no column names are specified the first two columns of the result are
     * used as the text and value columns respectively
     * @param     object    $result     DB_result object
     * @param     string    $textCol    (optional) Name of column to display as the OPTION text
     * @param     string    $valueCol   (optional) Name of column to use as the OPTION value
     * @param     mixed     $values     (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function loadDbResult(&$result, $textCol=null, $valueCol=null, $values=null)
    {
        if (!is_object($result) || !is_a($result, 'db_result')) {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadDbResult is not a valid DB_result');
        }
        if (isset($values)) {
            $this->setValue($values);
        }
        $fetchMode = ($textCol && $valueCol) ? DB_FETCHMODE_ASSOC : DB_FETCHMODE_ORDERED;
        while (is_array($row = $result->fetchRow($fetchMode)) ) {
            if ($fetchMode == DB_FETCHMODE_ASSOC) {
                $this->addOption($row[$textCol], $row[$valueCol]);
            } else {
                $this->addOption($row[0], $row[1]);
            }
        }
        return true;
    }

    /**
     * Queries a database and loads the options from the results
     *
     * @param     mixed     $conn       Either an existing DB connection or a valid dsn
     * @param     string    $sql        SQL query string
     * @param     string    $textCol    (optional) Name of column to display as the OPTION text
     * @param     string    $valueCol   (optional) Name of column to use as the OPTION value
     * @param     mixed     $values     (optional) Array or comma delimited string of selected values
     * @access    public
     * @return    void
     * @throws    PEAR_Error
     */
    function loadQuery(&$conn, $sql, $textCol=null, $valueCol=null, $values=null)
    {
        if (is_string($conn)) {
            require_once('DB.php');
            $dbConn = &DB::connect($conn, true);
            if (DB::isError($dbConn)) {
                return $dbConn;
            }
        } elseif (is_subclass_of($conn, "db_common")) {
            $dbConn = &$conn;
        } else {
            return PEAR::raiseError('Argument 1 of HTML_Select::loadQuery is not a valid type');
        }
        $result = $dbConn->query($sql);
        if (DB::isError($result)) {
            return $result;
        }
        $this->loadDbResult($result, $textCol, $valueCol, $values);
        $result->free();
        if (is_string($conn)) {
            $dbConn->disconnect();
        }
        return true;
    }

    /**
     * Loads options from different types of data sources
     *
     * This method is a simulated overloaded method.  The arguments, other than the
     * first are optional and only mean something depending on the type of the first argument.
     * If the first argument is an array then all arguments are passed in order to loadArray.
     * If the first argument is a db_result then all arguments are passed in order to loadDbResult.
     * If the first argument is a string or a DB connection then all arguments are
     * passed in order to loadQuery.
     * @param     mixed     $options     Options source currently supports assoc array or DB_result
     * @param     mixed     $param1     (optional) See function detail
     * @param     mixed     $param2     (optional) See function detail
     * @param     mixed     $param3     (optional) See function detail
     * @param     mixed     $param4     (optional) See function detail
     * @access    public
     * @return    PEAR_Error on error or true
     * @throws    PEAR_Error
     */
    function load(&$options, $param1=null, $param2=null, $param3=null, $param4=null)
    {
        switch (true) {
            case is_array($options):
                return $this->loadArray($options, $param1);
                break;
            case (is_a($options, 'db_result')):
                return $this->loadDbResult($options, $param1, $param2, $param3);
                break;
            case (is_string($options) && !empty($options) || is_subclass_of($options, "db_common")):
                return $this->loadQuery($options, $param1, $param2, $param3, $param4);
                break;
        }
    }

    /**
     * Returns the value of field without HTML tags
     *
     * @access    public
     * @return    string
     */
    function getFrozenHtml()
    {
        $html = '';
        foreach($this->_options as $k=>$v)
            if (in_array($v['attr']['value'],$this->_values)) $html .= ($html?'<br />':'').(empty($v['text'])? '&nbsp;':'<span>'.$v['text'].'</span>');
        return $html;
    }

    /**
     * We check the options and return only the values that _could_ have been
     * selected. We also return a scalar value if select is not "multiple"
     */
    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        }
		if (!is_array($value)) {
			$value = explode($this->list_sep,$value);
			array_shift($value);
		}
        return $this->_prepareValue($value, $assoc);
    }

    /**
     * Returns the SELECT in HTML
     *
     * @access    public
     * @return    string
     */
    public function getHtml()
    {
        $this->updateAttributes(array('multiple' => 'multiple'));
        $myName = $this->getName();

        $options_ = '';
        foreach ($this->_options as $k=>$option) {
            $selected = (is_array($this->_values) && in_array((string)$this->_options[$k]['attr']['value'], $this->_values)) ? 'selected="1" ' : '';
            $options_ .= '<option ' . $selected . $this->_getAttrString($this->_options[$k]['attr']) . '>' . $this->_options[$k]['text'] . '</option>' . "\n";
        }
        $attrString = $this->_getAttrString($this->_attributes);
        $select2_js = <<<js
            jQuery("select[name='{$myName}[]']").select2();
            jQuery("select[name='{$myName}[]']").on('select2:select', function(evt) {
              {$this->on_add_js_code}
            });
            jQuery("select[name='{$myName}[]']").on('select2:unselect', function(evt) {
              {$this->on_remove_js_code}
            });
js;

        $select2 = <<<html
            <select class="js-example-basic-multiple" multiple="multiple" style="width: 100%" name="{$myName}[]" {$attrString}>
              {$options_}
            </select>
html;

        eval_js($select2_js);
        return $select2;
    }

}
?>
