<?php /* Smarty version 2.6.29, created on 2026-06-20 21:22:05
         compiled from Utils/Calendar/day.tpl */ ?>
<div style="width: 900px;">
 
<div class="navigation-menu">
	<table border="0" cellpadding="0" cellspacing="0"><tr>
		<td class="empty">
			<div id="<?php echo $this->_tpl_vars['trash_id']; ?>
" class="trash">
				<div class="icon"><img border="0" width="32" height="32" src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/trash.png"></div>
				<div class="text"><?php echo $this->_tpl_vars['trash_label']; ?>
</div>
			</div>
		</td>
		<td style="width: 10px;"></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['prev_href']; ?>
><?php echo $this->_tpl_vars['prev_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/prev.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['today_href']; ?>
><?php echo $this->_tpl_vars['today_label']; ?>
&nbsp;&nbsp;<img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/this.png"></a></td>
		<td class="button_cell"><a class="button" <?php echo $this->_tpl_vars['next_href']; ?>
><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/next.png">&nbsp;&nbsp;<?php echo $this->_tpl_vars['next_label']; ?>
</a></td>
		<td style="width: 10px;"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['popup_calendar']; ?>
</td>
		<td class="empty"></td>
		<td class="button_cell"><?php echo $this->_tpl_vars['navigation_bar_additions']; ?>
</td>
	</tr></table>
</div>


	<div class="layer" style="padding: 9px; width: 100%;">
		<div class="css3_content_shadow">

<div style="padding: 5px; background-color: #FFFFFF;">

	<table cellspacing=0 id="Utils_Calendar__day">
		<thead>
			<tr>
				<th style="width:<?php echo $this->_tpl_vars['head_col_width']; ?>
;"></th>
				<th></th>
			</tr>
		</thead>
		<tr>
			<td class="hours_header" rowspan="2"><img src="<?php echo $this->_tpl_vars['theme_dir']; ?>
/Utils/Calendar/icon-day.png" width="32" height="32" border="0"><br><?php echo $this->_tpl_vars['day_view_label']; ?>
</td>
			<td class="header_month">
				<a <?php echo $this->_tpl_vars['link_month']; ?>
><?php echo $this->_tpl_vars['header_month']; ?>
</a>
				 &bull;
				<a <?php echo $this->_tpl_vars['link_year']; ?>
><?php echo $this->_tpl_vars['header_year']; ?>
</a>
			</td>

		</tr>

		<tr>
			<td class="header_day<?php if ($this->_tpl_vars['weekend']): ?>_weekend<?php endif; ?>">
				<?php echo $this->_tpl_vars['header_day']['label']; ?>
 &bull; <?php echo $this->_tpl_vars['header_day']['number']; ?>

			</td>
		</tr>

		<tr>
		<?php $_from = $this->_tpl_vars['timeline']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['k'] => $this->_tpl_vars['stamp']):
?>
			<tr>
				<td class="hour" nowrap ><?php echo $this->_tpl_vars['stamp']['label']; ?>
</td>
				<td class="inter<?php if ($this->_tpl_vars['weekend']): ?>_weekend<?php endif; ?>"<?php if ($this->_tpl_vars['stamp']['id'] !== false): ?> id="<?php echo $this->_tpl_vars['stamp']['id']; ?>
"<?php endif; ?>>&nbsp;</td>
			</tr>
		<?php endforeach; endif; unset($_from); ?>

	</table>

</div>
 		</div>
	</div>
<div style="color: #777777; display: block; float: left; padding-left: 20px;"><?php echo $this->_tpl_vars['info']; ?>
</div>

</div>