<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 *
 * {html_grid_epesi} - div-based twin of {html_table_epesi} (see
 * function.html_table_epesi.php), built for GenericBrowser's on-screen list
 * grid. Same param contract (loop/cols/rows/table_attr/th_attr/tr_attr/
 * td_attr/row_attrs/trailpad/vdir/hdir/inner/caption) so GenericBrowser_0.php
 * (the sole PHP producer of this data) didn't need to change - only the
 * three browser-facing .tpl call sites (theme/theme_adminlte/
 * theme_adminltedark's default.tpl) switched from {html_table_epesi} to this.
 * theme/pdf.tpl keeps calling {html_table_epesi} unchanged, since a PDF
 * rendering engine needs real <table> layout for reliable columns.
 *
 * Markup uses CSS table-display (display:table/table-row-group/table-row/
 * table-cell, declared once in GenericBrowser's own CSS keyed off the
 * existing .Utils_GenericBrowser/__thead/__tbody/__tr/__th/__td class names)
 * rather than CSS Grid, specifically so column-width negotiation across rows
 * keeps working the same way a real <table> did (a <table>-of-<div>s'
 * "displayed as a table" CSS layout algorithm, not a literal <table>
 * element) - and so per-row wrapper elements stay real boxes (needed for
 * col.js's/table_overflow.js's/sort_fields.js's/sort_nodes.js's DOM hooks -
 * see AI-shared/theming-and-frontend.md), unlike a CSS Grid + display:contents
 * approach which would leave a cloned drag-helper row an invisible,
 * zero-box element.
 *
 * `width="..."` and `nowrap`/`nowrap="..."` are real HTML presentation
 * attributes that only have browser-defined meaning on actual table
 * elements (table/tr/td/th/col/colgroup) - GenericBrowser_0.php's attrs
 * strings (built once, shared with the <table>-emitting plugin above) still
 * use them, so this plugin translates both into an equivalent inline style
 * before emitting each cell's div (see html_grid_epesi_attrs_to_div()).
 */
function smarty_function_html_grid_epesi($params, &$smarty)
{
    $table_attr = 'border="1"';
    $tr_attr = '';
    $th_attr = '';
    $td_attr = '';
    $cols = $cols_count = 3;
    $rows = 3;
    $trailpad = '&nbsp;';
    $vdir = 'down';
    $hdir = 'right';
    $inner = 'cols';
    $caption = '';

    if (!isset($params['loop'])) {
        $smarty->trigger_error("html_grid_epesi: missing 'loop' parameter");
        return;
    }

    foreach ($params as $_key=>$_value) {
        switch ($_key) {
            case 'loop':
                $loop = array();
                $td_attr = array();
                foreach($_value as $k=>$v){
                    $loop[] = $v['label'];
                    $td_attr[] = $v['attrs'];
                }
                break;

            case 'cols':
                if (is_array($_value) && !empty($_value)) {
                    $cols = array();
                    $th_attr = array();
                    foreach($_value as $k=>$v){
                        $cols[] = $v['label'];
                        $th_attr[] = $v['attrs'];
                    }
                    $cols_count = count($cols);
                } elseif (!is_numeric($_value) && is_string($_value) && !empty($_value)) {
                    $cols = explode(',', $_value);
                    $cols_count = count($cols);
                } elseif (!empty($_value)) {
                    $cols_count = (int)$_value;
                } else {
                    $cols_count = $cols;
                }
                break;

            case 'rows':
                $$_key = (int)$_value;
                break;

            case 'table_attr':
            case 'trailpad':
            case 'hdir':
            case 'vdir':
            case 'inner':
            case 'caption':
                $$_key = (string)$_value;
                break;

            case 'tr_attr':
                $$_key = $_value;
                break;
        }
    }

    $loop_count = count($loop);
    if (empty($params['rows'])) {
        $rows = ceil($loop_count/$cols_count);
    } elseif (empty($params['cols'])) {
        if (!empty($params['rows'])) {
            $cols_count = ceil($loop_count/$rows);
        }
    }

    $output = "<div $table_attr role=\"table\">\n";

    if (!empty($caption)) {
        $output .= '<div role="caption">' . $caption . "</div>\n";
    }

    if (is_array($cols)) {
        $cols = ($hdir == 'right') ? $cols : array_reverse($cols);
        $output .= "<div class=\"Utils_GenericBrowser__thead\" role=\"rowgroup\">\n";
        $output .= "<div class=\"Utils_GenericBrowser__tr nonselectable\" role=\"row\">\n";

        for ($r=0; $r<$cols_count; $r++) {
            if ($r==0) $class = 'Utils_GenericBrowser__th first';
            elseif ($r==$cols_count-1) $class = 'Utils_GenericBrowser__th last';
            else $class = 'Utils_GenericBrowser__th';
            $output .= html_grid_epesi_attrs_to_div('div', 'columnheader', $class, html_grid_epesi_cycle('th', $th_attr, $r));
            $output .= $cols[$r];
            $output .= "</div>\n";
        }
        $output .= "</div>\n</div>\n";
    }

    $output .= "<div class=\"Utils_GenericBrowser__tbody\" role=\"rowgroup\">\n";
    for ($r=0; $r<$rows; $r++) {
        $row_attrs = trim(html_grid_epesi_cycle('tr', $tr_attr, $r) . ' ' . ($params['row_attrs'][$r] ?? ''));
        $output .= html_grid_epesi_attrs_to_div('div', 'row', 'Utils_GenericBrowser__tr', $row_attrs);
        $rx =  ($vdir == 'down') ? $r*$cols_count : ($rows-1-$r)*$cols_count;

        for ($c=0; $c<$cols_count; $c++) {
            $x =  ($hdir == 'right') ? $rx+$c : $rx+$cols_count-1-$c;
            if ($inner!='cols') {
                $x = floor($x/$cols_count) + ($x%$cols_count)*$rows;
            }

            if ($x<$loop_count) {
                $output .= html_grid_epesi_attrs_to_div('div', 'cell', '', html_grid_epesi_cycle('td', $td_attr, $x)) . $loop[$x] . "</div>\n";
            } else {
                $output .= html_grid_epesi_attrs_to_div('div', 'cell', '', html_grid_epesi_cycle('td', $td_attr, $x)) . "$trailpad</div>\n";
            }
        }
        $output .= "</div>\n";
    }
    $output .= "</div>\n";
    $output .= "</div>\n";

    return $output;
}

/**
 * Same cycling logic as html_table_epesi.php's own
 * smarty_function_html_table_cycle() - deliberately a private copy, not a
 * shared call, since Smarty's plugin autoloader only ever includes a plugin
 * file when its OWN registered tag is used in a template; a page using only
 * {html_grid_epesi} (never {html_table_epesi}) would otherwise hit a fatal
 * "call to undefined function" the first time this ran.
 */
function html_grid_epesi_cycle($name, $var, $no) {
    if(!is_array($var)) {
        $ret = $var;
    } else {
        $ret = $var[$no % count($var)];
    }

    return ($ret) ? ' '.$ret : '';
}

/**
 * Opens a `<$tag role="$role" class="$extra_class ...">` tag, folding any
 * `width="..."` / `nowrap`/`nowrap="..."` found in $attrs into an inline
 * style (divs don't honor those as HTML presentation attributes the way a
 * real table/td/th does) and merging with $attrs' own `class="..."` (if
 * any) and `style="..."` (if any) rather than dropping them.
 */
function html_grid_epesi_attrs_to_div($tag, $role, $base_class, $attrs)
{
    $style_extra = '';
    if (preg_match('/\bwidth\s*=\s*"([^"]*)"/i', $attrs, $m)) {
        $w = $m[1];
        if ($w !== '' && is_numeric($w)) $w .= 'px';
        if ($w !== '') $style_extra .= 'width:'.$w.';';
        $attrs = preg_replace('/\bwidth\s*=\s*"[^"]*"\s*/i', '', $attrs, 1);
    }
    if (preg_match('/\bnowrap(\s*=\s*"[^"]*")?/i', $attrs, $m)) {
        $style_extra .= 'white-space:nowrap;';
        $attrs = preg_replace('/\bnowrap(\s*=\s*"[^"]*")?\s*/i', '', $attrs, 1);
    }

    $class = $base_class;
    if (preg_match('/\bclass\s*=\s*"([^"]*)"/i', $attrs, $m)) {
        // GenericBrowser_0.php's own cell attrs already carry
        // class="Utils_GenericBrowser__td ..." - merge rather than replace.
        $class = trim($base_class.' '.$m[1]);
        $attrs = preg_replace('/\bclass\s*=\s*"[^"]*"\s*/i', '', $attrs, 1);
    } elseif ($role === 'cell' && $class === '') {
        // Only trailpad cells (beyond $loop_count, no attrs of their own)
        // ever reach here without a class already.
        $class = 'Utils_GenericBrowser__td';
    }

    if ($style_extra) {
        if (preg_match('/\bstyle\s*=\s*"([^"]*)"/i', $attrs, $m)) {
            $attrs = preg_replace('/\bstyle\s*=\s*"[^"]*"/i', 'style="'.htmlspecialchars($m[1].';'.$style_extra, ENT_QUOTES).'"', $attrs, 1);
        } else {
            $attrs .= ' style="'.htmlspecialchars($style_extra, ENT_QUOTES).'"';
        }
    }

    $out = "<$tag role=\"$role\"";
    if ($class !== '') $out .= ' class="'.$class.'"';
    $attrs = trim($attrs);
    if ($attrs !== '') $out .= ' '.$attrs;
    $out .= '>';
    return $out;
}

/* vim: set expandtab: */
