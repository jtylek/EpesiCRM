<?php /* Smarty version 2.6.29, created on 2026-06-20 21:21:33
         compiled from Utils/Calendar/week.tpl */ ?>
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
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prev7_href']; ?>
><?php echo $this->_tpl_vars['prev7_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev2.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prev_href']; ?>
><?php echo $this->_tpl_vars['prev_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['today_href']; ?>
><?php echo $this->_tpl_vars['today_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['next_href']; ?>
><img border="0" width="8" height="20" src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['next_label']; ?>
</a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['next7_href']; ?>
><img border="0" width="8" height="20" src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next2.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['next7_label']; ?>
</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['popup_calendar']; ?>
</td>
		<td class="empty"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['navigation_bar_additions']; ?>
</td>
	</tr></table>
</div>
 
<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">
<!-- -->

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__week">
		<thead>
			<tr>
				<th style="width:<?php echo $this->_tpl_vars['head_col_width']; ?>
;"></th>
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
			<td class="hours_header" rowspan="2"><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/icon-week.png" width="32" height="32" border="0"><br><?php echo $this->_tpl_vars['week_view_label']; ?>
</td>
			<td class="header_month" colspan="<?php echo $this->_tpl_vars['header_month']['first_span']['colspan']; ?>
">
				<a <?php echo $this->_tpl_vars['header_month']['first_span']['month_link']; ?>
><?php echo $this->_tpl_vars['header_month']['first_span']['month']; ?>
</a>
				 &bull;
				<a <?php echo $this->_tpl_vars['header_month']['first_span']['year_link']; ?>
><?php echo $this->_tpl_vars['header_month']['first_span']['year']; ?>
</a>
			</td>
			<?php if (isset ( $this->_tpl_vars['header_month']['second_span'] )): ?>
				<td class="header_month" colspan="<?php echo $this->_tpl_vars['header_month']['second_span']['colspan']; ?>
">
					<a <?php echo $this->_tpl_vars['header_month']['second_span']['month_link']; ?>
><?php echo $this->_tpl_vars['header_month']['second_span']['month']; ?>
</a>
					 &bull;
					<a <?php echo $this->_tpl_vars['header_month']['second_span']['year_link']; ?>
><?php echo $this->_tpl_vars['header_month']['second_span']['year']; ?>
</a>
				</td>
			<?php endif; ?>

		</tr>

		<tr>
			<?php $_from = $this->_tpl_vars['day_headers']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['header']):
?>
				<td class="header_day_<?php echo $this->_tpl_vars['header']['style']; ?>
"><a <?php echo $this->_tpl_vars['header']['link']; ?>
><?php echo $this->_tpl_vars['header']['date']; ?>
</a></td>
			<?php endforeach; endif; unset($_from); ?>
		</tr>
		<tr>
		<?php $_from = $this->_tpl_vars['timeline']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['k'] => $this->_tpl_vars['stamp']):
?>
			<tr>
				<td class="hour" nowrap ><?php echo $this->_tpl_vars['stamp']['label']; ?>
</td>
				<?php $_from = $this->_tpl_vars['time_ids']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['j'] => $this->_tpl_vars['t']):
?>
                    <td class="inter_<?php echo $this->_tpl_vars['day_headers'][$this->_tpl_vars['j']]['style']; ?>
"<?php if ($this->_tpl_vars['t'][$this->_tpl_vars['k']] !== false): ?> id="<?php echo $this->_tpl_vars['t'][$this->_tpl_vars['k']]; ?>
"<?php endif; ?>><div class="inner">&nbsp;</div></td>
	            <?php endforeach; endif; unset($_from); ?>
			</tr>
		<?php endforeach; endif; unset($_from); ?>
	</table>

</div>
 		</div>
	</div>

<div style="color: #777777; display: block; float: left; padding-left: 20px;"><?php echo $this->_tpl_vars['info']; ?>
</div>