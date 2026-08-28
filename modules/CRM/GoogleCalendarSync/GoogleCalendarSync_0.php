<?php
/**
 * Pushes each user's Epesi meetings to their own Google Calendar (one-way,
 * Epesi -> Google). See AI-shared/Epesi-Google-Calendar-sync.md for the
 * design this was built from.
 * @author Claude Code
 * @copyright Janusz Tylek
 * @license MIT
 * @version 0.1
 * @package epesi-CRM
 * @subpackage GoogleCalendarSync
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class CRM_GoogleCalendarSync extends Module {

	public function body() {
	}

	// My Settings tile target (CRM_GoogleCalendarSyncCommon::user_settings()).
	// Status page: current connection state + a Connect/Disconnect button.
	// No manual Client ID/Secret entry here - that's the admin() screen below.
	public function connect($pushed_on_top = false) {
		if (!Base_AclCommon::check_permission('Google Calendar Sync')) {
			return;
		}
		if ($pushed_on_top) {
			if ($this->is_back()) {
				return Base_BoxCommon::pop_main();
			}
			Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
		} else {
			Base_ActionBarCommon::add('back', __('Back'), $this->create_main_href('Base_User_Settings'));
		}

		$user_id = Acl::get_user();
		$account = CRM_GoogleCalendarSyncCommon::get_account($user_id);
		$configured = CRM_GoogleCalendarSyncCommon::is_configured();
		$adminlte = Base_ThemeCommon::is_adminlte_family();

		if ($account) {
			Base_ActionBarCommon::add('delete', __('Disconnect'), $this->create_confirm_callback_href(__('Disconnect Google Calendar Sync? Events already pushed to Google will not be removed automatically.'), array('CRM_GoogleCalendarSyncCommon', 'disconnect_current_user')));
			Base_ActionBarCommon::add('refresh', __('Sync Now'), $this->create_callback_href(array('CRM_GoogleCalendarSyncCommon', 'sync_current_user')));
		} elseif ($configured) {
			$auth_url = CRM_GoogleCalendarSyncCommon::authorize_url(CRM_GoogleCalendarSyncCommon::oauth_redirect_uri());
			Base_ActionBarCommon::add('add', __('Connect Google Calendar'), 'href="'.htmlspecialchars($auth_url).'"');
		}

		$icon_url = htmlspecialchars(Base_ThemeCommon::get_template_file('CRM_GoogleCalendarSync', 'package-icon.png'));
		$title = __('Google Calendar Sync');

		if ($adminlte) {
			print('<div class="d-flex justify-content-center py-4"><div class="card" style="max-width:600px;width:100%;"><div class="card-body text-center">');
			print('<img src="'.$icon_url.'" alt="" width="48" height="48" class="mb-2 rounded">');
			print('<h4 class="mb-3">'.$title.'</h4>');
		} else {
			print('<h2><img src="'.$icon_url.'" alt="" width="32" height="32" style="vertical-align:middle;margin-right:8px;">'.$title.'</h2>');
		}

		if (!$configured) {
			$msg = __('Google Calendar Sync has not been configured yet. Ask an administrator to set it up in the Admin Panel.');
			print($adminlte ? ('<i class="bi bi-exclamation-circle text-warning" style="font-size:2rem;"></i><p class="text-muted mt-2 mb-0">'.$msg.'</p>') : ('<div class="important_notice">'.$msg.'</div>'));
		} elseif ($account) {
			$rows = array(
				__('Google account') => $account['google_email'] ? htmlspecialchars((string) $account['google_email']) : __('(unknown)'),
				__('Last synced') => $account['last_synced_on'] ? Base_RegionalSettingsCommon::time2reg(strtotime($account['last_synced_on'])) : __('Not yet synced'),
			);
			if ($account['last_error']) $rows[__('Last error')] = htmlspecialchars((string) $account['last_error']);
			if ($adminlte) {
				print('<i class="bi bi-check-circle-fill text-success" style="font-size:2rem;"></i>');
				print('<h5 class="mt-2 mb-3">'.__('Connected').'</h5>');
				print('<div class="text-start bg-body-tertiary rounded p-3">');
				foreach ($rows as $label=>$value) print('<div class="mb-1"><span class="fw-bold">'.$label.':</span> '.$value.'</div>');
				print('</div>');
			} else {
				print('<div class="important_notice"><b>'.__('Connected').'</b><br>');
				foreach ($rows as $label=>$value) print($label.': '.$value.'<br>');
				print('</div>');
			}
		} else {
			$msg = __('Connect your Google account to push your Epesi meetings to your own Google Calendar. This is one-way: changes made directly on Google are never pulled back into Epesi.');
			print($adminlte ? '<p class="text-muted mb-0">'.$msg.'</p>' : '<div class="important_notice">'.$msg.'</div>');
		}

		if ($adminlte) print('</div></div></div>');
	}

	// Admin Panel entry (CRM_GoogleCalendarSyncCommon::admin_caption()/admin_access()):
	// installation-wide Google OAuth Client ID/Secret. Superadmin-only, same
	// pattern as Base_EssClient::admin().
	public function admin($store = false) {
		if (!Base_AclCommon::i_am_sa()) {
			return;
		}
		if ($this->is_back()) {
			$this->parent->reset();
			return;
		}
		if (!$store) Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());

		$f = $this->init_module(Libs_QuickForm::module_name());
		$f->addElement('text', 'client_id', __('Google OAuth Client ID'), array('size'=>64));
		$f->addElement('text', 'client_secret', __('Google OAuth Client Secret'), array('size'=>64));

		if ($f->validate()) {
			$v = $f->exportValues();
			Variable::set('crm_googlecalendarsync_client_id', trim((string) $v['client_id']));
			Variable::set('crm_googlecalendarsync_client_secret', trim((string) $v['client_secret']));
			$this->parent->reset();
			return;
		}

		$f->setDefaults(array(
			'client_id' => Variable::get('crm_googlecalendarsync_client_id', false),
			'client_secret' => Variable::get('crm_googlecalendarsync_client_secret', false),
		));
		Base_ActionBarCommon::add('save', __('Save'), $f->get_submit_form_href());

		$redirect_uri = CRM_GoogleCalendarSyncCommon::oauth_redirect_uri();

		// Google Cloud Console's own home page doesn't make these next steps
		// obvious (its "Credentials" page redirects newcomers back to a
		// generic dashboard) - link each step straight to the exact Console
		// page it needs, in order, rather than just naming "Google Cloud
		// Console" once and leaving the navigation to the admin.
		$enable_link = '<a href="https://console.cloud.google.com/apis/library/calendar-json.googleapis.com" target="_blank" rel="noopener">'.__('Enable the Google Calendar API').'</a>';
		$consent_link = '<a href="https://console.cloud.google.com/apis/credentials/consent" target="_blank" rel="noopener">'.__('Configure the OAuth consent screen').'</a>';
		$credentials_link = '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener">'.__('Create an OAuth 2.0 Client ID').'</a>';
		$steps = '<ol class="mb-3 ps-3 text-start">'
			. '<li>'.$enable_link.' '.__('for your Google Cloud project.').'</li>'
			. '<li>'.$consent_link.' '.__('(User Type: Internal for a Workspace account you administer, otherwise External + Testing, with your own account added as a Test user). Under %s, add the %s scope explicitly - it is a sensitive scope, so Google silently drops it from any token if it is not declared here, even if the app requests it. Without this step, connecting will appear to succeed but syncing will fail with "insufficient authentication scopes".', array('<b>'.__('Data Access').'</b>', '<code>.../auth/calendar.events</code>')).'</li>'
			. '<li>'.$credentials_link.' '.__('of type Web application. Under Authorized redirect URIs, paste the URI shown below.').'</li>'
			. '<li>'.__('Paste the resulting Client ID and Client Secret into the form below and save.').'</li>'
			. '</ol>';

		if (Base_ThemeCommon::is_adminlte_family()) {
			print('<div class="d-flex justify-content-center py-4"><div class="card" style="max-width:600px;width:100%;"><div class="card-body">');
			print($steps);
			print('<div class="bg-body-tertiary rounded p-2 mb-3"><code class="user-select-all">'.htmlspecialchars($redirect_uri).'</code></div>');
			print('<center>');
			$f->display();
			print('</center>');
			print('</div></div></div>');
		} else {
			print('<div class="important_notice">');
			print($steps.'<code>'.htmlspecialchars($redirect_uri).'</code><br><br>');
			print('<center>');
			$f->display_as_column();
			print('</center>');
			print('</div>');
		}
	}

}

?>
