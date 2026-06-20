<?php /* Smarty version 2.6.29, created on 2026-06-20 21:29:25
         compiled from Utils/RecordBrowser/single_field.tpl */ ?>
<tr>
    <td class="label<?php if ($this->_tpl_vars['f']['type'] == 'long text'): ?> long_label<?php endif; ?>"><?php echo $this->_tpl_vars['f']['label']; ?>
<?php if ($this->_tpl_vars['f']['required']): ?>*<?php endif; ?><?php echo $this->_tpl_vars['f']['advanced']; ?>
</td>
    <td class="data<?php if ($this->_tpl_vars['f']['type'] == 'long text'): ?> long_data<?php endif; ?> <?php echo $this->_tpl_vars['f']['style']; ?>
" id="_<?php echo $this->_tpl_vars['f']['element']; ?>
__data">
        <?php if ($this->_tpl_vars['f']['error']): ?><?php echo $this->_tpl_vars['f']['error']; ?>
<?php endif; ?>
        <?php if ($this->_tpl_vars['f']['help']): ?>
            <div class="help"><img src="<?php echo $this->_tpl_vars['f']['help']['icon']; ?>
" alt="help" <?php echo $this->_tpl_vars['f']['help']['text']; ?>
></div>
        <?php endif; ?>
        <div>
            <?php echo $this->_tpl_vars['f']['html']; ?>
<?php if ($this->_tpl_vars['action'] == 'view'): ?>&nbsp;<?php endif; ?>
        </div>
    </td>
</tr>