{* The visible toggle link used to render here directly (a compact navbar
   icon+label, later a standalone "Support" sidebar block) - per the latest
   request it's a real accordion entry now instead, under the sidebar's
   existing "Support" submenu (Base_HelpCommon::menu(), merged there
   alongside Base_About/Base_EssClient/Base_EpesiStore/Base_Mail_ContactUs's
   own entries by Base_MenuCommon::get_menus()). $href/$label are still
   assigned by Help_0.php (shared with the default theme, which still shows
   its own separate navbar toggle) but go unused here now - only the
   overlay/search-popup markup below is still needed from this template,
   still printed inside #MenuBar (Base_Box/theme_adminlte/default.tpl) for
   convenience, though it renders nothing visible there any more since it's
   entirely position:fixed. *}
<div id="Base_Help__overlay" style="display:none;" onclick="Helper.hide_menu();"></div>
<img id="Base_Help__click_icon" frame1="{$theme_dir}/Base/Help/left_click.png" frame2="{$theme_dir}/Base/Help/left_click2.png" style="display:none;" />
<div id="Base_Help__menu" style="display:none;">
	<input type="text" id="Base_Help__search" placeholder="{$search_placeholder}" onkeyup="Helper.search_keypress()" />
	<div id="Base_Help__help_suggestions" class="tutorial_links">
	</div>
	<div id="Base_Help__help_links" class="tutorial_links" style="display:none;">
	</div>
	<div id="Base_Help__help_close_menu" class="tutorial_links" onclick="Helper.hide_menu();">
		{'Close'|t}<img src="{$theme_dir}/Base/Help/close_black.png" />
	</div>
</div>
