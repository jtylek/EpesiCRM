<?php /* Smarty version 2.6.29, created on 2026-06-20 21:23:09
         compiled from Utils/RecordBrowser/Filters/elements.tpl */ ?>
<?php echo $this->_tpl_vars['form_open']; ?>


<div id="recordbrowser_filters_<?php echo $this->_tpl_vars['filter_group']['id']; ?>
" class="Utils_RecordBrowser__Filter" <?php if (! $this->_tpl_vars['filter_group']['visible']): ?>style="display: none;"<?php endif; ?>>
	<table border="0" cellpadding="0" cellspacing="0">
		<tr>
			<?php $this->assign('x', 0); ?>
			<?php $this->assign('first', 1); ?>
			<?php $_from = $this->_tpl_vars['filter_group']['elements']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['f']):
?>
				<?php if ($this->_tpl_vars['x'] == 4): ?>
					<?php if ($this->_tpl_vars['first'] == 1): ?>
						<td class="buttons"><?php echo $this->_tpl_vars['form_data']['submit']['html']; ?>
</td>
						<?php $this->assign('first', 0); ?>
					<?php else: ?>
						<td />
					<?php endif; ?>
					<?php $this->assign('x', 0); ?>
					</tr>
					<tr>
				<?php endif; ?>
				<td class="label"><?php echo $this->_tpl_vars['form_data'][$this->_tpl_vars['f']]['label']; ?>
</td>
				<td class="data"><?php echo $this->_tpl_vars['form_data'][$this->_tpl_vars['f']]['html']; ?>
</td>
				<?php $this->assign('x', $this->_tpl_vars['x']+1); ?>
			<?php endforeach; endif; unset($_from); ?>
			<?php if ($this->_tpl_vars['first'] == 1): ?>
				<td class="buttons"><?php echo $this->_tpl_vars['form_data']['submit']['html']; ?>
</td>
			<?php endif; ?>
		</tr>
	</table>
</div>

<?php echo $this->_tpl_vars['form_close']; ?>
