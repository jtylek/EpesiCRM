<?php
/**
 * Shared display helpers for the modules/Tests/* example/demo modules.
 *
 * @copyright Copyright &copy; 2026, Janusz Tylek
 * @license MIT
 * @package epesi-tests
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class TestsCommon extends ModuleCommon {

	/**
	 * Prints a Bootstrap page-header block for a demo module's own content,
	 * so the raw widget-under-test doesn't open cold against the page edge.
	 */
	public static function heading($title, $description = null) {
		print '<div class="mb-3">';
		print '<h5 class="mb-1">' . htmlspecialchars($title) . '</h5>';
		if ($description !== null)
			print '<div class="text-body-secondary small">' . $description . '</div>';
		print '</div>';
	}

	private static $accordion_seq = 0;

	/**
	 * Prints the Install/Main/Common source dump every Tests/* module ends
	 * with, as a collapsed-by-default accordion instead of the original bare
	 * "<hr><b>Label</b><br>" separators dumping all three straight onto the
	 * page. A "Source" accordion item reveals a nested accordion with one
	 * item per file, each independently expandable (no data-bs-parent - a
	 * reader comparing Install/Main/Common wants more than one open at once,
	 * unlike a normal single-open-at-a-time accordion). Bootstrap's
	 * collapse/accordion JS is already relied on elsewhere in the app
	 * (Base_Menu's submenu toggle, Base_Box's shell), so no new component
	 * risk here - see AI-shared/theming-and-frontend.md's Tooltip entry for why a
	 * *hover*-driven Bootstrap component was avoided instead; this is a
	 * plain click toggle, not that.
	 *
	 * highlight_file() (via Utils_CatFile) always renders on an implicit
	 * white background - the explicit background/color here isn't
	 * decorative, it's what keeps the code legible once the surrounding page
	 * is the dark theme (see AI-shared/theming-and-frontend.md's tooltip/leightbox
	 * "fixed light chrome" entries for the same trap in other widgets).
	 *
	 * @param Module $module calling module instance (owns pack_module())
	 * @param string $base_dir module directory, e.g. 'modules/Tests/Lang/'
	 * @param array $files label => filename, e.g. ['Install'=>'LangInstall.php', ...]
	 */
	public static function source_card($module, $base_dir, array $files) {
		$uid = 'tests_source_' . (self::$accordion_seq++);

		print '<div class="accordion mt-3" id="' . $uid . '">';
		print '<div class="accordion-item">';
		print '<h2 class="accordion-header">';
		print '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $uid . '-body" aria-expanded="false" aria-controls="' . $uid . '-body">';
		print '<i class="bi bi-code-slash me-2"></i>' . __('Source');
		print '</button>';
		print '</h2>';
		print '<div id="' . $uid . '-body" class="accordion-collapse collapse">';
		print '<div class="accordion-body p-2">';

		print '<div class="accordion">';
		$i = 0;
		foreach ($files as $label => $filename) {
			$sub_id = $uid . '-f' . $i++;
			print '<div class="accordion-item">';
			print '<h2 class="accordion-header">';
			print '<button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $sub_id . '" aria-expanded="false" aria-controls="' . $sub_id . '">';
			print htmlspecialchars($label);
			print '</button>';
			print '</h2>';
			print '<div id="' . $sub_id . '" class="accordion-collapse collapse">';
			print '<div class="accordion-body p-0" style="background:#fff;color:#000;overflow-x:auto;">';
			$module->pack_module(Utils_CatFile::module_name(), $base_dir . $filename);
			print '</div>';
			print '</div>';
			print '</div>';
		}
		print '</div>';

		print '</div>'; // accordion-body
		print '</div>'; // accordion-collapse
		print '</div>'; // accordion-item
		print '</div>'; // accordion
	}
}
?>
