<?php
/**
 * @author Arkadiusz Bisaga, Janusz Tylek, Janusz Tylek <j@epe.si> and Janusz Tylek <j@epe.si>
 * @copyright Copyright &copy; 2006-2020 Janusz Tylek
 * @version 1.9.0
 * @license MIT
 * @package epesi-utils
 * @subpackage generic-browser
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_GenericBrowser_RowObject {
    /**
     * @var Utils_GenericBrowser
     */
    private $GBobj;
    private $num;

    /**
     * @param Utils_GenericBrowser $GBobj
     * @param $num
     */
    public function __construct(Utils_GenericBrowser $GBobj, $num){
        $this->GBobj = $GBobj;
        $this->num = $num;
    }

    /**
     * Adds data to the row in Generic Browser.
     *
     * Each argument fills one field,
     * it can be either a string or an array.
     *
     * If an array is passed it may consists following fields:
     * value - text that will be displayed in the field
     * style - additional css style definition
     * hint - tooltip for the field
     * wrapmode - what wrap method should be used (nowrap, wrap, cut)
     *
     * If a string is passed it will be displayed in the field.
     *
     * @param mixed list of arguments
     */
    public function add_data($args){
        $args = func_get_args();
        $this->GBobj->__add_row_data($this->num,$args);
    }

    /**
     * Adds data to the row in Generic Browser.
     *
     * The argument should be an array,
     * each array entry fills one field,
     * it can be either a string or an array.
     *
     * If an array is passed it may consists following fields:
     * value - text that will be displayed in the field
     * style - additional css style definition
     * hint - tooltip for the field
     *
     * If a string is passed it will be displayed in the field.
     *
     * @param array array with row data
     */
    public function add_data_array(array $arg){
        $this->GBobj->__add_row_data($this->num,$arg);
    }

    /**
     * Adds an action to a row in Generic Browser.
     *
     * All actions are placed in one, additional column.
     * Theme may replace text with icons and to determine which icon to use
     * label lowercase is used.
     *
     * @param string href
     * @param string label
     */
    public function add_action($tag_attrs,$label,$tooltip=null,$icon=null,$order=0,$off=false,$size=1){
        $this->GBobj->__add_row_action($this->num, $tag_attrs,$label,$tooltip,$icon,$order,$off,$size);
    }

    /**
     * Adds a style to a row in Generic Browser.
     *
     * All actions are placed in one, additional column.
     * Theme may replace text with icons and to determine which icon to use
     * label lowercase is used.
     *
     * @param string href
     * @param string label
     */
    public function set_attrs($tag_attrs){
        $this->GBobj->__set_row_attrs($this->num, $tag_attrs);
    }

    /**
     * Adds an info icon to the Generic Browser.
     *
     * @param string tooltip
     */
    public function add_info($tooltip, $leightbox = false){
        $this->GBobj->__add_row_action($this->num, $leightbox?Utils_TooltipCommon::tooltip_leightbox_mode():'','info',$tooltip,null);
    }

    /**
     * Adds an js to call when row is displayed
     *
     * @param string js
     */
    public function add_js($js){
        $this->GBobj->__add_row_js($this->num, $js);
    }

    public function no_actions() {
        $this->GBobj->no_action($this->num);
    }

}