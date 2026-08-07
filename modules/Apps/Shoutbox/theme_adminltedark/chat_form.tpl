{* {$form_data.post.html}/{$form_data.shoutbox_to.html}/{$form_data.submit_button.html} are
   QuickForm-generated markup shared with the default theme (textarea/autoselect/submit) -
   their own ids (shoutbox_text/shoutbox_to/shoutbox_button) are set by Shoutbox_0.php::chat()
   as extra attributes and are load-bearing (referenced directly by id in Shoutbox_0.php's own
   eval_js() calls and the shared shoutbox_refresh() poller) - kept exactly where QuickForm
   renders them, only the surrounding layout/CSS is new. #shoutbox_board is the same: its id is
   the Ajax.Updater() target refresh.php's poller writes into every 30s. *}
{php}
	// Shoutbox_0.php doesn't set a placeholder on the message textarea
	// (shared with the default theme) - injected here, theme-scoped, rather
	// than in the shared form definition.
	$form_data = $this->get_template_vars('form_data');
	if (isset($form_data['post']['html']) && !str_contains($form_data['post']['html'], 'placeholder=')) {
		$placeholder = htmlspecialchars(__('Type your message here...'), ENT_QUOTES);
		$form_data['post']['html'] = preg_replace('/<textarea\b/i', '<textarea placeholder="' . $placeholder . '"', $form_data['post']['html'], 1);
	}
	$this->assign('form_data', $form_data);
{/php}
{* Wrapped in its own Bootstrap "card" (same recipe as Utils_GenericBrowser's
   own .epesi-gb.card, Utils/GenericBrowser/theme_adminltedark/default.tpl) -
   #dashboard .epesi-applet-body has padding:0!important (Base/Dashboard/
   theme_adminltedark/default.css), a blanket rule meant for GenericBrowser/
   RecordBrowser table applets so their own grid can run edge-to-edge, applied
   indiscriminately to every applet's body regardless of content. Without an
   inner card of its own supplying its own padding, this form/board pair sat
   pressed flush against the applet's outer edges - reported as "looked
   better before" (i.e. before that shared zero-padding rule started
   affecting this non-table applet too). Scoped to chat_form.tpl specifically
   (chat_form_big.tpl's own Chat-tab rendering is a normal module body, never
   inside .epesi-applet-body, so it doesn't need this and isn't touched). *}
<div class="Apps_Shoutbox__dashboard epesi-shoutbox card">
	{$form_open}
	<div class="epesi-shoutbox-compose">
		{$form_data.post.html}
	</div>
	<div class="epesi-shoutbox-toolbar">
		<div class="epesi-shoutbox-to">{$form_data.shoutbox_to.html}</div>
		{$form_data.submit_button.html}
	</div>
	{$form_close}
	{$board}
</div>
