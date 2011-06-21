<center>
<br>
<table id="CRM_Filters" cellspacing="0" cellpadding="0">
	<tr>
{foreach key=k item=cd from=$custom_defaults}
	        <td>
				<div class="css3_content_shadow_new_record">
					{$cd.open}
					<div class="new_record_big-button">
						<img src="{$cd.icon}">
						<table class="new_record_icon_table_text"><tr class="new_record_icon_tr_text"><td class="new_record_icon_td_text">{$cd.label}</td></tr></table>
					</div>
					{$cd.close}
				</div>


	        </td>
{/foreach}
    </tr>
</table>

</center>


