<center>

{$form_open}
<table id="CRM_Filters" cellspacing="0" cellpadding="0" width="400px">
	<tr>
        <td style="width:100px;background-color:#336699;border-bottom:1px solid #B3B3B3;color:#FFFFFF;padding-left:5px;padding-right:5px;text-align:left;vertical-align:middle;">
        	{$form_closecancel.label}
		</td>
		<td colspan="3" style="width:1px;">
			{$form_closecancel.html}
		</td>
	</tr>
	<tr>
        <td style="background-color:#336699;border-bottom:1px solid #B3B3B3;color:#FFFFFF;padding-left:5px;padding-right:5px;text-align:left;vertical-align:middle;">
        	{$form_note.label}
		</td>
		<td colspan="3">
			<div class="crm_followup_leightbox_note">
				{$form_note.html}
			</div>
        </td>
	</tr>
</table>
        <!-- MY -->
<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
        <td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 80px;">
		<div class="content_shadow">
<!-- -->

	    {$new_event.open}
		<div class="big-button">
	        <img src="{$theme_dir}/CRM/Calendar/icon.png" alt="" align="middle" border="0" width="32" height="32">
	        <div style="height: 5px;"></div>
	        <span>{$new_event.text}</span>
        </div>
	    {$new_event.close}

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

        </td>

        <!-- ALL -->
        <td>

<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 80px;">
		<div class="content_shadow">
<!-- -->

	    {$new_task.open}
		<div class="big-button">
            <img src="{$theme_dir}/CRM/Tasks/icon.png" alt="" align="middle" border="0" width="32" height="32">
            <div style="height: 5px;"></div>
            <span>{$new_task.text}</span>
        </div>
	    {$new_task.close}

<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

        </td>

        <!-- MANAGE FILTERS -->
        <td>
<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 100px;">
		<div class="content_shadow">
<!-- -->



	    {$new_phonecall.open}
		<div class="big-button">
            <img src="{$theme_dir}/CRM/PhoneCall/icon.png" alt="" align="middle" border="0" width="32" height="32">
            <div style="height: 5px;"></div>
            <span>{$new_phonecall.text}</span>
        </div>
	    {$new_phonecall.close}


<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

        </td>
        <td>
<!-- SHADIW BEGIN -->
	<div class="layer" style="padding: 8px; width: 100px;">
		<div class="content_shadow">
<!-- -->



	    {$just_close.open}
		<div class="big-button">
            <img src="{$theme_dir}/Base/ActionBar/icons/folder.png" alt="" align="middle" border="0" width="32" height="32">
            <div style="height: 5px;"></div>
            <span>{$just_close.text}</span>
        </div>
	    {$just_close.close}


<!-- SHADOW END -->
 		</div>
		<div class="shadow-top">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-middle">
			<div class="left"></div>
			<div class="right"></div>
		</div>
		<div class="shadow-bottom">
			<div class="left"></div>
			<div class="center"></div>
			<div class="right"></div>
		</div>
	</div>
<!-- -->

        </td>
    </tr>
</table>
{$form_close}

</center>
