<center>
<BR>
{$form_open}
	<div id="CRM_Filters" style="width:600px;">
			<div style="display: flex;">
				<div class="epesi_label" style="flex-basis: 20%;">
					{$form_closecancel.label} <!-- STATUS -->
				</div>
				<div class="epesi_data" style="flex-basis: 30%;">
					{$form_closecancel.html} <!-- SELECT -->
				</div>
			</div>
			<div style="display: flex;">
				<div class="epesi_label">
					{$form_note.label} <!-- 1 note -->
				</div>
				<div class="epesi_data textarea" style="flex: 1 1 auto;">
					{$form_note.html} <!-- 2 note input -->
				</div>
			</div>
	</div>

    <div id="CRM_Filters" style="display: flex;">

            <div>
                {$just_close.open}
                <div class="epesi_big_button">
                    <img src="{$theme_dir}/Base/ActionBar/icons/save.png" alt="" align="middle" border="0" width="32" height="32">
                    <span>{$just_close.text}</span>
                </div>
                {$just_close.close}
            </div>

            <div style="font-size: 120%; font-weight: bold; width: 60px; padding: 0px 10px; display: flex; align-items: center; text-align: center">
                {"Or save and create:"|t}
            </div>

			<div>
				{$new_meeting.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/Calendar/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_meeting.text}</span>
				</div>
				{$new_meeting.close}
			</div>

			<div>
				{$new_task.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/Tasks/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_task.text}</span>
				</div>
				{$new_task.close}
			</div>

			<div>
				{$new_phonecall.open}
				<div class="epesi_big_button">
					<img src="{$theme_dir}/CRM/PhoneCall/icon.png" alt="" align="middle" border="0" width="32" height="32">
					<span>{$new_phonecall.text}</span>
				</div>
				{$new_phonecall.close}
			</div>
    </div>
	{$form_close}

</center>
