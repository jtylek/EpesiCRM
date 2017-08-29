<?php

defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Class to export RB data to CSV
 */
class Utils_RecordBrowser_CsvExport
{
    private $tab;
    private $crits;
    private $order;
    private $admin;
    private $charset;
    private $field_separator;
    private $decimal_separator;
    private $end_line_type;
    private $text_space_indicator;
    private $text_space_separator;
    private $error_charset_conv;
    /**
     * Utils_RecordBrowser_CsvExport constructor.
     *
     * @param string                          $tab   Recordset identifier
     * @param array|Utils_RecordBrowser_Crits $crits Crits
     * @param array                           $order Order
     * @param bool                            $admin RB Admin mode - list inactive records
     */
    public function __construct($tab, $crits = array(), $order = array(), $admin = false, $config = array())
    {
        $this->tab = $tab;
        $this->crits = $crits;
        $this->order = $order;
        $this->admin = $admin;
        
        $def_config = Utils_CommonDataCommon::get_array('System/csv_export_params');
        foreach ($def_config as $ck => $cv) {
            if (!array_key_exists($ck,$config)) $config[$ck] = $cv;
        }        

        $this->charset          = $config['charset'];
        $this->field_separator  = $config['field_separator'];
        $this->decimal_separator= $config['decimal_separator'];
        
        switch (strtoupper($config['end_line_type'])){
            case ('LIN'):
            case ('LINUX'):
            case ('UNI'):
            case ('UNIX'):
                $end_line_type = "\n";
                break;
            case ('WIN'):                
            case ('WINDOWS'):
                $end_line_type = "\r\n";
                break;
            case ('MAC'):
            case ('MACINTOSH'):
                $end_line_type = "\r";
                break;
            default:
                $end_line_type = "\n";
                break;
        }
        $this->end_line_type         = $end_line_type;
        $this->text_space_indicator  = $config['text_space_indicator'];
        $this->text_space_separator  = $config['text_space_separator'];
        if (!$this->text_space_indicator){ //if we want separator around string contained spaces, we should disable separator character from oryginal string.
            $this->text_space_separator = chr(254); //Unfortunately can not be empty - this char is not easy accesible from keyboard, so we can use it.
        }
        $this->error_charset_conv = 0;
    }    

    function rb_csv_export_format_currency_value($v, $symbol)
    {
        static $currency_decimal_signs = null;
        static $currency_thou_signs;
        if ($currency_decimal_signs === null) {
            $currency_decimal_signs = DB::GetAssoc('SELECT symbol, decimal_sign FROM utils_currency');
            $currency_thou_signs = DB::GetAssoc('SELECT symbol, thousand_sign FROM utils_currency');
        }
        $v = str_replace($currency_thou_signs[$symbol], '', $v);
        $v = str_replace($currency_decimal_signs[$symbol], $this->decimal_separator, $v);
        return $v;
    }

    /**
     * Print CSV file to output
     */
    public function to_output()
    {
        $f = fopen('php://output', 'w');
        $this->to_handle($f);
        fclose($f);
    }

    /**
     * Create CSV file
     *
     * @param string $file File location
     */
    public function to_file($file)
    {
        $f = fopen($file, 'w');
        $this->to_handle($f);
        fclose($f);
    }

    /**
     * Get CSV as string
     *
     * @return string CSV content
     */
    public function get_as_string()
    {
        $f = fopen('php://temp', 'w');
        $this->to_handle($f);
        rewind($f);
        return stream_get_contents($f);
    }

