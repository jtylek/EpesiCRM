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
				{if isset($__link.new_event.open)}
					&nbsp;&nbsp;&nbsp;{$new_event}
				{/if}
				{if isset($__link.new_task.open)}
					&nbsp;&nbsp;&nbsp;{$new_task}
				{/if}
				{if isset($__link.new_phonecall.open)}
					&nbsp;&nbsp;&nbsp;{$new_phonecall}
				{/if}
			</td>
		</tr>
	</tbody>
</table>

<div id="CRM_PhoneCall">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    <table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
        <tbody>
            <tr>
                {* LEFT column *}
                <td style="width: 50%; vertical-align: top;">
                    {* subject *}
                    <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
                        <tbody>
                            <tr>
                                <td class="label" align="left" style="width: 10%;">{$form_data.subject.label}</td>
                                <td class="data" align="left">
                                    <span class="error">
                                        {$form_data.subject.error}
                                    </span>
                                    {$form_data.subject.html}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    {* description *}
                    <table cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if} no-border" style="{if $action == 'view'}border-left: 1px solid #b3b3b3;{/if}">
                        <tbody>
                            {if $action != 'view'}
                            <tr>
                                <td class="label" style="border-bottom: none; border-right: 1px solid #b3b3b3;">{$form_data.description.label}</td>
                            </tr>
                            {/if}
                            <tr>
                                <td class="data no-border" style="vertical-align: top; padding-top: 2px; padding-bottom: 2px;">
                                    {if isset($form_data.description.error)}
                                        {$form_data.description.error}
                                    {/if}
                                    {if $action == 'view'}<div style="height: 284px; white-space: normal; overflow: auto;">{/if}
                                        {$form_data.description.html}{if $action == 'view'}&nbsp;{/if}
                                    {if $action == 'view'}</div>{/if}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                {* RIGHT column *}
                <td style="width: 50%; vertical-align: top;">
                    <table cellpadding="0" cellspacing="0" border="0" class="form {if $action == 'view'}view{else}edit{/if}">
                        <tr>
                            <td class="label" style="width: 20%;">{$form_data.company_name.label}</td>
                            <td class="data" style="width: 80%;" colspan="2">
                                <span class="error">
                                    {$form_data.company_name.error}
                                </span>
                                {$form_data.company_name.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">{$form_data.contact.label}</td>
                            <td class="data" colspan="2">
                                <span class="error">
                                    {$form_data.contact.error}
                                </span>
                                {$form_data.contact.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">{$form_data.other_contact.label}</td>
                            <td class="data">
                                {$form_data.other_contact.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                            <td class="data">
                                <span class="error">
                                    {$form_data.other_contact_name.error}
                                </span>
                                {$form_data.other_contact_name.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">{$form_data.phone.label}</td>
                            <td class="data" colspan="2">
                                <span class="error">
                                    {$form_data.phone.error}
                                </span>
                                {$form_data.phone.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label">{$form_data.other_phone.label}</td>
                            <td class="data">
                                {$form_data.other_phone.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                            <td class="data">
                                <span class="error">
                                    {$form_data.other_phone_number.error}
                                </span>
                                {$form_data.other_phone_number.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label" align="left">{$form_data.date_and_time.label}</td>
                            <td class="data timestamp" align="left" colspan="2" style="padding-bottom: 2px;">{$form_data.date_and_time.html}{if $action == 'view'}&nbsp;{/if}</td>
                        </tr>
                        <tr>
                            <td class="label" align="left">{$form_data.status.label}</td>
                            <td class="data status" align="left" colspan="2">
                                {if $action=='view'}
                                    <div class="icon status_{$raw_data.status}"></div>
                                {/if}
                                {$form_data.status.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label" align="left">{$form_data.permission.label}</td>
                            <td class="data permission" align="left" colspan="2">
                                {if $action=='view'}
                                    <div class="icon permission_{$raw_data.permission}"></div>
                                {/if}
                                {$form_data.permission.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                        <tr>
                            <td class="label" align="left">{$form_data.priority.label}</td>
                            <td class="data priority" align="left" colspan="2">
                                {if $action=='view'}
                                    <div class="icon priority_{$raw_data.priority}"></div>
                                {/if}
                                {$form_data.priority.html}{if $action == 'view'}&nbsp;{/if}
                            </td>
                        </tr>
                    </table>
                    <table id="CRM_PhoneCall" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.employees.label}</td>
                        </tr>
                        <tr>
                            <td class="data" style="vertical-align: top;"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}{if $action == 'view'}&nbsp;{/if}</td>
                        </tr>
                    </table>
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
