<?php /* Smarty version 2.6.29, created on 2026-06-20 21:28:59
         compiled from Utils/RecordBrowser/Browsing_records.tpl */ ?>
<div style="text-align: left;">
<table id="Browsing_records" class="nonselectable" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<?php if (isset ( $this->_tpl_vars['caption'] )): ?>
				<td style="width:100px;">
					<div class="name">
						<img alt=" " class="icon" src="<?php echo $this->_tpl_vars['icon']; ?>
" width="32" height="32" border="0">
						<div class="label">
							<?php if (isset ( $this->_tpl_vars['form_data'] )): ?>
								<?php echo $this->_tpl_vars['form_open']; ?>

							<?php endif; ?>
							<?php echo $this->_tpl_vars['caption']; ?>

							<?php if (isset ( $this->_tpl_vars['form_data'] )): ?>
									<?php echo $this->_tpl_vars['form_data']['browse_mode']['html']; ?>

								<?php echo $this->_tpl_vars['form_close']; ?>

							<?php endif; ?>
						</div>
					</div>
				</td>
				<td style="width:100%;">
				</td>
			<?php endif; ?>
    		<td class="filters">
                <?php if (isset ( $this->_tpl_vars['filters']['controls'] )): ?>
	                <?php echo $this->_tpl_vars['filters']['controls']; ?>

                <?php endif; ?>
            </td>
        </tr>
        <?php if (isset ( $this->_tpl_vars['filters']['elements'] )): ?>
        <tr>
            <td colspan="3" class="filters">
            <?php echo $this->_tpl_vars['filters']['elements']; ?>

            </td>
        </tr>
        <?php endif; ?>
	</tbody>
</table>
</div>
                

<?php echo $this->_tpl_vars['table']; ?>
