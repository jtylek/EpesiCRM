<div class=CRM_Calendar_Utils_Dropdown_Hidden id="CRM_Calendar_Utils_Dropdown_{$id}_f" onmouseout="CRM_Calendar_Utils_Dropdown_hide('{$id}')" onmouseover="CRM_Calendar_Utils_Dropdown_show('{$id}')">
	{if $pre_values.count > 0}
		<ul id="CRM_Calendar_Utils_Dropdown_{$id}_f_top" class=CRM_Calendar_Utils_Dropdown_ul onmouseover="CRM_Calendar_Utils_Dropdown_show('{$id}')">
		{foreach from=$pre_values.list item=item}
			<li>{$item}</li>
		{/foreach}
		</ul>
	{/if}
	
	<span class=CRM_Calendar_Utils_Dropdown_Hidden_middle onmouseover="CRM_Calendar_Utils_Dropdown_show('{$id}')">{$current}</span>
	
	{if $values.count > 0}
		<ul id="CRM_Calendar_Utils_Dropdown_{$id}_f_bottom" class=CRM_Calendar_Utils_Dropdown_ul onmouseover="CRM_Calendar_Utils_Dropdown_show('{$id}')">
		{foreach from=$values.list item=item}
			<li>{$item}</li>
		{/foreach}
		</ul>
	{/if}
</div>
<span class=CRM_Calendar_Utils_Dropdown_main id="CRM_Calendar_Utils_Dropdown_{$id}_b" onclick="CRM_Calendar_Utils_Dropdown_show('{$id}')" onmouseout="CRM_Calendar_Utils_Dropdown_hide('{$id}')" >{$current}</span>