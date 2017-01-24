<?php

/**
 * HTML class for an autoselect field
 *
 * @category    HTML
 * @package     HTML_QuickForm
 * @author      Arkadiusz Bisaga <abisaga@telaxus.com>
 */
class HTML_QuickForm_autoselect extends HTML_QuickForm_select
{
    private $more_opts_callback = null;
    private $more_opts_args = null;
    private $more_opts_format = null;
    private $__options = array();
    private $on_select = '';
    private $on_selecting = '';
    private $select2_options = array();

    /**
     * Class constructor
     *
     * @param     string    Select name attribute
     * @param     mixed     Label(s) for the select
     * @param     mixed     Data to be used to populate options
     * @param     mixed     Either a typical HTML attribute string or an associative array
     * @since     1.0
     * @access    public
     * @return    void
     */
    function __construct($elementName = null, $elementLabel = null, $options = null, $more_opts_callback = null, $format = null, $attributes = null)
    {
        parent::__construct($elementName, $elementLabel, $options, $attributes);
        if (isset($options)) {
            $this->__options = $options;
        }
        $this->more_opts_callback = $more_opts_callback[0];
        $this->more_opts_args = $more_opts_callback[1];
        $this->more_opts_format = $format;
    } //end constructor

    public function set_select2_options($options) {
      $this->select2_options = $options;
    }

    public static function get_autocomplete_suggestbox($string, $callback, $args, $format = null)
    {
        if (!is_string($string)) $string = '';
        $suggestbox_args = is_array($args)?$args:array();
        array_unshift($suggestbox_args, $string);
        $result = call_user_func_array($callback, $suggestbox_args);
        $res = [];
        foreach ($result as $id => $description) {
            $res[] = ['id' => $id, 'text' => html_entity_decode($description)];
        }
        return $res;
    }

    function toHtml()
    {
        $val = $this->getValue();
        if (isset($val[0]) && $val[0] != '' && !isset($this->__options[$val[0]]) && $this->more_opts_format) {
            $label = call_user_func_array($this->more_opts_format, array($val[0], $this->more_opts_args));
            if ($label !== null) $this->addOption(strip_tags($label), $val[0]);
        }
        return parent::toHtml();
    } //end func toHtml

    function exportValue(&$submitValues, $assoc = false)
    {
        $value = $this->_findValue($submitValues);
        if (is_null($value)) {
            $value = $this->getValue();
        } elseif (!is_array($value)) {
            $value = array($value);
        }
        $cleanValue = $value;
        if (is_array($cleanValue) && !$this->getMultiple()) {
            if (!isset($cleanValue[0])) $cleanValue[0] = '';
            return $this->_prepareValue($cleanValue[0], $assoc);
        } else {
            return $this->_prepareValue($cleanValue, $assoc);
        }
    }

    /**
     * @param $val
     * @return string
     */
    public function getHtml()
    {
        $myName = $this->getName();
        $this->updateAttributes(array('id' => $myName));
        if (!$this->getMultiple()) {
            $attrString = $this->_getAttrString($this->_attributes);
        } else {
            $this->setName($myName . '[]');
            $attrString = $this->_getAttrString($this->_attributes);
            $this->setName($myName);
        }

        $strValues = is_array($this->_values) ? array_map('strval', $this->_values) : array();

        $options = '';
        foreach ($this->_options as $option) {
            if (!empty($strValues)) {
              $found = array_search($option['attr']['value'], $strValues, true);
              if($found!==false) {
                $option['attr']['selected'] = 'selected';
                unset($strValues[$found]);
              }
            }
            $options .= "\t<option" . $this->_getAttrString($option['attr']) . '>' .
                $option['text'] . "</option>\n";
        }
        foreach($strValues as $val) {
          if($this->more_opts_format)
            $label = call_user_func_array($this->more_opts_format, array($val, $this->more_opts_args));
          else {
            $labels = call_user_func_array($this->more_opts_callback, array($val, $this->more_opts_args));
            $label = array_shift($labels);
          }
          $options .= "\t<option value=\"" . $val . '" selected="selected">' .
              $label . "</option>\n";
        }
        $callback = array($this->more_opts_callback, $this->more_opts_args, $this->more_opts_format);

        $key = md5(serialize($callback) . $this->getAttribute('id'));
        $_SESSION['client']['quickform']['autocomplete'][$key] = array(
            'callback' => array(get_called_class(), 'get_autocomplete_suggestbox'),
            'field' => 'q',
            'args' => $callback
        );

        //TODO-PJ: Add pagination

        $cid = CID;
        $hint = __('Start typing to search...');
        $select2_options = json_encode($this->select2_options);
        $select2_js = <<<js
                jQuery("select[name='{$myName}']").select2(jq.extend({
                    placeholder: "{$hint}",
                    ajax: {
                        url: "libs/quickform/autocomplete_update.php",
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                          return {
                              q: params.term,
                              page: params.page,
                              cid: "{$cid}",
                              key: "{$key}"
                          }
                        },
                        processResults: function (data, params) {
                              // parse the results into the format expected by Select2
                              // since we are using custom formatting functions we do not need to
                              // alter the remote JSON data, except to indicate that infinite
                              // scrolling can be used
                              params.page = params.page || 1;
                              return {
                                results: data,
                                pagination: {
                                  more: (params.page * 30) < data.total_count
                                }
                              };
                            },
                    }
                },{$select2_options})).on("select2:selecting", function(e) { {$this->on_selecting} })
                  .on("select2:select", function(e) { {$this->on_select} });
js;

        $select2 = <<<html
                    <select style="width: 100%" {$attrString}>
                      {$options}
                    </select>
html;

        eval_js($select2_js);
        return $select2;
    } //end func toHtml

    public function on_selecting($js) {
      $this->on_selecting = $js;
    }

    public function on_select($js) {
      $this->on_select = $js;
    }

}

?>
