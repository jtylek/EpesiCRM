<?php
/**
 * Flash Charts
 *
 * This module used Open Flash Chart to display data as a chart in Flash.
 * Copyright (C) 2007 John Glazebrook
 * distributed under the terms of the GNU General Public License version 2 or later
 *
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Janusz Tylek
 * @license MIT
 * @package epesi-libs
 * @subpackage openflashchart
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

// OpenFlashChart -> ChartJS migration, 2026-08-18 (AI-shared/dont-reintroduce.md):
// replaced by modules/Libs/ChartJS - Flash itself has rendered nothing in any
// browser since ~2021 (Adobe killed Flash Player Dec 2020), so this was already
// non-functional, not just legacy. This module is kept installed - not
// uninstalled/deleted - purely so ModuleManager::uninstall() (which needs
// OpenFlashChartInstall.php loadable to run its own uninstall() hook, and refuses
// to proceed if anything still requires it) has nothing to actually do here: no
// code requires Libs_OpenFlashChartInstall anymore (Utils_RecordBrowser_Reports's
// requires() now lists Libs_ChartJSInstall - see that module's own upgrade patch),
// so there's no correctness reason to force existing installs through an uninstall
// step, only a modest disk-space one. The vendored 2-lug/ (open-flash-chart.swf)
// tree and data.php (the Flash movie's own data-fetch endpoint) are both deleted;
// nothing instantiates this class anymore.
class Libs_OpenFlashChart extends Module {
	public function body() {
	}
}

?>
