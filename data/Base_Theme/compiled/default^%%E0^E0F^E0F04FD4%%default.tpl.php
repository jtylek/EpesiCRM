<?php /* Smarty version 2.6.29, created on 2026-06-20 16:54:51
         compiled from Utils/Wizard/default.tpl */ ?>
<?php if (! empty ( $this->_tpl_vars['captions'] )): ?>
<table style="width:100%"><tr><td style="width:200px;vertical-align:top;">
<?php $this->assign('level', 0); ?>
<ul>
<?php $_from = $this->_tpl_vars['captions']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['i'] => $this->_tpl_vars['cap']):
?>
	<?php 
		$level = $this->get_template_vars('level');
		$cap = $this->get_template_vars('cap');
		$cap_level = $cap['level'];
		while($level>$cap_level) {
			print('</ul>');
			$level--;
		}
		while($level<$cap_level) {
			print('<ul>');
			$level++;
		}
		$this->assign('level',$level);
	 ?>

	<li>
	<?php if ($this->_tpl_vars['i'] == $this->_tpl_vars['active_caption_key']): ?>
		<b><?php echo $this->_tpl_vars['cap']['caption']; ?>
</b>
	<?php else: ?>
		<?php echo $this->_tpl_vars['cap']['caption']; ?>

	<?php endif; ?>
	</li>
<?php endforeach; endif; unset($_from); ?>
</ul>
</td><td>
<?php endif; ?>
<?php echo $this->_tpl_vars['page']; ?>

<?php if (! empty ( $this->_tpl_vars['captions'] )): ?>
</td></tr></table>
<?php endif; ?>