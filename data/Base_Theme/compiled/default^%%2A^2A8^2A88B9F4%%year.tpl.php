<?php /* Smarty version 2.6.29, created on 2026-06-20 21:22:07
         compiled from Utils/Calendar/year.tpl */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('function', 'math', 'Utils/Calendar/year.tpl', 25, false),)), $this); ?>
<div class="navigation-menu">
	<table border="0">
		<tr>
			<td class="empty"></td>
			<td style="width: 10px;"></td>
			<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prevyear_href']; ?>
><?php echo $this->_tpl_vars['prevyear_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev.png"></a></td>
			<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['today_href']; ?>
><?php echo $this->_tpl_vars['today_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/this.png"></a></td>
			<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['nextyear_href']; ?>
><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['nextyear_label']; ?>
</a></td>
			<td style="width: 10px;"></td>
			<td class="button_cell"><?php echo $this->_tpl_vars['popup_calendar']; ?>
</td>
			<!-- <td style="width: 10px;"></td>
			<td><a class="button" style="width: 80px;"><img border="0" width="20" height="20" src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/4x3.png" style="vertical-align: middle; padding: 0px; margin-left: 10px; display: block; float: left; width: 20px; height: 20px;">4 x 3</a></td> -->
			<td class="empty"></td>
			<?php if ($this->_tpl_vars['navigation_bar_additions']): ?>
				<td class="button_cell"><?php echo $this->_tpl_vars['navigation_bar_additions']; ?>
</td>
			<?php endif; ?>
		</tr>
	</table>
</div>


	<div class="layer" style="padding: 9px; width: 764px;">
		<div class="css3_content_shadow">

<?php echo smarty_function_math(array('assign' => 'col','equation' => 'x','x' => 3), $this);?>


<table border="0" cellpadding="0" cellspacing="5" style="background-color: #FFFFFF;">

<?php $_from = $this->_tpl_vars['year']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['month']):
?>
	<?php if ($this->_tpl_vars['col'] % 3 == 0): ?><tr><?php endif; ?>
		<td style="vertical-align: top">
            <table name="CRMCalendar" id="Utils_Calendar__year" cellpadding="0" cellspacing="0" border="0">
            	<tr>
            		<td class="header-month" colspan="8"><a <?php echo $this->_tpl_vars['month']['month_link']; ?>
><?php echo $this->_tpl_vars['month']['month_label']; ?>
 &bull; <?php echo $this->_tpl_vars['month']['year_label']; ?>
</a></td>
            	</tr>
            	<tr>
            		<td class="week-number">&nbsp;</td>
            		<?php $_from = $this->_tpl_vars['day_headers']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['header']):
?>
            			<td class="header"><?php echo $this->_tpl_vars['header']; ?>
</td>
            		<?php endforeach; endif; unset($_from); ?>
            	</tr>
            	<?php $_from = $this->_tpl_vars['month']['month']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['week']):
?>
            		<tr>
            			<td class="week-number"><a <?php echo $this->_tpl_vars['week']['week_link']; ?>
><?php echo $this->_tpl_vars['week']['week_label']; ?>
</a></td>
            			<?php $_from = $this->_tpl_vars['week']['days']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['day']):
?>
            				<td class="day <?php echo $this->_tpl_vars['day']['style']; ?>
"><a <?php echo $this->_tpl_vars['day']['day_link']; ?>
><?php echo $this->_tpl_vars['day']['day']; ?>
</a></td>
            			<?php endforeach; endif; unset($_from); ?>
            		</tr>
            	<?php endforeach; endif; unset($_from); ?>
            </table>
		</td>
	<?php if ($this->_tpl_vars['col'] % 3 == 3): ?></tr><?php endif; ?>

    <?php echo smarty_function_math(array('assign' => 'col','equation' => "x+1",'x' => $this->_tpl_vars['col']), $this);?>


<?php endforeach; endif; unset($_from); ?>

</table>
 		</div>
	</div>