    /**
     * Print CSV file to resource handle
     *
     * @param resource $f
     */
    public function to_handle($f)
    {
        set_time_limit(0);
        $tab_info = Utils_RecordBrowserCommon::init($this->tab);
        $cols = array(
            $this->utf_to_charset(__('Record ID')),
            $this->utf_to_charset(__('Created on')),
            $this->utf_to_charset(__('Created by')),
            $this->utf_to_charset(__('Edited on')),
            $this->utf_to_charset(__('Edited by')),
        );
        foreach ($tab_info as $v) {
            if (!$v['export']) {
                continue;
            }
            $cols[] = $this->utf_to_charset(_V($v['name']));
            if ($v['style'] == 'currency') {
                $cols[] = $this->utf_to_charset(_V($v['name']) . ' - ' . __('Currency'));
            }
        }
        //fwrite($f, "\xEF\xBB\xBF"); //oryginal
        //Preparing buffering for eol conversion and separator disabling
        $end_line_type          = $this->end_line_type;
        $text_space_separator   = $this->text_space_separator;
        $text_space_indicator   = $this->text_space_indicator;        
        ob_start(function($buffer) use ($end_line_type,$text_space_indicator,$text_space_separator) {
            return Utils_RecordBrowser_CsvExport::eol_conversion($buffer, $end_line_type,$text_space_indicator,$text_space_separator);
        });
        fputcsv($f, $cols, $this->field_separator, chr($this->text_space_separator));
        $currency_codes = DB::GetAssoc('SELECT symbol, code FROM utils_currency');

        $records = true;
        $chunk = 100;
        $limit = array('numrows' => $chunk, 'offset' => 0);
        while ($records) {
            $records = Utils_RecordBrowserCommon::get_records($this->tab, $this->crits, array(), $this->order, $limit, $this->admin);
            $limit['offset'] += $chunk;
            foreach ($records as $r) {
                $has_access = Utils_RecordBrowserCommon::get_access($this->tab, 'view', $r);
                if (!$has_access) {
                    continue;
                }
                $rec = array(
                    $r['id'],
                );
                $details = Utils_RecordBrowserCommon::get_record_info($this->tab, $r['id']);
                $rec[] = $this->utf_to_charset($details['created_on']);
                $rec[] = $this->utf_to_charset(Base_UserCommon::get_user_label($details['created_by'], true));
                $rec[] = $this->utf_to_charset($details['edited_on']);
                $rec[] = $this->utf_to_charset($details['edited_by'] ? Base_UserCommon::get_user_label($details['edited_by'], true) : '');
                foreach ($tab_info as $field_name => $v) {
                    if (!$v['export']) {
                        continue;
                    }
                    ob_start();
                    if (!isset($has_access[$v['id']]) || !$has_access[$v['id']]) {
                        $val = '';
                    } else {
                        $val = Utils_RecordBrowserCommon::get_val($this->tab, $field_name, $r, true, $v);
                    }
                    ob_end_clean();
                    $val = str_replace('&nbsp;', ' ', htmlspecialchars_decode(strip_tags(preg_replace('/\<[Bb][Rr]\/?\>/', "\n", $val))));
                    if ($v['style'] == 'currency') {
                        $val = str_replace(' ', '_', $val);
                        $val = explode(';', $val);
                        if (isset($val[1])) {
                            $final = array();
                            foreach ($val as $v) {
                                $v = explode('_', $v);
                                if (isset($v[1])) {
                                    $final[] = $this->utf_to_charset($this->rb_csv_export_format_currency_value($v[0], $v[1]) . ' ' . $currency_codes[$v[1]]);
                                }
                            }
                            $rec[] = implode('; ', $final);
                            $rec[] = $this->utf_to_charset('---');
                            continue;
                        }
                        $val = explode('_', $val[0]);
                        $currency_symbol = '---';
                        $last = end($val);
                        $first = reset($val);
                        if (isset($currency_codes[$first])) {
                            $currency_symbol = array_shift($val);
                        } elseif (isset($currency_codes[$last])) {
                            $currency_symbol = array_pop($val);
                        }
                        $value = implode('', $val);
                        if (isset($currency_codes[$currency_symbol])) {
                            $rec[] = $this->rb_csv_export_format_currency_value($value, $currency_symbol);
                            $rec[] = $currency_codes[$currency_symbol];
                        } else {
                            $rec[] = $value;
                            $rec[] = $currency_symbol;
                        }
                    } else {
                        $rec[] = $this->utf_to_charset(str_replace(array(\PHP_EOL,"&#8194;","&rArr;",$this->text_space_separator), array(' ','','',''),trim($val)));
                    }
                }
                fputcsv($f, $rec, $this->field_separator, chr($this->text_space_separator));
            }
        }
        if ($this->error_charset_conv){
            $error_text = __('Missing PHP module iconv. You should enable it in PHP environment or use only UTF-8 charset for CSV output, not '.$this->charset.'.');
            trigger_error($error_text, E_USER_NOTICE); //if we want error.
            //fputcsv($f, array('WARNING',$error_text), $this->field_separator, $this->text_space_separator); //if we want not converted output with additional warning at the end.
        }
    }
    
    public function utf_to_charset($str){ //Conversion to requested charset
        $text_space_separator = '';
        if ($this->text_space_indicator){ //if we want use separator we should clean string from that character.
            $text_space_separator = $this->text_space_separator;
        }
        $ret = str_replace(array($this->field_separator,$text_space_separator),array(' ',''),$str); //We do not want field separator char inside oryginal text
        if (strtoupper($this->charset) <> 'UTF-8'){
            $ret =  $this->charset_conversion($ret);
        }
        return $ret;
    }
    public function eol_conversion($string, $end_line_type, $text_space_indicator, $text_space_separator) {
        if ($text_space_indicator){ //do not remove space indicator character
            $text_space_separator = '';
        }
        return str_replace(array(PHP_EOL,$text_space_separator), array($end_line_type,''), $string);
    }
    public function charset_conversion($string){
        if (function_exists('iconv')) {
            $ret =  iconv('UTF-8', $this->charset.'//TRANSLIT', $string);
        }else{
            $this->error_charset_conv = 1;//there is no function for charset translation
            $ret = $string;
        }
        return $ret;
    }
}
