<?php
/**
 * Chart.js - https://www.chartjs.org
 * Copyright (c) 2014-2024 Chart.js Contributors
 * Released under the MIT License.
 *
 * Canvas-based line/bar chart module. Replaces Libs/OpenFlashChart (Flash,
 * non-functional in every browser since ~2021 - see
 * AI-shared/deliberate-removals.md) - Utils/RecordBrowser/Reports is the
 * only real caller.
 *
 * @license MIT
 * @package epesi-libs
 * @subpackage ChartJS
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Libs_ChartJS extends Module {
	const TYPE_LINE = 'line';
	const TYPE_BAR = 'bar';

	private $type = self::TYPE_LINE;
	private $title = '';
	private $labels = array();
	private $datasets = array();
	private $y_max = null;
	private $width = '950px';
	private $height = '400px';

	public function construct() {
		load_js('modules/Libs/ChartJS/4.5.1/chart.umd.min.js','');
		load_js('modules/Libs/ChartJS/cj.js','');
	}

	public function set_type($type) {
		$this->type = $type;
	}

	public function set_title($title) {
		$this->title = $title;
	}

	public function set_labels(array $labels) {
		$this->labels = $labels;
	}

	// $color: any CSS color string. Kept as one line per series (not filled
	// area) for the 'line' type, matching OFC_Charts_Line's own plain-line
	// look this replaces.
	public function add_dataset($label, $color, array $values) {
		$this->datasets[] = array('label'=>(string)$label,'color'=>$color,'values'=>$values);
	}

	// Optional - Chart.js auto-scales the y axis reasonably on its own;
	// callers only need this to match a value computed across multiple
	// sibling charts that must share the same visual scale (see
	// Reports_0.php's own $max tracking).
	public function set_y_max($max) {
		$this->y_max = $max;
	}

	public function set_width($w) {
		$this->width = is_numeric($w) ? $w.'px' : $w;
	}

	public function set_height($h) {
		$this->height = is_numeric($h) ? $h.'px' : $h;
	}

	public function body() {
		if (empty($this->datasets)) return;

		$datasets = array();
		foreach ($this->datasets as $ds) {
			$datasets[] = array(
				'label' => $ds['label'],
				'data' => $ds['values'],
				'borderColor' => $ds['color'],
				'backgroundColor' => $ds['color'],
				'fill' => false,
			);
		}
		$scales = array('y'=>array('beginAtZero'=>true));
		if ($this->y_max !== null) $scales['y']['max'] = $this->y_max;

		$config = array(
			'type' => $this->type,
			'data' => array('labels'=>$this->labels,'datasets'=>$datasets),
			'options' => array(
				'responsive' => true,
				'maintainAspectRatio' => false,
				'plugins' => array('title'=>array('display'=>$this->title !== '','text'=>$this->title)),
				'scales' => $scales,
			),
		);

		// Same uniqueness scheme OpenFlashChart_0.php used (md5 of this
		// instance's own module path) - several sibling chart instances can
		// render on one page (draw_chart()'s numeric+currency pair,
		// draw_summary_chart()'s 4 charts), each needs its own canvas id.
		$id = 'chartjs_'.md5($this->get_path());
		print('<div style="width:'.htmlspecialchars($this->width).';height:'.htmlspecialchars($this->height).';max-width:100%;">'.
		      '<canvas id="'.$id.'"></canvas>'.
		      '</div>');
		// cj.js's e:load handler (queued config survives the AJAX-push DOM
		// swap that follows - see that file's own comment) does the actual
		// `new Chart(...)` once the canvas element is really in the DOM.
		eval_js('charts_hib["'.$id.'"]='.json_encode($config).';');
	}

}

?>
