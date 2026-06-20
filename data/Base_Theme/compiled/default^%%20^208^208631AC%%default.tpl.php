<?php /* Smarty version 2.6.29, created on 2026-06-20 21:14:47
         compiled from Base/Help/default.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 't', 'Base/Help/default.tpl', 15, false),)), $this); ?>
<div class="help">
	<a <?php echo $this->_tpl_vars['href']; ?>
 onMouseOver="$('help_icon').src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/MainModuleIndicator/help-hover.png';" onMouseOut="$('help_icon').src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/MainModuleIndicator/help.png';">
		<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/MainModuleIndicator/help.png" id="help_icon" alt="?" border="0"><div class="help_label"><?php echo $this->_tpl_vars['label']; ?>
</div>
	</a>
</div>
<div id="Base_Help__overlay" style="display:none;" onclick="Helper.hide_menu();"></div>
<img id="Base_Help__click_icon" frame1="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Help/left_click.png" frame2="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Help/left_click2.png" style="display:none;" />
<div id="Base_Help__menu" style="display:none;">
	<input type="text" id="Base_Help__search" placeholder="<?php echo $this->_tpl_vars['search_placeholder']; ?>
" onkeyup="Helper.search_keypress()" />
	<div id="Base_Help__help_suggestions" class="tutorial_links">
	</div>
	<div id="Base_Help__help_links" class="tutorial_links" style="display:none;">
	</div>
	<div id="Base_Help__help_close_menu" class="tutorial_links" onclick="Helper.hide_menu();">
		<?php echo ((is_array($_tmp='Close')) ? $this->_run_mod_handler('t', true, $_tmp) : Base_ThemeCommon::smarty_modifier_translate($_tmp)); ?>
<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Help/close_black.png" />
	</div>
</div>