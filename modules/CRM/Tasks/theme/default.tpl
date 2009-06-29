<table class="CRM_Tasks__table" border="0" cellpadding="0" cellspacing="0">
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

<div id="CRM_Tasks">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;"> <!-- 750px -->
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
	<table cellpadding="0" cellspacing="0" border="0" style="width: 100%;">
		<tbody>
			<tr>
				{* LEFT column *}
				<td style="width: 50%; vertical-align: top;">
					{* title *}
					<table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0" style="border-right: none;">
						<tbody>
							<tr>
								<td class="group_bottom label title" align="left" style="width: 10%;">{$form_data.title.label}{if $form_data.title.required}*{/if}</td>
								<td class="group_bottom data title" align="left"><span class="error">{$form_data.title.error}</span>
									{$form_data.title.html}
								</td>
							</tr>
						</tbody>
					</table>
					{* description *}
					<table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0" style="border-right: none; border-bottom: none;">
						<tbody>
							<tr>
								<td class="label" style="border-bottom: none; border-right: 1px solid #b3b3b3;">{$form_data.description.label}{if $form_data.description.required}*{/if}</td>
							</tr>
							<tr>
								<td class="data" style="vertical-align: top; padding: 3px 4px 3px 0px; {if $action == 'view'}border-bottom: 2px solid white;{/if}">
									{if isset($form_data.description.error)}
										{$form_data.description.error}
									{/if}
									{assign var=number value=$form_data|@count}
									{assign var=number value=$number-15}
									{assign var=number value=$number*21}
									{assign var=number value=$number+53}
									{if $action=='view'}{assign var=number value=$number-2}{/if}
									<div style="height: {$number}px; white-space: normal; overflow: auto;">
										{$form_data.description.html}
									</div>
								</td>
							</tr>
						</tbody>
					</table>
					{* employees *}
					<table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0" style="padding-top: 1px; border-top: none; border-right: 1px solid #b3b3b3;">
						<tbody>
							<tr>
								<td class="label" style="border-bottom: 1px solid white;">{$form_data.employees.label}{if $form_data.employees.required}*{/if}</td>
							</tr>
							<tr>
								<td class="data arrows" style="vertical-align: top;"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}</td>
							</tr>
						</tbody>
					</table>
				</td>
				{* RIGHT column *}
				<td style="width: 50%; vertical-align: top;">
					<table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0" style="border-left: none;">
						<tbody>
							<tr>
								<td class="label" align="left" style="width: 20%;">{$form_data.status.label}{if $form_data.status.required}*{/if}</td>
								<td class="data status" align="left" style="width: 80%;">
									<span class="error">{$form_data.status.error}</span>
									{if $action == 'view'}
										<div class="icon status_{$raw_data.status}"></div>
									{/if}
									{$form_data.status.html}
								</td>
							</tr>
							<tr>
								<td class="label" align="left">{$form_data.deadline.label}</td>
								<td class="data" align="{if $action == 'view'}left{else}right{/if}" style="padding-right: 0px;" colspan=3>{$form_data.deadline.html}</td>
							</tr>
							<tr>
								<td class="label" align="left">{$form_data.longterm.label}</td>
								<td class="data" align="left">{$form_data.longterm.html}</td>
							</tr>
							<tr>
								<td class="label" align="left">{$form_data.permission.label}{if $form_data.permission.required}*{/if}</td>
								<td class="data permission" align="left">
									<span class="error">{$form_data.permission.error}</span>
									{if $action=='view'}
										<div class="icon permission_{$raw_data.permission}"></div>
									{/if}
									{$form_data.permission.html}
								</td>
							</tr>
							<tr>
								<td class="label" align="left">{$form_data.priority.label}{if $form_data.priority.required}*{/if}</td>
								<td class="data priority" align="left">
									<span class="error">{$form_data.priority.error}</span>
									{if $action=='view'}
										<div class="icon priority_{$raw_data.priority}"></div>
									{/if}
									{$form_data.priority.html}
								</td>
							</tr>
							{foreach key=k item=f from=$fields name=fields}
								{if (	$k!='title' &&
										$k!='employees' &&
										$k!='customers' &&
										$k!='status' &&
										$k!='priority' &&
										$k!='permission' &&
										$k!='longterm' &&
										$k!='deadline')}
								<tr>
									<td class="label" align="left">{$f.label}{if $f.required}*{/if}</td>
									<td class="data" align="left">
										<span class="error">{$f.error}</span>
										{$f.html}
									</td>
								</tr>
								{/if}
							{/foreach}
						</tbody>
					</table>
					{* customers *}
					<table name="UtilsTasks" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0" style="border-left: none; border-top: none;">
						<tbody>
							<tr>
								<td class="label" style="padding-right: 0px; border-bottom: none;"><div style="float: left; padding-top: 3px;">{$form_data.customers.label}</div><div style="float: right; background-color: white; padding-left: 4px;">{if isset($form_data.customers_rpicker_advanced.html)}{$form_data.customers_rpicker_advanced.html}{/if}</div></td>
							</tr>
							<tr>
								<td class="data" style="vertical-align: top;"><span class="error">{$form_data.customers.error}</span>{$form_data.customers.html}</td>
							</tr>
						</tbody>
					</table>
				</td>
			</tr>
		</tbody>
	</table>
</div>


{php}
	eval_js('focus_by_id(\'title\');');
{/php}

<!-- SHADOW END-->
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
