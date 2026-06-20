<?php /* Smarty version 2.6.29, created on 2026-06-20 21:21:35
         compiled from Utils/Calendar/month.tpl */ ?>
<div class="navigation-menu">
	<table border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="trash_cell">
			<div id="<?php echo $this->_tpl_vars['trash_id']; ?>
" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/trash.png"></div>
				<div class="text"><?php echo $this->_tpl_vars['trash_label']; ?>
</div>
			</div>
		</td>
		<td class="empty"></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prevyear_href']; ?>
><?php echo $this->_tpl_vars['prevyear_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev2.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prevmonth_href']; ?>
><?php echo $this->_tpl_vars['prevmonth_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['today_href']; ?>
><?php echo $this->_tpl_vars['today_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['nextmonth_href']; ?>
><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['nextmonth_label']; ?>
</a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['nextyear_href']; ?>
><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next2.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['nextyear_label']; ?>
</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['popup_calendar']; ?>
</td>
		<td class="empty"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['navigation_bar_additions']; ?>
</td>
	</tr></table>
</div>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table name="CRMCalendar" id="Utils_Calendar__month" cellpadding="0" cellspacing="0" border="0">
		<thead>
			<tr>
				<th style="width:30px;"></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
				<th></th>
			</tr>
		</thead>
		<tr>
			<td class="month-header" colspan="8"><?php echo $this->_tpl_vars['month_label']; ?>
 &bull; <a <?php echo $this->_tpl_vars['year_link']; ?>
><?php echo $this->_tpl_vars['year_label']; ?>
</a></td>
		</tr>

		<tr>
			<td class="week-number">&nbsp;</td>
			<?php $_from = $this->_tpl_vars['day_headers']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['header']):
?>
                <td class="<?php echo $this->_tpl_vars['header']['class']; ?>
"><?php echo $this->_tpl_vars['header']['label']; ?>
</td>
			<?php endforeach; endif; unset($_from); ?>
		</tr>

		<?php $_from = $this->_tpl_vars['month']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
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
"><div class="inner" id="<?php echo $this->_tpl_vars['day']['id']; ?>
"><a class="day_link" <?php echo $this->_tpl_vars['day']['day_link']; ?>
><?php echo $this->_tpl_vars['day']['day']; ?>
</a></div></td>
				<?php endforeach; endif; unset($_from); ?>
			</tr>
		<?php endforeach; endif; unset($_from); ?>
	</table>

</div>

<!-- SHADOW END -->
 		</div>
	</div>
<!-- -->

<div style="color: #777777; display: block; float: left; padding-left: 20px;"><?php echo $this->_tpl_vars['info']; ?>
</div>