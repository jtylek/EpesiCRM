<?php /* Smarty version 2.6.29, created on 2026-06-20 21:14:47
         compiled from CRM/Filters/default.tpl */ ?>
<center>

<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
        <!-- MY -->
        <td>

	    <?php echo $this->_tpl_vars['__link']['my']['open']; ?>

		<div class="epesi_big_button">
            <img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/CRM/Filters/my.png" alt="" align="middle" border="0" width="32" height="32">
			<span><?php echo $this->_tpl_vars['__link']['my']['text']; ?>
</span>
        </div>
	    <?php echo $this->_tpl_vars['__link']['my']['close']; ?>


        </td>

		<?php if (isset ( $this->_tpl_vars['all'] )): ?>
			<!-- ALL -->
			<td>

			<?php echo $this->_tpl_vars['__link']['all']['open']; ?>

			<div class="epesi_big_button">
				<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/CRM/Filters/all.png" alt="" align="middle" border="0" width="32" height="32">
				<span><?php echo $this->_tpl_vars['__link']['all']['text']; ?>
</span>
			</div>
			<?php echo $this->_tpl_vars['__link']['all']['close']; ?>


			</td>
		<?php endif; ?>

        <!-- MANAGE FILTERS -->
        <td>

	    <?php echo $this->_tpl_vars['__link']['manage']['open']; ?>

		<div class="epesi_big_button">
            <img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/CRM/Filters/manage.png" alt="" align="middle" border="0" width="32" height="32">
			<span><?php echo $this->_tpl_vars['__link']['manage']['text']; ?>
</span>
        </div>
	    <?php echo $this->_tpl_vars['__link']['manage']['close']; ?>


        </td>
    </tr>
</table>
<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
        <td colspan="3" style="text-align: center;">
			<?php echo $this->_tpl_vars['contacts_open']; ?>

				<?php echo $this->_tpl_vars['contacts_data']['crm_filter_contact']['label']; ?>
&nbsp;<span class="filters-autoselect"><?php echo $this->_tpl_vars['contacts_data']['crm_filter_contact']['html']; ?>
</span>&nbsp;<span class="child_button"><?php echo $this->_tpl_vars['contacts_data']['submit']['html']; ?>
</span>
			<?php echo $this->_tpl_vars['contacts_close']; ?>

		</td>
    </tr>
</table>

<br>

<?php if (! empty ( $this->_tpl_vars['filters'] )): ?>
	<table id="CRM_Filters" cellspacing="0" cellpadding="0">
		<tr>
			<td colspan="4" class="epesi_label header">&nbsp;&nbsp;<?php echo $this->_tpl_vars['saved_filters']; ?>
&nbsp;&nbsp;</td>
		</tr>
		<tr>

		<?php $this->assign('x', 0); ?>
		<?php $_from = $this->_tpl_vars['filters']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['k'] => $this->_tpl_vars['p']):
?>
		<?php $this->assign('x', $this->_tpl_vars['x']+1); ?>

			<td>

			<?php echo $this->_tpl_vars['p']['open']; ?>

			<div class="epesi_big_button">
				<span class="text"><?php echo $this->_tpl_vars['p']['title']; ?>
</span>
			</div>
			<?php echo $this->_tpl_vars['p']['close']; ?>


		</td>

		<?php if (( $this->_tpl_vars['x']%4 ) == 0): ?>
		</tr>
		<tr>
		<?php endif; ?>

	<?php endforeach; endif; unset($_from); ?>

		</tr>

	</table>
<?php endif; ?>

</center>