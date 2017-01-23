<?php

require_once 'datepicker.php';

/**
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2017, Telaxus LLC
 * @license MIT
 * @version 1.0
 * @package epesi-Utils
 * @subpackage PopupCalendar
 */
class HTML_QuickForm_timestamp extends HTML_QuickForm_datepicker
{
    protected $showDate = true;

    /**
     * Class constructor
     *
     * @param     string $elementName       (optional) Input field name attribute
     * @param     string $elementLabel      (optional) Input field label
     * @param     array $options            (optional) Input field label
     * @param     mixed  $attributes        (optional) Either a typical HTML attribute string
     *                                      or an associative array
     */
    public function __construct($elementName = null, $elementLabel = null, $options = array(), $attributes = null)
    {
        parent::__construct($elementName, $elementLabel, $attributes);
        if (isset($options['date'])) {
            $this->showDate = $options['date'] == true;
        }
    }

    function getMomentJsDateFormat()
    {
        $date_format = $this->showDate ? parent::getMomentJsDateFormat() . ' ' : '';
        $time_format = Base_RegionalSettingsCommon::time_12h() ? 'hh:mm a' : 'HH:mm';
        return $date_format . $time_format;
    }

    public function getDatetimepickerOptions()
    {
        $options = parent::getDatetimepickerOptions();
        $options['sideBySide'] = true;
        $options['stepping'] = 5;
        return $options;
    }

}
