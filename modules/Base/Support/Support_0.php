<?php
/**
 * Get Support landing page - explains support options, no data of its own
 * (see Base_SupportInstall).
 *
 * @package epesi-base
 * @subpackage support
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_Support extends Module {
	public function body() {
		$smarty = Base_ThemeCommon::init_smarty();
		$smarty->assign('title', __('Get Support'));
		$smarty->assign('content', $this->get_content());
		Base_ThemeCommon::display_smarty($smarty, $this->get_type());
	}

	private function get_content() {
		$content = '<p>'.__('%s is a free, open source (MIT licensed) PHP framework for building web-native business applications.', array('Epesi')).'</p>';
		$content .= '<p>'.__('It uses a modular architecture, and is distributed with basic CRM functionality out of the box:').'</p>';
		$content .= '<ul class="epesi-support-modules">'
			.'<li>'.__('Attachments and notes').'</li>'
			.'<li>'.__('Calendar').'</li>'
			.'<li>'.__('Companies').'</li>'
			.'<li>'.__('Contacts').'</li>'
			.'<li>'.__('Dashboard').'</li>'
			.'<li>'.__('E-mail archiving').'</li>'
			.'<li>'.__('Login Audit').'</li>'
			.'<li>'.__('Phone Calls').'</li>'
			.'<li>'.__('Roundcube e-mail client integration').'</li>'
			.'<li>'.__('Search').'</li>'
			.'<li>'.__('Shoutbox').'</li>'
			.'<li>'.__('Tasks').'</li>'
			.'<li>'.__('User Activity Report').'</li>'
			.'<li>'.__('User Management').'</li>'
			.'</ul>';

		$content .= '<hr>';

		$content .= '<h5>'.__('Documentation').'</h5>';
		$content .= '<p>'.__('Whether you\'re an end user, an administrator, or a developer, the documentation at %s is the best place to start.', array('<a href="https://epesi.org/" target="_blank">epesi.org</a>')).'</p>';

		$content .= '<h5>'.__('For Administrators: modules & the Epesi Store').'</h5>';
		$content .= '<p>'.__('Turn %s into a custom ERP by installing additional modules straight from the Epesi Store, from within your own installation. Some modules are free, others are commercial add-ons available for purchase.', array('Epesi')).'</p>';
		if (Base_AclCommon::i_am_sa()) {
			$content .= '<p><a class="btn btn-outline-primary btn-sm" '.Base_BoxCommon::create_href(null, 'Base_Admin').'><i class="bi bi-person-workspace"></i> '.__('Open Administrator Control Panel').'</a></p>';
			$content .= '<p>'.__('From there, open %s to browse, buy, and install modules.', array('<strong>'.__('Modules Administration & Store').'</strong>')).'</p>';
		}

		$content .= '<h5>'.__('Community Forum').'</h5>';
		$content .= '<p>'.__('Ask customization questions, report bugs, or request new features on the %s.', array('<a href="https://forum.epe.si/" target="_blank">'.__('Epesi Forum').'</a>')).'</p>';

		$content .= '<h5>'.__('E-mail Support').'</h5>';
		$content .= '<p>'.__('You can also reach us directly at %s.', array('<a href="mailto:epesi.help@tylek.org">epesi.help@tylek.org</a>')).'</p>';

		$content .= '<h5>'.__('For Developers').'</h5>';
		$content .= '<p>'.__('Get the source from %s.', array('<a href="https://github.com/jtylek/EpesiCRM" target="_blank">GitHub</a>')).'</p>';
		$content .= '<p>'.__('The repository is AI-friendly and ships with project-specific memory, so AI coding assistants can get productive quickly when you build your own applications on top of it.').'</p>';
		$content .= '<p>'.__('Found a bug? %s.', array('<a href="https://github.com/jtylek/EpesiCRM/issues" target="_blank">'.__('Open an issue on GitHub').'</a>')).'</p>';

		$content .= '<hr>';
		$content .= '<p class="epesi-support-footer small">'.__('%s is maintained by Janusz Tylek and Karina Tylek.', array('Epesi')).' '.__('Copyright').' &copy; 2006-'.date('Y').'</p>';

		return $content;
	}

	public function caption() {
		return __('Get Support');
	}
}
?>
