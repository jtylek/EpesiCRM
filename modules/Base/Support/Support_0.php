<?php
/**
 * Get Support landing page - explains support options, no data of its own
 * (see Base_SupportInstall). Content is a placeholder for now, to be filled
 * in later.
 *
 * @package epesi-base
 * @subpackage support
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Support extends Module {
	public function body() {
		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('title', __('Get Support'));
		$smarty->assign('content', '<p>'.__('Content coming soon.').'</p>');
		Base_ThemeCommon::display_smarty($smarty, $this->get_type());
	}

	public function caption() {
		return __('Get Support');
	}
}
?>
