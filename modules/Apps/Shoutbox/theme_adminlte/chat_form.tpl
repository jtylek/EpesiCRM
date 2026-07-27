{* {$form_data.post.html}/{$form_data.shoutbox_to.html}/{$form_data.submit_button.html} are
   QuickForm-generated markup shared with the default theme (textarea/autoselect/submit) -
   their own ids (shoutbox_text/shoutbox_to/shoutbox_button) are set by Shoutbox_0.php::chat()
   as extra attributes and are load-bearing (referenced directly by id in Shoutbox_0.php's own
   eval_js() calls and the shared shoutbox_refresh() poller) - kept exactly where QuickForm
   renders them, only the surrounding layout/CSS is new. #shoutbox_board is the same: its id is
   the Ajax.Updater() target refresh.php's poller writes into every 30s. *}
<div class="Apps_Shoutbox__dashboard epesi-shoutbox">
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
