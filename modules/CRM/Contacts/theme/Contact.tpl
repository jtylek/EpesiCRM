{if isset($form_data.paste_company_info)}
{$form_data.paste_company_info.html}
{/if}
{* Get total number of fields to display *}
{foreach key=k item=f from=$fields name=fields}
	{assign var=count value=$smarty.foreach.fields.total}
{/foreach}
{if $count is not even}
	{assign var=rows value=$count+1}
	{assign var=rows value=$rows/2}
{else}
	{assign var=rows value=$count/2}
{/if}
{assign var=x value=0}

<table class="CRM_Contacts__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">
				{if isset($prev_record)}
					{$__link.prev_record.open}<img src="{$theme_dir}/images/big_prev.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.prev_record.close}
				{/if}
			</td>
			<td class="icon"><img src="{$theme_dir}/CRM/Contacts/contacts.png" width="32" height="32" border="0"></td>
			<td class="arrow">
				{if isset($next_record)}
					{$__link.next_record.open}<img src="{$theme_dir}/images/big_next.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.next_record.close}
				{/if}
			</td>
			<td class="name">{$caption}</td>
			<td class="required_fav_info">
				&nbsp;*&nbsp;{$required_note}
				{if isset($subscription_tooltip)}
					&nbsp;&nbsp;&nbsp;{$subscription_tooltip}
				{/if}
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


<!-- SHADOW BEGIN -->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="padding: 2px 2px 2px 2px; background-color: #FFFFFF;">

