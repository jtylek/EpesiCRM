<center>
<BR>
{$form_open}
	<table id="CRM_Filters" cellpadding="0" style="width:600px; border-spacing: 3px;">
			<tr>
				<td class="epesi_label" style="width:20%;">
					{$form_closecancel.label} <!-- STATUS -->
				</td>
				<td class="epesi_data" style="width:30%;">
					{$form_closecancel.html} <!-- SELECT -->
				</td>
			</tr>
			<tr>
				<td class="epesi_label">
					{$form_note.label} <!-- 1 note -->
				</td>
				<td colspan="3" class="epesi_data textarea" style="width:80%;">
					{$form_note.html} <!-- 2 note input -->
				</td>
			</tr>
	</table>
       
	<table id="CRM_Filters" cellspacing="0" cellpadding="0">	
		<tr>
			<td valign="top">
				{$new_meeting.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/Calendar/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_meeting.text}</span>
				</div>
				{$new_meeting.close}
			</td>

			<td valign="top">
				{$new_task.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/Tasks/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_task.text}</span>
				</div>
				{$new_task.close}
			</td>

			<td valign="top">
				{$new_phonecall.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/PhoneCall/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_phonecall.text}</span>
				</div>
				{$new_phonecall.close}
			</td>
			
			
			<td valign="top">
				{$just_close.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/Base/ActionBar/icons/save.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$just_close.text}</span>
				</div>
				{$just_close.close}
			</td>
		</tr>
	</table>
	{$form_close}

</center>
