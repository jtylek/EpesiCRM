
<table class="Utils_RecordBrowser__table" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td style="width:100px;">
				<div class="name">
					<img alt="&nbsp;" class="icon" src="{$icon}" width="32" height="32" border="0">
					<div class="label">{$caption}</div>
				</div>
			</td>
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

	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="css3_content_shadow">

<div class="Utils_RecordBrowser__container">
	<table class="Utils_RecordBrowser__View_entry" cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
		<tbody>
			<tr>
				{* LEFT column *}
				<td style="width: 50%; vertical-align: top;">
					{* subject *}
					<table style="table-layout:auto;" cellpadding="0" cellspacing="0" border="0" class="{if $action == 'view'}view{else}edit{/if}">
						<tbody>
							<tr>
								<td class="label" align="left" style="width: 25%;">{$form_data.subject.label}{if $form_data.subject.required}*{/if}</td>
								<td class="data" align="left" colspan="2" id="_subject__data">
									<div style="position:relative;">
										<span class="error">{$form_data.subject.error}</span>
										{$form_data.subject.html}
									</div>
								</td>
							</tr>
							{if $action == 'view'}
									<tr>
										<td class="label" style="width: 20%;">{$form_data.customer.label}</td>
										<td class="data" style="width: 80%;" colspan="2" id="_customer__data_mod">
											<div style="position:relative;">
												<span class="error">
													{$form_data.customer.error}
												</span>
												{if $raw_data.other_customer}{$form_data.other_customer_name.html}{else}{$form_data.customer.html}{/if}&nbsp;
											</div>
										</td>
									</tr>
									<tr>
										<td class="label">{$form_data.phone.label}</td>
										<td class="data" colspan="2" id="_phone__data_mod">
											<div style="position:relative;">
												<span class="error">
													{$form_data.phone.error}
												</span>
												{if $raw_data.other_phone}{$form_data.other_phone_number.html}{else}{$form_data.phone.html}{/if}&nbsp;
											</div>
										</td>
									</tr>
							{else}
									<tr>
										<td class="label" style="width: 20%;">{$form_data.customer.label}{if $form_data.customer.required}*{/if}</td>
										<td class="data" style="width: 80%;" colspan="2" id="_customer__data">
											<div style="position:relative;">
												<span class="error">
													{$form_data.customer.error}
												</span>
												{$form_data.customer.html}{if $action == 'view'}&nbsp;{/if}
											</div>
										</td>
									</tr>
									<tr>
										<td class="label">{$form_data.other_customer.label}{if $form_data.other_customer.required}*{/if}</td>
										<td style="width:1px;" id="_other_customer__data">
											{$form_data.other_customer.html}
										</td>
										<td class="data" style="width:99%;" id="_other_customer_name__data">
											<div style="position:relative;">
												<span class="error">
													{$form_data.other_customer_name.error}
												</span>
												{$form_data.other_customer_name.html}{if $action == 'view'}&nbsp;{/if}
											</div>
										</td>
									</tr>
									<tr>
										<td class="label">{$form_data.phone.label}{if $form_data.phone.required}*{/if}</td>
										<td class="data" colspan="2" id="_phone__data">
											<div style="position:relative;">
												<span class="error">
													{$form_data.phone.error}
												</span>
												{$form_data.phone.html}{if $action == 'view'}&nbsp;{/if}
											</div>
										</td>
									</tr>
									<tr>
										<td class="label">{$form_data.other_phone.label}{if $form_data.other_phone.required}*{/if}</td>
										<td id="_other_phone__data">
											{$form_data.other_phone.html}
										</td>
										<td class="data" id="_other_phone_number__data">
											<div style="position:relative;">
												<span class="error">
													{$form_data.other_phone_number.error}
												</span>
												{$form_data.other_phone_number.html}{if $action == 'view'}&nbsp;{/if}
											</div>
										</td>
									</tr>
							{/if}
						</tbody>
					</table>
				</td>
				{* RIGHT column *}
				<td style="width: 50%; vertical-align: top;">
					<table cellpadding="0" cellspacing="0" border="0" class="form {if $action == 'view'}view{else}edit{/if}">
						{$fields.date_and_time.full_field}
						{$fields.status.full_field}
						{$fields.permission.full_field}
						{$fields.priority.full_field}
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
									$k!='date_and_time' &&
                                    $f.type != 'multiselect')}
								{$f.full_field}
							{/if}
						{/foreach}
					</table>
				</td>
			</tr>
			{if !empty($multiselects)}
				<tr>
					{assign var=x value=1}
					{assign var=y value=1}
					{foreach key=k item=f from=$multiselects name=fields}
						{if $y==1}
						<td class="column" style="width: {$cols_percent}%;">
							<table cellpadding="0" cellspacing="0" border="0" class="multiselects {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
						{/if}
						{$f.full_field}
						{if $y==$mss_rows or ($y==$mss_rows-1 and $x>$mss_no_empty)}
							{if $x>$mss_no_empty}
								<tr style="display:none;">
									<td class="label">&nbsp;</td>
									<td class="data">&nbsp;</td>
								</tr>
							{/if}
							{assign var=y value=1}
							{assign var=x value=$x+1}
							</table>
						</td>
						{else}
							{assign var=y value=$y+1}
						{/if}
					{/foreach}
				</tr>
			{/if}
			<tr>
				<td colspan="2">
				<table cellpadding="0" cellspacing="0" border="0" class="longfields {if $action == 'view'}view{else}edit{/if}" style="border-top: none;">
					{foreach key=k item=f from=$longfields name=fields}
						{$f.full_field}
					{/foreach}
				</table>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<!-- SHADOW END -->
 		</div>
	</div>
<!-- -->

</div>

