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

    /**
     * Utils_RecordBrowser_CsvExport constructor.
     *
     * @param string                          $tab   Recordset identifier
     * @param array|Utils_RecordBrowser_Crits $crits Crits
     * @param array                           $order Order
     * @param bool                            $admin RB Admin mode - list inactive records
     */
    public function __construct($tab, $crits = array(), $order = array(), $admin = false)
    {
        $this->tab = $tab;
        $this->crits = $crits;
        $this->order = $order;
        $this->admin = $admin;
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
        $v = str_replace($currency_decimal_signs[$symbol], '.', $v);
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
            __('Record ID'),
            __('Created on'),
            __('Created by'),
            __('Edited on'),
            __('Edited by'),
        );
        foreach ($tab_info as $v) {
            if (!$v['export']) {
                continue;
            }
            $cols[] = _V($v['name']);
            if ($v['style'] == 'currency') {
                $cols[] = _V($v['name']) . ' - ' . __('Currency');
            }
        }
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, $cols);
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
                $rec[] = $details['created_on'];
                $rec[] = Base_UserCommon::get_user_label($details['created_by'], true);
                $rec[] = $details['edited_on'];
                $rec[] = $details['edited_by'] ? Base_UserCommon::get_user_label($details['edited_by'], true) : '';
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
                                    $final[] = $this->rb_csv_export_format_currency_value($v[0], $v[1]) . ' ' . $currency_codes[$v[1]];
                                }
                            }
                            $rec[] = implode('; ', $final);
                            $rec[] = '---';
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
                        $rec[] = trim($val);
                    }
                }
                fputcsv($f, $rec);
            }
        }
    }
}
