<?php
/**
 * Flash Charts
 *
 * This module uses Open Flash Chart, displays data as a chart in flash.
 * Copyright (C) 2007 John Glazebrook
 * distributed under the terms of the GNU General Public License version 2 or later
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-libs
 * @subpackage openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_OpenFlashChart extends Module {
	private $ofc;
	private $width="500px";
	private $height="300px";
	private static $included = false;
	
	public function construct() {
		if(!self::$included) {
			$dir = $this->get_module_dir();
			ini_set('include_path',ini_get('include_path').PATH_SEPARATOR.$dir.'/2-lug');
			require_once('OFC/OFC_Chart.php');
			self::$included = true;
		}

		$this->ofc = new OFC_Chart();
	}

	public function & __call($func_name, $args) {
		if (is_object($this->ofc))
			$return = call_user_func_array(array(&$this->ofc, $func_name), $args);
		else
			trigger_error("OpenFlashChart object doesn't exists", E_USER_ERROR);
		return $return;
	}
	
	public function set_width($w) {
		if(is_numeric($w)) $w .= 'px';
		$this->width = $w;
	}
	
	public function set_height($h) {
		if(is_numeric($h)) $h .= 'px';
		$this->height = $h;
	}

	public function body() {
		$md = md5($this->get_path());
		$data = $this->ofc->toString();
		$this->set_module_variable('data',$data);
//		eval_js('var open_flash_chart_data=function() {'.
//					'return "'.Epesi::escapeJS($data).'";'.
//					  '}');
		$url=urlencode($this->get_module_dir().'data.php?id='.CID.'&chart='.$this->get_path());
		print('<span style="display:none">'.md5($data).'</span>');
		print('<object classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" codebase="http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=8,0,0,0" width="'.$this->width.'" height="'.$this->height.'" id="ofc_'.$md.'" align="middle">'.
		     '<param name="allowScriptAccess" value="sameDomain" />'.
		     '<param name="movie" value="'.$this->get_module_dir().'2-lug/open-flash-chart.swf" />'.
			 '<param name="FlashVars" value="data-file='.$url.'" />'.
			 '<param name="wmode" value="opaque">'.
		     '<param name="quality" value="high" />'.
			 '<embed src="'.$this->get_module_dir().'2-lug/open-flash-chart.swf" wmode="opaque" FlashVars="data-file='.$url.'" quality="high" bgcolor="#FFFFFF" width="'.$this->width.'" height="'.$this->height.'" name="open-flash-chart" align="middle" allowScriptAccess="sameDomain" type="application/x-shockwave-flash" pluginspage="http://www.macromedia.com/go/getflashplayer" />'.
			'</object>');
	}

}

?>