{* Get total number of fields to display *}

<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
			<td class="name">{$caption}</td>
			<td class="required_fav_info">
				&nbsp;*&nbsp;{$required_note}
				{if isset($fav_tooltip)}
					&nbsp;&nbsp;&nbsp;{$fav_tooltip}
				{/if}
				{if isset($info_tooltip)}
					&nbsp;&nbsp;&nbsp;{$info_tooltip}
				{/if}
				{if isset($history_tooltip)}
					&nbsp;&nbsp;&nbsp;{$history_tooltip}
				{/if}
			</td>
		</tr>
	</tbody>
</table>

<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 750px;">
		<div class="content_shadow">
<!-- -->


<div style="background-color: white; padding: 5px;">

<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="label" align="left" style="width: 10%;">{$form_data.subject.label}</td>
			<td class="group_bottom data title" align="left">
				<span class="error">
					{$form_data.subject.error}
				</span>
	            {$form_data.subject.html}
            </td>
		</tr>
    </tbody>
</table>
{* description *}
<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
        {if $action != 'view'}
    	<tr>
            <td class="label">{$form_data.description.label}</td>
        </tr>
        {/if}
    	<tr>
            <td class="data" colspan="4" style="vertical-align: top; padding-top: 2px;">
                {if $action == 'view'}<div style="height: 50px; white-space: normal; overflow: auto;">{/if}
                    {$form_data.description.html}
                {if $action == 'view'}</div>{/if}
            </td>
        </tr>
    </tbody>
</table>
{* employees contact *}
<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
	<tbody>
        <tr>
        	<td style="width: 50%;" colspan="2">
				<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
					<tr>
			    	    <td class="label" style="width: 40%; padding-right: 0px;">{$form_data.company_name.label}</td>
			    	    <td class="data" style="width: 60%; padding-right: 0px;" colspan="2">
			    	    	<span class="error">
								{$form_data.company_name.error}
							</span>
							{$form_data.company_name.html}
						</td>
					</tr>
			        <tr>
			    	    <td class="label" style="padding-right: 0px;">{$form_data.contact.label}</td>
			    	    <td class="data" style="padding-right: 0px;" colspan="2">
			    	    	<span class="error">
								{$form_data.contact.error}
							</span>
							{$form_data.contact.html}
						</td>
					</tr>
			        <tr>
			    	    <td class="label" style="padding-right: 0px;">{$form_data.other_contact.label}</td>
			    	    <td class="data" style="padding-right: 0px;">
							{$form_data.other_contact.html}
						</td>
			    	    <td class="data" style="padding-right: 0px;">
			    	    	<span class="error">
								{$form_data.other_contact_name.error}
							</span>
							{$form_data.other_contact_name.html}
						</td>
					</tr>
			        <tr>
			    	    <td class="label" style="padding-right: 0px;">{$form_data.phone.label}</td>
			    	    <td class="data" style="padding-right: 0px;" colspan="2">
			    	    	<span class="error">
								{$form_data.phone.error}
							</span>
							{$form_data.phone.html}
						</td>
					</tr>
			        <tr>
			    	    <td class="label" style="padding-right: 0px;">{$form_data.other_phone.label}</td>
			    	    <td class="data" style="padding-right: 0px;">
							{$form_data.other_phone.html}
						</td>
			    	    <td class="data" style="padding-right: 0px;">
			    	    	<span class="error">
								{$form_data.other_phone_number.error}
							</span>
							{$form_data.other_phone_number.html}
						</td>
					</tr>
				</table>
        	</td>
        	<td style="width: 50%;" colspan="6">
				<table id="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0">
					<tr>
			        	<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.employees.label}</td>
			        </tr>
					<tr>
						<td class="data" style="vertical-align: top;"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}</td>
					</tr>
				</table>
        	</td>
        </tr>
		<tr>
		  	<td class="label" align="left">{$form_data.date_and_time.label}</td>
			<td class="data timestamp" align="left">{$form_data.date_and_time.html}</td>

		  	<td class="label" align="left">{$form_data.status.label}</td>
			<td class="data status" align="left">{$form_data.status.html}</td>

            <td class="label" align="left">{$form_data.permission.label}</td>
			<td class="data permission" align="left">
                {if $action=='view'}
                    <div class="icon"></div>
                {/if}
                {$form_data.permission.html}
            </td>

            <td class="label" align="left">{$form_data.priority.label}</td>
			<td class="data priority" align="left">
                {if $action=='view'}
                    <div class="icon"></div>
                {/if}
                {$form_data.priority.html}
            </td>
		</tr>
    </tbody>
</table>

</div>

{php}
	eval_js('focus_by_id(\'subject\');');
{/php}

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
