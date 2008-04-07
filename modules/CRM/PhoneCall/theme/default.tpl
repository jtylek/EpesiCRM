<table class="CRM_PhoneCall__table" border="0" cellpadding="0" cellspacing="0">
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
	<div class="layer" style="padding: 9px; width: 760px;">
		<div class="content_shadow">
<!-- -->


<div id="CRM_PhoneCall" style="background-color: white; padding: 5px;">

<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
	<tbody>
		<tr>
			<td class="label" align="left" style="width: 10%;">{$form_data.subject.label}</td>
			<td class="group_bottom data title" align="left">
				<span class="error">
					{$form_data.subject.error}
				</span>
	            {$form_data.subject.html}&nbsp;
            </td>
		</tr>
    </tbody>
</table>

{* description *}
<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if} no-border" style="{if $action == 'view'}border-left: 1px solid #b3b3b3;{/if}">
	<tbody>
        {if $action != 'view'}
    	<tr>
            <td class="label no-border">{$form_data.description.label}</td>
        </tr>
        {/if}
    	<tr>
            <td class="data no-border" style="vertical-align: top; padding-top: 2px; padding-bottom: 2px;">
                {if $action == 'view'}<div style="height: 50px; white-space: normal; overflow: auto;">{/if}
                    {$form_data.description.html}&nbsp;
                {if $action == 'view'}</div>{/if}
            </td>
        </tr>
    </tbody>
</table>

{* employees contact *}
<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if} no-border">
	<tbody>
        <tr>
        	<td style="width: 50%; vertical-align: top; {if $action == 'view'}border-right: 1px solid #b3b3b3;{/if}">
				<table cellpadding="0" cellspacing="0" border="0">
					<tr>
			    	    <td class="label" style="width: 35%;">{$form_data.company_name.label}</td>
			    	    <td class="data" style="width: 65%;" colspan="2">
			    	    	<span class="error">
								{$form_data.company_name.error}
							</span>
							{$form_data.company_name.html}&nbsp;
						</td>
					</tr>
			        <tr>
			    	    <td class="label">{$form_data.contact.label}</td>
			    	    <td class="data" colspan="2">
			    	    	<span class="error">
								{$form_data.contact.error}
							</span>
							{$form_data.contact.html}&nbsp;
						</td>
					</tr>
			        <tr>
			    	    <td class="label">{$form_data.other_contact.label}</td>
			    	    <td class="data">
							{$form_data.other_contact.html}&nbsp;
						</td>
			    	    <td class="data">
			    	    	<span class="error">
								{$form_data.other_contact_name.error}
							</span>
							{$form_data.other_contact_name.html}&nbsp;
						</td>
					</tr>
			        <tr>
			    	    <td class="label">{$form_data.phone.label}</td>
			    	    <td class="data" colspan="2">
			    	    	<span class="error">
								{$form_data.phone.error}
							</span>
							{$form_data.phone.html}&nbsp;
						</td>
					</tr>
			        <tr>
			    	    <td class="label">{$form_data.other_phone.label}</td>
			    	    <td class="data">
							{$form_data.other_phone.html}&nbsp;
						</td>
			    	    <td class="data">
			    	    	<span class="error">
								{$form_data.other_phone_number.error}
							</span>
							{$form_data.other_phone_number.html}&nbsp;
						</td>
					</tr>
                    <tr>
                        <td class="label" align="left" style="{if $action == 'view'}border-bottom: none;{/if}">{$form_data.date_and_time.label}</td>
                        <td class="data timestamp" align="left" colspan="2" style="padding-bottom: 2px; {if $action == 'view'}border-bottom: none;{/if}">{$form_data.date_and_time.html}&nbsp;</td>
                    </tr>
				</table>
        	</td>
        	<td style="width: 50%; vertical-align: top;">
				<table id="CRM_PhoneCall" cellpadding="0" cellspacing="0" border="0">
					<tr>
			        	<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.employees.label}</td>
			        </tr>
					<tr>
						<td class="data" style="vertical-align: top;"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}&nbsp;</td>
					</tr>
				</table>
        	</td>
        </tr>
    </tbody>
</table>

<table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
    <tbody>
        <tr>
		  	<td class="label" align="left" style="width: 100px;">{$form_data.status.label}</td>
			<td class="data status" align="left">
                {if $action=='view'}
                    <div class="icon status_{$raw_data.status}"></div>
                {/if}
				{$form_data.status.html}&nbsp;
			</td>

            <td class="label" align="left" style="width: 100px;">{$form_data.permission.label}</td>
			<td class="data permission" align="left">
                {if $action=='view'}
                    <div class="icon permission_{$raw_data.permission}"></div>
                {/if}
                {$form_data.permission.html}&nbsp;
            </td>

            <td class="label" align="left" style="width: 100px;">{$form_data.priority.label}</td>
			<td class="data priority" align="left">
                {if $action=='view'}
                    <div class="icon priority_{$raw_data.priority}"></div>
                {/if}
                {$form_data.priority.html}&nbsp;
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
