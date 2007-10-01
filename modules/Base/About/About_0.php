<?php
/**
 * About Epesi
 * @author pbukowski@telaxus.com
 * @copyright pbukowski@telaxus.com
 * @license SPL
 * @version 0.1
 * @package base-about
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_About extends Module {
	private function get_info() {
		return '<center>'.
			'<img src="'.$this->get_module_dir().'epesi.png" border=0><br>'.
			'<h3>Version '.EPESI_VERSION.'</h3>'.
			'</center>'.
			'<div align="left">'.
			'<h2>Authors:</h2>'.
			'<ul><li> Janusz Tylek - <i>Software Architect, Project Manager</i><br />'.
			'</li><li> Pawel Bukowski - <i>Project Leader, Software Design , Coding</i><br />'.
			'</li><li> Arek Bisaga - <i>Software Design , Coding</i><br />'.
			'</li><li> Kuba Slawinski - <i>Software Design , Coding</i><br />'.
			'</li><li> Marcin Steczkiewicz - <i>Graphics, Skins, CSS</i><br />'.
			'</li></ul><br>'.
			'<h2>Components used in the framework</h2>'.
			'<ul><li> <b>ADOdb</b> - ADOdb is a PHP &amp; Python database class library to provide more powerful abstractions for performing queries and managing databases. ADOdb also hides the differences between the different databases so you can easily switch dbs without changing code. ADOdb is dual licensed using BSD and LGPL. Copyright (c) 2000-2004 John Lim All rights reserved. <a href="http://adodb.sourceforge.net" title="http://adodb.sourceforge.net" rel="nofollow">http://adodb.sourceforge.net</a>'.
			'</li><li> <b>phpGACL</b> - A PHP class offering Web developers a simple, yet immensely powerful "drop in" permission system to their current Web based applications. Copyright (c) 2007 Mike Benoit <a href="http://phpgacl.sourceforge.net" title="http://phpgacl.sourceforge.net" rel="nofollow">http://phpgacl.sourceforge.net</a>'.
			'</li><li> <b>Smarty</b> - Smarty is a template engine for PHP. More specifically, it facilitates a manageable way to separate application logic and content from its presentation. Smarty is released under the LGPL (Lesser GPL) Smarty Copyright (c) 2002-2005 New Digital Group, Inc. Authors: Monte Ohrt and Andrei Zmievski <a href="http://smarty.php.net" title="http://smarty.php.net" rel="nofollow">http://smarty.php.net</a>'.
			'</li><li> <b>HistoryKeeper</b> - A JavaScript-based library for managing browser history (back button) and providing support for deep linking for Flash and Ajax applications. HistoryKeeper is released under the LGPL (Lesser GPL), version 1.9 (alpha) (2006/04/14) Copyright (c) 2005-2006, Kevin Newman <a href="http://www.unfocus.com/Projects/HistoryKeeper/" title="http://www.unfocus.com/Projects/HistoryKeeper/" rel="nofollow">http://www.unfocus.com/Projects/HistoryKeeper/</a>'.
			'</li><li> <b>FCKeditor</b> - This HTML text editor brings to the web much of the power of desktop editors like MS Word.<a href="http://www.fckeditor.net/" title="http://www.fckeditor.net/">http://www.fckeditor.net/</a>'.
			'</li><li> <b>FPDF</b>'.
			'</li><li> <b>Leightbox</b>'.
			'</li><li> <b>Lytebox</b>'.
			'</li><li> <b>QuickForm</b>'.
			'</li><li> <b>ScriptAculoUs</b> - script.aculo.us provides you with easy-to-use, cross-browser user interface JavaScript libraries to make your web sites and web applications fly. <a href="http://script.aculo.us/" title="http://script.aculo.us/" rel="nofollow">http://script.aculo.us/</a>'.
			'</li></ul><br>'.
			'</div>'.
			'<center>Application developed by<br>'.
			'<img src="'.$this->get_module_dir().'telaxus.jpg"><br>'.
			'Copyright &copy; 2007</center>';
	}
		
	public function info() {
		print($this->get_info());
	}

	public function body() {
		$tip = $this->init_module('Utils/Tooltip');
		print('<div id="aboutepesi" class="leightbox">'.$this->get_info().'<br><a class="lbAction" rel="deactivate">Close</a></div>');
		print('<a rel="aboutepesi" class="lbOn" '.$tip->open_tag_attrs(Base_LangCommon::ts('Base_About','Click to get more info')).'><img src="images/epesi-powered.png" border=0></a>');
	}

	public function caption() {
		return "About Epesi";
	}

}

?>