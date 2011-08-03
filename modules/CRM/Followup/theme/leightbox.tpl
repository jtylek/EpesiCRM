<center>
<BR>
{$form_open}
	<table id="CRM_Filters" cellspacing="0" cellpadding="0" width="600px">
			<tr>
				<td style="width:100px;background-color:#336699;border-bottom:1px solid #B3B3B3;color:#FFFFFF;padding-left:5px;padding-right:5px;text-align:left;vertical-align:middle;">
					{$form_closecancel.label} <!-- STATUS -->
				</td>
				<td colspan="3" style="width:1px;">
					{$form_closecancel.html} <!-- SELECT -->
				</td>
			</tr>
			<tr>
				<td style="background-color:#336699;border-bottom:1px solid #B3B3B3;color:#FFFFFF;padding-left:5px;padding-right:5px;text-align:left;vertical-align:middle;">
					{$form_note.label} <!-- 1 note -->
				</td>
				<td colspan="3">
					<div class="crm_followup_leightbox_note">
						{$form_note.html} <!-- 2 note input -->
					</div>
				</td>
			</tr>
	</table>
       
	<table id="CRM_Filters" cellspacing="0" cellpadding="0">	
		<tr>
			<td valign="top">
				<div class="panel">
					{$new_meeting.open}
					<div class="panel_div">
						<div class="icon">
							<div class="div_icon"><img src="{$theme_dir}/CRM/Calendar/icon.png" alt="" align="middle" border="0" width="32" height="32"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$new_meeting.text}</td></tr></table>
						</div>
					</div>
					{$new_meeting.close}
				</div>
			</td>

			<td valign="top">
				<div class="panel">
					{$new_task.open}
					<div class="panel_div">
						<div class="div_icon">
							<div class="div_icon"><img src="{$theme_dir}/CRM/Tasks/icon.png" alt="" align="middle" border="0" width="32" height="32"></div>
							<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$new_task.text}</td></tr></table>
						</div>
					</div>
					{$new_task.close}
				</div>
			</td>

			<td valign="top">
				<div class="panel">
					{$new_phonecall.open}
					<div class="panel_div">
						<div class="div_icon"><img src="{$theme_dir}/CRM/PhoneCall/icon.png" alt="" align="middle" border="0" width="32" height="32"></div>
						<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$new_phonecall.text}</td></tr></table>
					</div>
					{$new_phonecall.close}
				</div>
			</td>
			
			
			<td valign="top">
				<div class="panel"></div>
					{$just_close.open}
					<div class="panel_div">
						<div class="div_icon"><img src="{$theme_dir}/Base/ActionBar/icons/save.png" alt="" align="middle" border="0" width="32" height="32"></div>
						<table class="icon_table_text"><tr class="icon_tr_text"><td class="icon_td_text">{$just_close.text}</td></tr></table>
					</div>
					{$just_close.close}
				</div>
			</td>
		</tr>
	</table>
	{$form_close}

</center>
