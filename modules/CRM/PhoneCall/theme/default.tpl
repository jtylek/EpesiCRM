<table class="CRM_PhoneCall__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="arrow" style="padding-left: 20px;">
				{if isset($prev_record)}
					{$__link.prev_record.open}<img src="{$theme_dir}/images/big_prev.png" width="24" height="16" border="0" style="vertical-align: middle;">{$__link.prev_record.close}
				{/if}
			</td>
			<td class="icon"><img src="{$icon}" width="32" height="32" border="0"></td>
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
				{foreach item=n from=$new}
					&nbsp;&nbsp;&nbsp;{$n}
				{/foreach}
			</td>
		</tr>
	</tbody>
</table>


{if isset($click2fill)}
    {$click2fill}
{/if}

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
								<td class="label" align="left" style="width: 10%;">{$form_data.subject.label}{if $form_data.subject.required}*{/if}</td>
								<td class="data" align="left" id="_subject__data">
									<span class="error">{$form_data.subject.error}</span>
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
								<td class="data no-border" id="_description__data" style="vertical-align: top; padding-top: 2px; padding-bottom: 2px;">
									{if isset($form_data.description.error)}
										{$form_data.description.error}
									{/if}
									{if $action == 'view'}<div style="height: 55px; white-space: normal; overflow: auto;">{/if}
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
{*						<tr>
							<td class="label" style="width: 20%;">
								&nbsp;
							</td>
							<td class="data" style="width: 80%;" colspan="2">
								&nbsp;
							</td>
						</tr>*}
						{if $action == 'view'}
								<tr>
									<td class="label" style="width: 20%;">{$form_data.customer.label}</td>
									<td class="data" style="width: 80%;" colspan="2" id="_customer__data">
										<span class="error">
											{$form_data.customer.error}
										</span>
										{if $raw_data.other_customer}{$form_data.other_customer_name.html}{else}{$form_data.customer.html}{/if}&nbsp;
									</td>
								</tr>
								<tr>
									<td class="label">{$form_data.phone.label}</td>
									<td class="data" colspan="2" id="_phone__data">
										<span class="error">
											{$form_data.phone.error}
										</span>
										{if $raw_data.other_phone}{$form_data.other_phone_number.html}{else}{$form_data.phone.html}{/if}&nbsp;
									</td>
								</tr>
						{else}
								<tr>
									<td class="label" style="width: 20%;">{$form_data.customer.label}{if $form_data.customer.required}*{/if}</td>
									<td class="data" style="width: 80%;" colspan="2" id="_customer__data">
										<span class="error">
											{$form_data.customer.error}
										</span>
										{$form_data.customer.html}{if $action == 'view'}&nbsp;{/if}
									</td>
								</tr>
								<tr>
									<td class="label">{$form_data.other_customer.label}{if $form_data.other_customer.required}*{/if}</td>
									<td class="data" style="width:1px;" id="_other_customer__data">
										{$form_data.other_customer.html}
									</td>
									<td class="data" style="width:99%;" id="_other_customer_name__data">
										<span class="error">
											{$form_data.other_customer_name.error}
										</span>
										{$form_data.other_customer_name.html}{if $action == 'view'}&nbsp;{/if}
									</td>
								</tr>
								<tr>
									<td class="label">{$form_data.phone.label}{if $form_data.phone.required}*{/if}</td>
									<td class="data" colspan="2" id="_phone__data">
										<span class="error">
											{$form_data.phone.error}
										</span>
										{$form_data.phone.html}{if $action == 'view'}&nbsp;{/if}
									</td>
								</tr>
								<tr>
									<td class="label">{$form_data.other_phone.label}{if $form_data.other_phone.required}*{/if}</td>
									<td class="data" id="_other_phone__data">
										{$form_data.other_phone.html}
									</td>
									<td class="data" id="_other_phone_number__data">
										<span class="error">
											{$form_data.other_phone_number.error}
										</span>
										{$form_data.other_phone_number.html}{if $action == 'view'}&nbsp;{/if}
									</td>
								</tr>
						{/if}
						
						<tr>
							<td class="label" align="left">{$form_data.date_and_time.label}{if $form_data.date_and_time.required}*{/if}</td>
							<td class="data timestamp" id="_date_and_time__data" align="left" colspan="2" style="padding-bottom: 2px;"><span class="error">{$form_data.date_and_time.error}</span>{$form_data.date_and_time.html}{if $action == 'view'}&nbsp;{/if}</td>
						</tr>
						<tr>
							<td class="label" align="left">{$form_data.status.label}{if $form_data.status.required}*{/if}</td>
							<td class="data status" align="left" colspan="2" id="_status__data">
								<span class="error">{$form_data.status.error}</span>
								{if $action=='view'}
									<div class="icon status_{$raw_data.status}"></div>
								{/if}
								{$form_data.status.html}{if $action == 'view'}&nbsp;{/if}
							</td>
						</tr>
						<tr>
							<td class="label" align="left">{$form_data.permission.label}{if $form_data.permission.required}*{/if}</td>
							<td class="data permission" align="left" colspan="2" id="_permission__data">
								<span class="error">{$form_data.permission.error}</span>
								{if $action=='view'}
									<div class="icon permission_{$raw_data.permission}"></div>
								{/if}
								{$form_data.permission.html}{if $action == 'view'}&nbsp;{/if}
							</td>
						</tr>
						<tr>
							<td class="label" align="left">{$form_data.priority.label}{if $form_data.priority.required}*{/if}</td>
							<td class="data priority" align="left" colspan="2" id="_priority__data">
								<span class="error">{$form_data.priority.error}</span>
								{if $action=='view'}
									<div class="icon priority_{$raw_data.priority}"></div>
								{/if}
								{$form_data.priority.html}{if $action == 'view'}&nbsp;{/if}
							</td>
						</tr>
						{foreach key=k item=f from=$fields name=fields}
							{if (	$k!='subject' &&
									$k!='company_name' &&
									$k!='employees' &&
									$k!='related_to' &&
									$k!='status' &&
									$k!='priority' &&
									$k!='permission' &&
									$k!='customer' &&
									$k!='other_customer' &&
									$k!='other_customer_name' &&
									$k!='phone' &&
									$k!='other_phone' &&
									$k!='other_phone_number' &&
									$k!='date_and_time')}
							<tr>
								<td class="label" align="left">{$f.label}{if $f.required}*{/if}</td>
								<td class="data" align="left" colspan="2">
									<span class="error">{$f.error}</span>
									{$f.html}
								</td>
							</tr>
							{/if}
						{/foreach}
					</table>
				</td>
			</tr>
			<tr>
				<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.employees.label}</td>
				<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.related_to.label}</td>
			</tr>
			<tr>
				<td class="data multiselect" id="_employees__data" style="vertical-align: top; {if $action == 'view'}border-left: 1px solid #b3b3b3; border-right: 1px solid #b3b3b3; border-bottom: 1px solid #b3b3b3; padding-left: 4px;{/if}"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}{if $action == 'view'}&nbsp;{/if}</td>
				<td class="data multiselect" id="_related_to__data" style="vertical-align: top; {if $action == 'view'}border-left: 1px solid #b3b3b3; border-right: 1px solid #b3b3b3; border-bottom: 1px solid #b3b3b3; padding-left: 4px;{/if}"><span class="error">{$form_data.related_to.error}</span>{$form_data.related_to.html}{if $action == 'view'}&nbsp;{/if}</td>
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

</div>

