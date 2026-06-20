<?php /* Smarty version 2.6.29, created on 2026-06-20 21:21:33
         compiled from Utils/LeightboxPrompt/leightbox.tpl */ ?>
<center>
<?php echo $this->_tpl_vars['open_buttons_section']; ?>

<table id="Utils_LeightboxPrompt" cellspacing="0" cellpadding="0">
	<?php $this->assign('x', 0); ?>
	<tr>
	<?php $_from = $this->_tpl_vars['buttons']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['b']):
?>
        <td>
			<?php echo $this->_tpl_vars['b']['open']; ?>

			<div class="epesi_big_button">
				<?php if (( $this->_tpl_vars['b']['icon'] )): ?>
					<img src="<?php echo $this->_tpl_vars['b']['icon']; ?>
" alt="" align="middle" border="0" width="32" height="32">
				<?php endif; ?>
				<span><?php echo $this->_tpl_vars['b']['label']; ?>
</span>
			</div>
			<?php echo $this->_tpl_vars['b']['close']; ?>

        </td>
		<?php $this->assign('x', $this->_tpl_vars['x']+1); ?>
		<?php if (( $this->_tpl_vars['x'] == 6 )): ?>
			<?php $this->assign('x', 0); ?>
			</tr>
			<tr>
		<?php endif; ?>
	<?php endforeach; endif; unset($_from); ?>
    </tr>
</table>
<?php echo $this->_tpl_vars['close_buttons_section']; ?>


<?php $_from = $this->_tpl_vars['sections']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['b']):
?>
	<?php echo $this->_tpl_vars['b']; ?>

<?php endforeach; endif; unset($_from); ?>
<?php echo $this->_tpl_vars['additional_info']; ?>

</center>