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
	// AdminLTE-only: Base_AdminlteIcons::resolve() looks this up for this
	// module's icon (sidebar menu, ActionBar launcher, admin panels, module
	// indicator, etc.) instead of a central map - see
	// modules/Base/Theme/adminlte_icons.php.
	public static function adminlte_icon() { return 'bi-chat-square-dots'; }

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
	// Reverted by request to a plain native browser tooltip instead - just
	// the title="..." attribute, no JS component at all - which trades
	// visual polish for a guarantee that this feature can never again
	// conflict with anything else on the page, since there is nothing left
	// for it to conflict with.
	private static function to_plain_text($tip) {
		// Native tooltips only ever show plain text, so block-ish
		// boundaries are turned into separators (title="" does render \n)
		// before stripping the rest, so e.g. format_info_tooltip()'s table
		// still reads as "Label: value" one per line instead of one run-on
		// wall of text.
		$plain = preg_replace('#</td>\s*<td[^>]*>#i', ': ', $tip);
		$plain = preg_replace('#</(tr|div|p|li)>|<br\s*/?>#i', "\n", $plain);
		$plain = trim(html_entity_decode(strip_tags($plain), ENT_QUOTES));
		$plain = preg_replace('/[ \t]{2,}/', ' ', $plain);
		return preg_replace("/\n{2,}/", "\n", $plain);
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable tooltip when placing mouse over that element.
	 *
	 * @param string tooltip text
	 * @param boolean help tooltip? (you can turn off help tooltips)
	 * @return string HTML tag attributes
	 */
	public static function open_tag_attrs( $tip, $help=true, $max_width=500 ) {
		if(MOBILE_DEVICE) return '';
		self::show_help();
		if($help && !self::$help_tooltips) return '';
		if (Base_ThemeCommon::is_adminlte_family()) {
			return ' data-epesi-tooltip="1" title="'.htmlspecialchars(self::to_plain_text($tip)).'" ';
		}
		return ' onMouseMove="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.show(this,event,'.$max_width.')" tip="'.htmlspecialchars($tip).'" onMouseOut="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" onMouseUp="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.hide()" ';
	}

	/**
	 * Returns string that when placed as tag attribute
	 * will enable ajax request to set a tooltip when placing mouse over that element.
	 *
	 * @param callback method that will be called to get tooltip content
	 * @param array parameters that will be passed to the callback
	 * @return string HTML tag attributes
	 */
	public static function ajax_open_tag_attrs( $callback, $args, $max_width=300 ) {
		if(MOBILE_DEVICE) return '';

		$tooltip_settings = array('callback'=>$callback, 'args'=>$args);
		$tooltip_id = md5(serialize($tooltip_settings));

		$_SESSION['client']['utils_tooltip']['callbacks'][$tooltip_id] = $tooltip_settings;

		if (Base_ThemeCommon::is_adminlte_family()) {
			// Content isn't known yet - theme_adminlte/tooltip.js's
			// epesi_tooltip_ajax_load(), wired via plain onmouseenter (no
			// Bootstrap/Popper involved, see open_tag_attrs() above), POSTs
			// to the same modules/Utils/Tooltip/req.php the default theme
			// uses on first hover and rewrites the title attribute in
			// place. Native tooltips don't refresh mid-display, so the
			// very first hover on a given element still shows "Loading..."
			// once; the fetched content shows from the next hover onward.
			return ' data-epesi-tooltip="1" title="'.htmlspecialchars(__('Loading...')).'" onmouseenter="epesi_tooltip_ajax_load(this,\''.$tooltip_id.'\')" ';
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
	 * @return string text with tooltip
	 */
	public static function ajax_create( $text, $callback, $args=array(), $max_width=300) {
		return '<span '.self::ajax_open_tag_attrs($callback,$args,$max_width).'>'.$text.'</span>';
	}

    public static function is_tooltip_code_in_str($str)
    {
        return str_contains($str, 'Utils_Toltip.show(') || str_contains($str, 'Utils_Tooltip.load_ajax(')
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
		// typeof-guarded like open_tag_attrs()/ajax_open_tag_attrs() above -
		// js/tooltip.js (and its Utils_Tooltip global) isn't loaded under
		// adminlte (see the bottom of this file), so this would otherwise
		// throw on click there; the leightbox itself still opens via
		// get_open_href()'s own href, just without this tooltip content
		// pre-populating it.
		return Libs_LeightboxCommon::get_open_href('tooltip_leightbox_mode').' onmousedown="if(typeof(Utils_Tooltip)!=\'undefined\')Utils_Tooltip.leightbox_mode(this)" ';
	}
	
}

// The default theme's custom mouse-tracking #tooltip_div is unused under
// adminlte (plain native title="..." tooltips instead, see open_tag_attrs())
// - loading it too would be dead weight, and init_tooltip_div() would inject
// a floating div nothing ever shows. theme_adminlte/tooltip.js is still
// needed for the ajax_open_tag_attrs() variant's on-hover content fetch.
if (Base_ThemeCommon::is_adminlte_family()) {
	load_js('modules/Utils/Tooltip/theme_adminlte/tooltip.js');
} else {
	load_js('modules/Utils/Tooltip/js/tooltip.js');
	Utils_TooltipCommon::init_tooltip_div();
}

?>