{* Outside table *}
<table id="CRM_Contacts__Contact" cellpadding="0" cellspacing="0" border="0">
	<tbody>
		<tr>
			<td class="left-column">
				<table border="0" cellpadding="0" cellspacing="0" class="{if $action == 'view'}view{else}edit{/if}">
					<tbody>
						{* create new company *}
						{if isset($form_data.create_company)}
						<tr>
							<td class="label" nowrap>
								{$form_data.create_company.label}
							</td>
							<td class="data create-company" style="width:1px">
								{$form_data.create_company.html}{if $action == 'view'}&nbsp;{/if}
							</td>
							<td class="data">
								{if isset($form_data.create_company_name.error)}<span class="error">{$form_data.create_company_name.error}</span>{/if}{$form_data.create_company_name.html}{if $action == 'view'}&nbsp;{/if}
							</td>
						</tr>
						{else}
							{if $action == 'edit'}
								{* empty *}
								<tr>
									<td class="label" align="left">&nbsp;</td>
									<td class="data" colspan="2" align="left">&nbsp;</td>
								</tr>
							{/if}
						{/if}
						{* login *}
						<tr>
							<td class="label" align="left">{$form_data.login.label}</td>
							{if isset($form_data.create_new_user)}
								<td class="data create-company" style="width:1px" align="left">{$form_data.create_new_user.html}</td>
							{/if}
							<td class="data" {if !isset($form_data.create_new_user)}colspan="2" {/if}align="left">{if isset($form_data.login.error)}<span class="error">{$form_data.login.error}</span>{/if}{$form_data.login.html}{if isset($form_data.new_login)}{$form_data.new_login.html}{/if}</td>
						</tr>
						{* last name *}
						<tr>
							<td class="label" align="left">{$form_data.last_name.label}*</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.last_name.error)}<span class="error">{$form_data.last_name.error}</span>{/if}{$form_data.last_name.html}</td>
						</tr>
						{* first name *}
						<tr>
							<td class="label" align="left">{$form_data.first_name.label}*</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.first_name.error)}<span class="error">{$form_data.first_name.error}</span>{/if}{$form_data.first_name.html}</td>
						</tr>
						{* title *}
						<tr>
							<td class="label" align="left">{$form_data.title.label}</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.title.error)}<span class="error">{$form_data.title.error}</span>{/if}{$form_data.title.html}</td>
						</tr>
						{* work phone *}
						<tr>
							<td class="label" align="left">{$form_data.work_phone.label}</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.work_phone.error)}<span class="error">{$form_data.work_phone.error}</span>{/if}{$form_data.work_phone.html}</td>
						</tr>
						{* mobile phone *}
						<tr>
							<td class="label" align="left">{$form_data.mobile_phone.label}</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.mobile_phone.error)}<span class="error">{$form_data.mobile_phone.error}</span>{/if}{$form_data.mobile_phone.html}</td>
						</tr>
						{* fax *}
						<tr>
							<td class="label" align="left">{$form_data.fax.label}</td>
							<td class="data" colspan="2" align="left">{if isset($form_data.fax.error)}<span class="error">{$form_data.fax.error}</span>{/if}{$form_data.fax.html}</td>
						</tr>
						{* empty *}
						<tr>
							<td class="label" align="left">&nbsp;</td>
							<td class="data" colspan="2" align="left">&nbsp;</td>
						</tr>
						{if $action == 'view'}
							{* empty *}
							<tr>
								<td class="label" align="left">&nbsp;</td>
								<td class="data" colspan="2" align="left">&nbsp;</td>
							</tr>
						{/if}
						{* company name - multiselect *}
						<tr>
							<td class="label" align="left">{$form_data.company_name.label}</td>
							<td class="data" colspan="2" align="left" style="line-height: 16px;">{if isset($form_data.company_name.error)}<span class="error">{$form_data.company_name.error}</span>{/if}{$form_data.company_name.html}</td>
						</tr>
					</tbody>
				</table>
			</td>
			<td class="right-column right-column-{if $action == 'view'}view{else}edit{/if}">
				<table border="0" cellpadding="0" cellspacing="0" class="{if $action == 'view'}view{else}edit{/if}">
					<tbody>
						{* email *}
						<tr>
							<td class="label" align="left">{$form_data.email.label}</td>
							<td class="data" align="left">{if isset($form_data.email.error)}<span class="error">{$form_data.email.error}</span>{/if}{$form_data.email.html}</td>
						</tr>
						{* web address *}
						<tr>
							<td class="label" align="left">{$form_data.web_address.label}</td>
							<td class="data" align="left">{if isset($form_data.web_address.error)}<span class="error">{$form_data.web_address.error}</span>{/if}{$form_data.web_address.html}</td>
						</tr>
						{* address 1 *}
						<tr>
							<td class="label" align="left">{$form_data.address_1.label}</td>
							<td class="data" align="left">{if isset($form_data.address_1.error)}<span class="error">{$form_data.address_1.error}</span>{/if}{$form_data.address_1.html}</td>
						</tr>
						{* address 2 *}
						<tr>
							<td class="label" align="left">{$form_data.address_2.label}</td>
							<td class="data" align="left">{if isset($form_data.address_2.error)}<span class="error">{$form_data.address_2.error}</span>{/if}{$form_data.address_2.html}</td>
						</tr>
						{* city *}
						<tr>
							<td class="label" align="left">{$form_data.city.label}</td>
							<td class="data" align="left">{if isset($form_data.city.error)}<span class="error">{$form_data.city.error}</span>{/if}{$form_data.city.html}</td>
						</tr>
						{* country *}
						<tr>
							<td class="label" align="left">{$form_data.country.label}*</td>
							<td class="data" align="left">{if isset($form_data.country.error)}<span class="error">{$form_data.country.error}</span>{/if}{$form_data.country.html}</td>
						</tr>
						{* zone *}
						<tr>
							<td class="label" align="left">{$form_data.zone.label}</td>
							<td class="data" align="left">{if isset($form_data.zone.error)}<span class="error">{$form_data.zone.error}</span>{/if}{$form_data.zone.html}</td>
						</tr>
						{* postal code *}
						<tr>
							<td class="label" align="left">{$form_data.postal_code.label}</td>
							<td class="data" align="left">{if isset($form_data.postal_code.error)}<span class="error">{$form_data.postal_code.error}</span>{/if}{$form_data.postal_code.html}</td>
						</tr>
						{* permission *}
						<tr>
							<td class="label" align="left">{$form_data.permission.label}*</td>
							<td class="data" align="left">{if isset($form_data.permission.error)}<span class="error">{$form_data.permission.error}</span>{/if}{$form_data.permission.html}</td>
						</tr>
						{* group - multiselect *}
						<tr>
							<td class="label" align="left">{$form_data.group.label}</td>
							<td class="data" align="left" style="line-height: 16px;">{if isset($form_data.group.error)}<span class="error">{$form_data.group.error}</span>{/if}{$form_data.group.html}</td>
						</tr>
					</tbody>
				</table>
			</td>
		</tr>
	</tbody>
</table>


{php}
	eval_js('focus_by_id(\'last_name\');');
{/php}

</div>

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
