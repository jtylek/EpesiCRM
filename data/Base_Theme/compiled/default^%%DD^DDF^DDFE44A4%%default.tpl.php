<?php /* Smarty version 2.6.29, created on 2026-06-20 21:21:33
         compiled from Utils/TabbedBrowser/default.tpl */ ?>
<?php 
	load_js($this->get_template_vars('theme_dir').'/Utils/TabbedBrowser/default.js');
 ?>

<div class="Utils_TabbedBrowser_div">

<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
	<tr>
		<td>
			<ul class="Utils_TabbedBrowser">
			<?php $_from = $this->_tpl_vars['captions']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['cap'] => $this->_tpl_vars['link']):
?>
				<?php if (isset ( $this->_tpl_vars['captions_submenus'][$this->_tpl_vars['cap']] )): ?>
					<li onmouseover="tabbedbrowser_show_submenu('<?php echo $this->_tpl_vars['cap']; ?>
')" onmouseout="tabbedbrowser_hide_submenu('<?php echo $this->_tpl_vars['cap']; ?>
')">
						<div class="tabbedbrowser_submenu" id="tabbedbrowser_<?php echo $this->_tpl_vars['cap']; ?>
_popup" style="display:none;position:absolute;">
							<?php $_from = $this->_tpl_vars['captions_submenus'][$this->_tpl_vars['cap']]; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['s_cap'] => $this->_tpl_vars['s_link']):
?>
								<?php echo $this->_tpl_vars['s_link']; ?>

							<?php endforeach; endif; unset($_from); ?>
						</div>
				<?php else: ?>
					<li>
				<?php endif; ?>
				<?php echo $this->_tpl_vars['link']; ?>

				</li>&nbsp;
			<?php endforeach; endif; unset($_from); ?>
			</ul>
		</td>
	</tr>
	<tr >
		<td >
		<div class="border_bottom"></div>
			<center><?php echo $this->_tpl_vars['body']; ?>
</center>
		</td>
	</tr>
</table>

</div>