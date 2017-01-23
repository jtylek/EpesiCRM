<?php

/**
 * HTML class for an autocomplete field
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Pawel Bukowski <pbukowski@telaxus.com>
 */
class HTML_QuickForm_autocomplete extends HTML_QuickForm_autoselect {

    /**
     * Class constructor
     *
     * @param string    $elementName    (optional)Input field name attribute
     * @param string    $elementLabel   (optional)Input field label
     * @param mixed 	$callback		(optional)Method callback that will be used to populate table
     * @param mixed     $attributes     (optional)Either a typical HTML attribute string
     *                                      or an associative array
     * @return void
     */
    function __construct($elementName=null, $elementLabel=null, $callback=null, $args=null, $attributes=null) {
        parent::__construct($elementName, $elementLabel, array(),array($callback),null,$attributes);
        $this->set_select2_options(array('tags'=>true));
    }

  /*  public static function get_autocomplete_suggestbox($string, $callback, $args, $format = null)
    {
        return array_merge(array(array('id'=>$string,'text'=>$string)),parent::get_autocomplete_suggestbox($string,$callback,$args,$format));
    }*/
}
?>
