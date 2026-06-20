<?php /* Smarty version 2.6.29, created on 2026-06-20 20:26:40
         compiled from Base/Dashboard/default.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'count', 'Base/Dashboard/default.tpl', 9, false),)), $this); ?>
	<div class="layer" style="padding: 10px 0px; width: 96%; min-height: 30px;">
		<div class="content_shadow_css3_dashboard <?php echo $this->_tpl_vars['color']; ?>
_dashboard">
            <table class="container <?php echo $this->_tpl_vars['color']; ?>
_dashboard" cellpadding="0" cellspacing="0" border="0">
                <tbody>
                <tr class="nonselectable">
                	<td width="3px" class="header actions <?php echo $this->_tpl_vars['color']; ?>
_dashboard">
                	</td>
                	<?php if (! empty ( $this->_tpl_vars['actions'] )): ?>
						<?php $this->assign('actions_width', count($this->_tpl_vars['actions'])); ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']*16); ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']+4); ?>
	                	<td width="<?php echo $this->_tpl_vars['actions_width']; ?>
px" class="header actions <?php echo $this->_tpl_vars['color']; ?>
">
							<?php $_from = $this->_tpl_vars['actions']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['action']):
?>
		                		<?php echo $this->_tpl_vars['action']; ?>

		                	<?php endforeach; endif; unset($_from); ?>
	                	</td>
	                <?php else: ?>
	                	<td width="3px" class="header actions <?php echo $this->_tpl_vars['color']; ?>
">
	                	</td>
                	<?php endif; ?>
                	                    <td class="header title <?php echo $this->_tpl_vars['handle_class']; ?>
 <?php if ($this->_tpl_vars['fixed']): ?>fixed <?php endif; ?><?php echo $this->_tpl_vars['color']; ?>
"><?php echo $this->_tpl_vars['caption']; ?>
</td>
					<?php $this->assign('actions_width', 8); ?>
					<?php if (isset ( $this->_tpl_vars['href'] )): ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']+18); ?>
					<?php endif; ?>
					<?php if (isset ( $this->_tpl_vars['toggle'] )): ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']+18); ?>
					<?php endif; ?>
					<?php if (isset ( $this->_tpl_vars['configure'] )): ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']+18); ?>
					<?php endif; ?>
					<?php if (isset ( $this->_tpl_vars['remove'] )): ?>
						<?php $this->assign('actions_width', $this->_tpl_vars['actions_width']+18); ?>
					<?php endif; ?>
                    <td class="header controls <?php echo $this->_tpl_vars['color']; ?>
" nowrap="1" width="<?php echo $this->_tpl_vars['actions_width']; ?>
px">
						<?php if (isset ( $this->_tpl_vars['href'] )): ?>
							<?php echo $this->_tpl_vars['__link']['href']['open']; ?>

							<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/resize.png" onMouseOver="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/resize-hover.png';" onMouseOut="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/resize.png';" width="14" height="14" alt="R" border="0">
							<?php echo $this->_tpl_vars['__link']['href']['close']; ?>

						<?php endif; ?>
						<?php if (isset ( $this->_tpl_vars['toggle'] )): ?>
							<?php echo $this->_tpl_vars['__link']['toggle']['open']; ?>

							<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/roll-up.png" onMouseOver="var x='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'down-hover.png';else this.src=x+'up-hover.png';" onMouseOut="var x='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'down.png';else this.src=x+'up.png';" onClick="var x='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/roll-';if(this.src.indexOf('roll-down')>=0)this.src=x+'up-hover.png';else this.src=x+'down-hover.png';" width="14" height="14" alt="=" border="0">
							<?php echo $this->_tpl_vars['__link']['toggle']['close']; ?>

						<?php endif; ?>
						<?php if (isset ( $this->_tpl_vars['configure'] )): ?>
							<?php echo $this->_tpl_vars['__link']['configure']['open']; ?>

							<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/configure.png" onMouseOver="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/configure-hover.png';" onMouseOut="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/configure.png';" width="14" height="14" alt="c" border="0">
							<?php echo $this->_tpl_vars['__link']['configure']['close']; ?>

						<?php endif; ?>
						<?php if (isset ( $this->_tpl_vars['remove'] )): ?>
							<?php echo $this->_tpl_vars['__link']['remove']['open']; ?>

							<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/close.png" onMouseOver="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/close-hover.png';" onMouseOut="this.src='<?php echo $this->_tpl_vars['theme_dir']; ?>
/Base/Dashboard/close.png';" width="14" height="14" alt="x" border="0">
							<?php echo $this->_tpl_vars['__link']['remove']['close']; ?>

						<?php endif; ?>
						&nbsp;
					</td>
                </tr>
                <tr>
                    <td colspan="4" class="content_td" onclick=""><?php echo $this->_tpl_vars['content']; ?>
</td>
                </tr>
                </tbody>
            </table>
 		</div>
	</div>
