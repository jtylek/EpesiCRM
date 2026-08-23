<?php
/** 
 * @author Kuba Slawinski <kslawinski@telaxus.com> and Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek 
 * @version 1.0
 * @license MIT 
 * @package epesi-utils 
 * @subpackage tooltip
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Utils_TooltipCommon extends ModuleCommon {
	// AdminLTE-only: Base_BootstrapIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/bootstrap_icons.php.
	public static function bootstrap_icon() { return 'bi-chat-square-dots'; }

	public static function user_settings(){
		return array(__('Misc')=>array(
			array('name'=>'help_tooltips','label'=>__('Show help tooltips'),'type'=>'checkbox','default'=>1)
			));
	}

	private static $help_tooltips;
	private static function show_help() {
		if(!isset(self::$help_tooltips))
			self::$help_tooltips = Base_User_SettingsCommon::get(Utils_TooltipCommon::module_name(),'help_tooltips');
	}
	
	public static function init_tooltip_div(){
		if(!isset($_SESSION['client']['utils_tooltip']['div_exists'])) {
			$smarty = Base_ThemeCommon::init_smarty();
			$smarty->assign('tip','<span id="tooltip_text"></span>');
			ob_start();
			@Base_ThemeCommon::display_smarty($smarty,'Utils_Tooltip');
			$tip_th = ob_get_clean();
			eval_js('Utils_Tooltip.create_block(\''.Epesi::escapeJS($tip_th,false).'\')',false);
			$_SESSION['client']['utils_tooltip']['div_exists'] = true;
		}
		on_exit(array('Utils_TooltipCommon', 'hide_tooltip'),null,false);
	}
	
	public static function hide_tooltip() {
		eval_js('Utils_Tooltip.hide()');
	}

	// Bootstrap's own JS Tooltip component was tried here first and, across
	// three separate rounds, kept conflicting with other hover-driven
	// scripts already present in this app in ways that were hard to pin
	// down precisely and broke real functionality each time (see
	// adminlte-css-conflicts.md's "e:load"/tooltip entries for the history).
	// Reverted by request to a plain native browser tooltip - just the
	// title="..." attribute, no JS component at all. Restyling to match the
	// sidebar's grey/black scheme (2026-08) still had to avoid that same
	// failure mode, since native tooltips can't be recolored with CSS at
	// all. A pure CSS ::after popup (no JS at all) was tried next, but had
	// to be dropped too: it's clipped by any ancestor with overflow:hidden,
	// which turned out to include plain Bootstrap .card containers (used
	// for rounded corners on dashboard applets, admin panels, etc.), not
	// just RecordBrowser/GenericBrowser table cells. Both open_tag_attrs()
	// and ajax_open_tag_attrs() now render the same small
	// theme_adminltedark/tooltip.js-driven popup (a real element appended to
	// <body> on hover, escaping any ancestor's clipping) - see that file's
	// header comment for why this is a fundamentally different, much lower
	// risk to that same failure mode than the earlier Bootstrap component
	// attempts: no component, no event delegation, just a plain
	// mouseenter/mouseleave pair.
	// Splits on $delimiter_regex, trims/collapses whitespace within each
	// piece, drops any that end up empty, and rejoins with $join. A plain
	// "collapse 2+ newlines into 1" regex (the previous approach) missed
	// lines that were only ever template indentation (a run of tabs/spaces
	// between two tags, e.g. changes_list.tpl's per-cell divs each on their
	// own source line) - those collapse to a single space, not nothing, so
	// they never matched as "blank" and survived as stray near-empty lines
	// in the popup. Collapsing only [ \t]{2,} (2+ spaces/tabs) wasn't enough
	// either: the role="cell"/"columnheader" join above only replaces the
	// exact "</div>...<div...>" span between two cells, leaving each cell's
	// OWN internal template whitespace (a single "\n\t\t" before/after its
	// text, from the source .tpl's own indentation) sitting untouched right
	// next to the newly-inserted " | " - visible as literal stray newlines
	// splitting "Field | Old value" back across lines. Collapsing every
	// \s+ run (not just space/tab, and not just 2+) catches that too.
	private static function collapse_blank_lines($text, $delimiter_regex, $join) {
		$lines = preg_split($delimiter_regex, $text);
		$lines = array_map(function($l) {
			return trim(preg_replace('/\s+/', ' ', $l));
		}, $lines);
		return implode($join, array_filter($lines, function($l) { return $l !== ''; }));
	}

	public static function to_plain_text($tip) {
		// The tooltip popup only ever shows plain text, so block-ish
		// boundaries are turned into separators before stripping the rest,
		// so e.g. format_info_tooltip()'s table still reads as
		// "Label: value" one per line instead of one run-on wall of text.
		$plain = preg_replace('#</td>\s*<td[^>]*>#i', ': ', $tip);
		// Same idea for the CSS-Grid-based table replacements some templates
		// use instead of a real <table> (e.g. Utils_RecordBrowser's
		// changes_list.tpl, "Field/Old value/New value" - real <div>s with
		// role="row"/"columnheader"/"cell" restoring ARIA table semantics,
		// see that file's own header comment) - adjacent cells within one
		// row joined onto a single line instead of each getting its own.
		$plain = preg_replace('#</div>\s*<div[^>]*\brole="(?:cell|columnheader)"[^>]*>#i', ' | ', $plain);
		$plain = preg_replace('#</(tr|div|p|li)>|<br\s*/?>#i', "\n", $plain);
		$plain = html_entity_decode(strip_tags($plain), ENT_QUOTES);
		return self::collapse_blank_lines($plain, '/\n/', "\n");
	}

	// Like to_plain_text() but keeps a small safe-formatting subset
	// (<strong>/<b>/<br>) instead of stripping every tag, since
	// open_tag_attrs()'s popup renders this via innerHTML
	// (theme_adminltedark/tooltip.js's epesi_tooltip_show()), not plain
	// textContent - lets e.g. Utils_RecordBrowserCommon::get_html_record_info()
	// bold its "Record ID" line. Deliberately does NOT html_entity_decode
	// like to_plain_text() does: that round-trip is only safe when the
	// result is consumed as inert text, never re-parsed as HTML - any
	// entities already present are left alone and rendered natively by the
	// browser instead. ajax_open_tag_attrs()'s content keeps going through
	// to_plain_text() + textContent (see tooltip.js) by default, since its
	// req.php response has already been through html_entity_decode
	// server-side, which innerHTML would be unsafe to re-parse - unless the
	// caller opted into $safe_html (see ajax_open_tag_attrs()'s own doc), in
	// which case req.php routes through here instead and tooltip.js renders
	// it via innerHTML too; $keep_table is a further, independent opt-in on
	// top of that (req.php reads both flags separately from the same
	// session-stored tooltip_settings).
	//
	// $keep_table: for content built from a real <table> (currently just
	// Utils_RecordBrowser's theme_adminltedark/changes_list.tpl, a
	// "Field/Old value/New value" changes grid) - keeps table/colgroup/col/
	// row/cell tags intact instead of flattening them to "Field: Old
	// value"-style lines, so the browser aligns the columns instead of the
	// columns running together at whatever width each value happens to be.
	// The <colgroup>/<col> pair is what lets the table's own CSS
	// (.epesi-tooltip-popup table, theme_adminltedark/default.css) declare
	// fixed per-column widths - without it, table-layout:fixed has no
	// reliable per-column sizing hint on a table whose first row is one of
	// changes_list.tpl's colspan="3" group_header/message rows. Deliberately its
	// own allowlist rather than folding into the default one: interactive/
	// resource tags (<a>, <img>, ...) stay excluded either way, but the
	// default allowlist's </td><td> and role="cell" flattening would corrupt
	// a real table's structure the moment it also carries these tags.
	public static function to_safe_html($tip, $keep_table = false) {
		if ($keep_table) {
			$safe = preg_replace('#</(div|p|li)>#i', '<br>', $tip);
			$safe = strip_tags($safe, '<strong><b><br><table><colgroup><col><tr><th><td>');
			return self::collapse_blank_lines($safe, '#<br\s*/?>#i', '<br>');
		}
		$safe = preg_replace('#</td>\s*<td[^>]*>#i', ': ', $tip);
		// See to_plain_text()'s matching comment - CSS-Grid table
		// replacements (role="cell"/"columnheader") join their row's cells
		// onto one line the same way a real <table>'s </td><td> does above.
		$safe = preg_replace('#</div>\s*<div[^>]*\brole="(?:cell|columnheader)"[^>]*>#i', ' | ', $safe);
		$safe = preg_replace('#</(tr|div|p|li)>#i', '<br>', $safe);
		$safe = strip_tags($safe, '<strong><b><br>');
		return self::collapse_blank_lines($safe, '#<br\s*/?>#i', '<br>');
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable tooltip when placing mouse over that element.
	 *
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @param int max_width unused under adminlte (fixed via .epesi-tooltip-popup's own max-width); kept for the legacy theme's Utils_Tooltip.show()
	 * @param bool keep a real <table>'s structure instead of flattening it (see to_safe_html()'s $keep_table doc) - opt in only when $tip's HTML is known to be safe to keep, e.g. Utils_GenericBrowser\RowObject::add_info()'s Watchdog changes-list caller
	 * @return string HTML tag attributes
	 */
	public static function open_tag_attrs( $tip, $help=true, $max_width=500, $keep_table=false ) {
		if(MOBILE_DEVICE) return '';
		self::show_help();
		if($help && !self::$help_tooltips) return '';
		if (Base_ThemeCommon::is_adminlte_family()) {
			// data-tooltip holds the popup's content (safe-HTML subset, see
			// to_safe_html()); theme_adminltedark/tooltip.js's
			// epesi_tooltip_show() reads it on hover and shows/positions the
			// popup via innerHTML (theme_adminltedark/default.css's
			// .epesi-tooltip-popup). aria-label carries the fully-plain
			// version, space-joined, for screen readers, since a data-*
			// attribute has no accessibility semantics of its own.
			$html = self::to_safe_html($tip, $keep_table);
			$plain = self::to_plain_text($tip);
			return ' data-epesi-tooltip="1" data-tooltip="'.htmlspecialchars($html).'" aria-label="'.htmlspecialchars(str_replace("\n", ' ', $plain)).'" onmouseenter="epesi_tooltip_show(this)" ';
		}
		return ' onMouseMove="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.show(this,event,'.$max_width.')" tip="'.htmlspecialchars($tip).'" onMouseOut="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" onMouseUp="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" ';
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable ajax request to set a tooltip when placing mouse over that element.
	 *
	 * @param callback method that will be called to get tooltip content
	 * @param array parameters that will be passed to the callback
	 * @param int unused under adminlte (fixed via .epesi-tooltip-popup's own max-width); kept for the legacy theme's Utils_Tooltip.load_ajax()
	 * @param bool render the callback's HTML via to_safe_html($content,$keep_table)+innerHTML (keeps <strong>/<b>/<br>) instead of the default to_plain_text()+textContent flatten - opt in only for callbacks whose HTML is safe to keep
	 * @param bool passed through to to_safe_html()'s $keep_table - only meaningful when $safe_html is true; keeps a real <table>'s structure (e.g. Watchdog's Field/Old value/New value changes grid) instead of flattening rows to "Label: value" lines
	 * @return string HTML tag attributes
	 */
	public static function ajax_open_tag_attrs( $callback, $args, $max_width=300, $safe_html=false, $keep_table=false ) {
		if(MOBILE_DEVICE) return '';

		$tooltip_settings = array('callback'=>$callback, 'args'=>$args, 'safe_html'=>$safe_html, 'keep_table'=>$keep_table);
		$tooltip_id = md5(serialize($tooltip_settings));

		$_SESSION['client']['utils_tooltip']['callbacks'][$tooltip_id] = $tooltip_settings;

		if (Base_ThemeCommon::is_adminlte_family()) {
			// Content isn't known yet - deliberately a separate
			// data-tooltip-ajax attribute (not data-tooltip), since
			// theme_adminltedark/tooltip.js's epesi_tooltip_ajax_load()
			// (wired via plain onmouseenter, no Bootstrap/Popper involved)
			// needs to tell "show this placeholder now" apart from
			// open_tag_attrs()'s "content already known" case. It POSTs to
			// the same modules/Utils/Tooltip/req.php the default theme uses
			// on first hover, then rewrites the popup's content in place
			// once the ajax call returns, so (unlike the old title-attribute
			// version) it updates live rather than needing a second hover.
			// data-tooltip-html tells tooltip.js to render the (eventual)
			// response via innerHTML instead of textContent - only set when
			// $safe_html is true, matching the safe_html flag req.php reads
			// back out of $tooltip_settings above.
			$html_attr = $safe_html ? ' data-tooltip-html="1"' : '';
			return ' data-epesi-tooltip="1" data-tooltip-ajax="'.htmlspecialchars(__('Loading...')).'"'.$html_attr.' onmouseenter="epesi_tooltip_ajax_load(this,\''.$tooltip_id.'\')" ';
		}
		$loading_message = '<center><img src='.Base_ThemeCommon::get_template_file('Utils_Tooltip','loader.gif').' /><br/>'.__('Loading...').'</center>';
		return ' onMouseMove="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.load_ajax(this,event,'.$max_width.')" tip="'.$loading_message.'" tooltip_id="'.$tooltip_id.'" onMouseOut="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" onMouseUp="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" ';
	}

	/**
	 * Returns string that if displayed will create text with tooltip.
	 *
	 * @param string text
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string text with tooltip
	 */
	public static function create( $text, $tip, $help=true, $max_width=300) {
		self::show_help();
		if((!$help || self::$help_tooltips) && is_string($tip) && $tip!=='')
			return '<span '.self::open_tag_attrs($tip,$help,$max_width).'>'.$text.'</span>';
		else
			return $text;
	}

	/**
	 * Returns string that if displayed will create text with tooltip loaded via ajax.
	 *
	 * @param string text
	 * @param mixed callback
	 * @param array arguments for the callback
	 * @param int max_width unused under adminlte, see open_tag_attrs()
	 * @param bool render the callback's HTML via to_safe_html() (keeps <strong>/<b>/<br>) instead of flattening it to plain text - see ajax_open_tag_attrs()'s $safe_html doc
	 * @return string text with tooltip
	 */
	public static function ajax_create( $text, $callback, $args=array(), $max_width=300, $safe_html=false) {
		return '<span '.self::ajax_open_tag_attrs($callback,$args,$max_width,$safe_html).'>'.$text.'</span>';
	}

    public static function is_tooltip_code_in_str($str)
    {
        return str_contains($str, 'Utils_Tooltip.show(') || str_contains($str, 'Utils_Tooltip.load_ajax(')
            || str_contains($str, 'data-epesi-tooltip="1"');
    }

	/**
	* Returns a 2-column formatted table
	*
	* @param array keys are captions, values are values
	*/
	public static function format_info_tooltip($arg) {
		if(!is_array($arg) || empty($arg)) return '';
		$table='<table width="280" cellpadding="2">';
		foreach ($arg as $k=>$v){
			if ($v===null || (is_string($v) && trim(strip_tags($v))==='')) continue;
			$table.='<tr><td width="90"><strong>';
			$table.=$k.'</strong></td><td bgcolor="white" style="word-wrap: break-word;">';
			$table.= $v; // Value
			$table.='</td></tr>';
		}
		$table.='</table>';

        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        $return = $purifier->purify($table);

		return $return;
	}

	public static function tooltip_leightbox_mode() {
		static $init = null;
		if (!isset($_REQUEST['__location'])) $loc = true;
		else $loc = $_REQUEST['__location'];
		if ($init!==$loc) {
			Base_ThemeCommon::load_css(Utils_TooltipCommon::module_name(),'leightbox_mode');
			Libs_LeightboxCommon::display('tooltip_leightbox_mode', '<center><span id="tooltip_leightbox_mode_content" /></center>');
			$init = $loc;
		}
		// The leightbox itself always opens via get_open_href()'s own href
		// (Libs_Leightbox's lbOn-click handling is theme-independent) - only
		// *populating* it needs a theme-specific script, since it has to
		// read the same element's hover-tooltip content back out, and
		// open_tag_attrs()/ajax_open_tag_attrs() render completely different
		// attributes per theme (legacy: a plain tip="..." attribute, read by
		// js/tooltip.js's Utils_Tooltip.leightbox_mode(); adminlte:
		// data-tooltip/data-tooltip-ajax, read by theme_adminltedark/
		// tooltip.js's epesi_tooltip_leightbox_populate() - see that
		// function's own comment for why it can't just always use
		// innerHTML). Both are typeof-guarded so this never throws on
		// whichever theme's tooltip.js isn't loaded.
		if (Base_ThemeCommon::is_adminlte_family()) {
			$onmousedown = ' onmousedown="if(typeof(epesi_tooltip_leightbox_populate)!=\'undefined\')epesi_tooltip_leightbox_populate(this)" ';
		} else {
			$onmousedown = ' onmousedown="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.leightbox_mode(this)" ';
		}
		return Libs_LeightboxCommon::get_open_href('tooltip_leightbox_mode').$onmousedown;
	}
	
}

// The default theme's custom mouse-tracking #tooltip_div is unused under
// adminlte (see open_tag_attrs()/ajax_open_tag_attrs() above) - loading it
// too would be dead weight, and init_tooltip_div() would inject a floating
// div nothing ever shows. theme_adminltedark/default.css has the CSS-only
// [data-tooltip] popup; theme_adminltedark/tooltip.js is still needed for
// the ajax_open_tag_attrs() variant's on-hover content fetch + popup.
if (Base_ThemeCommon::is_adminlte_family()) {
	Base_ThemeCommon::load_css(Utils_TooltipCommon::module_name(),'default');
	load_js('modules/Utils/Tooltip/theme_adminltedark/tooltip.js');
} else {
	load_js('modules/Utils/Tooltip/js/tooltip.js');
	Utils_TooltipCommon::init_tooltip_div();
}

?>
