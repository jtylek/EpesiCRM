{* Reached via Utils_TabbedBrowser's "Chat" tab - Shoutbox_0.php::body() calls
   $tb->set_tab(__('Chat'), $this->chat(...), true, null); TabbedBrowser coerces
   that truthy 3rd arg into array(true) and calls chat(true) - i.e. $big=true -
   so this "big" template renders here, not chat_form.tpl (which only the
   dashboard applet, via Shoutbox_0.php::applet(), actually uses).

   Per request: dropped the {$header} "Shoutbox" caption (redundant - the tab
   strip above already says "Chat") and the {$form_data.post.label} "Message"
   label the default theme's table layout printed. Same ids as chat_form.tpl,
   suffixed _big throughout (shoutbox_text_big/shoutbox_to_big/
   shoutbox_button_big/shoutbox_board_big) - Shoutbox_0.php's own JS references
   these directly by id, kept exactly as QuickForm renders them. *}
<div id="shoutbox_big_container" class="epesi-shoutbox">
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
