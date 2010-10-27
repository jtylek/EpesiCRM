{php}
	$this->_tpl_vars['fdow'] = Utils_PopupCalendarCommon::get_first_day_of_week();
	$this->_tpl_vars['fdow']--;
	if ($this->_tpl_vars['fdow']<0) $this->_tpl_vars['fdow']+=7;
{/php}
<table class="CRM_Calendar_Event__header" border="0" cellpadding="0" cellspacing="0">
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
				{if isset($clipboard_tooltip)}
					&nbsp;&nbsp;&nbsp;{$clipboard_tooltip}
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
<div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    <table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
        <tbody>
            <tr>
                {if $action == 'view'}
                <!-- NEW HEADER -->
                <td rowspan="2">
                    <table border="0" class="header-new">
                        <tbody>
                            <tr>
                                <td class="weekday green">{$day_details.start.weekday}</td>
                                <td></td>
                                <td class="weekday green">{if $event_info.start_date != $event_info.end_date}{$day_details.end.weekday}{/if}</td>
                            </tr>
                            <tr>
                                <td class="day black">{$day_details.start.day}</td>
                                <td class="day black">{if $event_info.start_date != $event_info.end_date}&nbsp;-&nbsp;{/if}</td>
                                <td class="day black">{if $event_info.start_date != $event_info.end_date}{$day_details.end.day}{/if}</td>
                            </tr>
                            <tr>
                                <td class="month blue">{$day_details.start.month}&nbsp;{$day_details.start.year}</td>
                                <td></td>
                                <td class="month blue">{if $event_info.start_date != $event_info.end_date}{$day_details.end.month}&nbsp;{$day_details.start.year}{/if}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="time black">
                                    {if $event_info.start_time != "timeless"}
                                        {$event_info.start_time}&nbsp;-&nbsp;{$event_info.end_time}
                                    {else}
                                        timeless
                                    {/if}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="duration dark-gray">
                                    {if $event_info.start_time != "timeless"}
                                        {$event_info.duration}
                                    {/if}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                {/if}
                <!-- LEFT -->
                <td style="width: 50%; height: 101px; vertical-align: top;">
                    {* title *}
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="group_bottom label title" align="left" style="width: 20%;">{$form_data.title.label}</td>
                                <td class="group_bottom data title" align="left" style="width: 80%"><span class="error">{$form_data.title.error}</span>
                                    {$form_data.title.html}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    {* description *}
                    <table style="height:100%" name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="label" style="border-bottom: none; border-right: none;">{$form_data.description.label}</td>
                            </tr>
                            <tr>
                                <td class="data" style="vertical-align: top; border-right: none; padding: 3px 4px 3px 0px;">
                                    {if $action == 'view'}<div style="padding-left: 2px; white-space: normal; overflow: auto;">{/if}
                                        {$form_data.description.html}
                                    {if $action == 'view'}</div>{/if}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </td>
                <!-- -->
                <!-- RIGHT -->
                <td style="width: 50%; height: 101px; vertical-align: top;">
                    {* start - end *}
                    {if $action != 'view'}
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                                <tr>
                                    <td class="label" style="border-right: 1px solid #b3b3b3; width: 40%; height: 21px;">{$form_data.date.label}</td>
                                </tr>
                                <tr>
                                    <td class="data" style="border-right: 1px solid #b3b3b3; height: 20px; "><span class="error">{$form_data.date.error}</span><div class="time_s" style="float: left; width: 200px; text-align: center;">{$form_data.date.html}</div><span id="time_s">{$form_data.time.html}</span></td>
                                </tr>
                                <tr>
                                    <td class="label" style="width: 60%; padding-right: 0px; height: 21px; vertical-align: top;"><div style="float: left; margin-top: 3px;">{$form_data.duration.label} / {$form_data.end_time.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$form_data.toggle.html}</div></td>
                                </tr>
                                <tr>
                                    <td class="data" style="height: 20px;">
                                        <div id="crm_calendar_duration_block"><span class="error">{$form_data.duration.error}</span><div style="float: left; width: 200px;"><span id="duration">{$form_data.duration.html}</span></div></div>
                                        <div id="crm_calendar_event_end_block"><span class="error">{$form_data.end_time.error}</span><span id="time_e">{$form_data.end_time.html}</span></div>
                                    </td>
                                </tr>
                        </tbody>
                    </table>
                    {/if}
                    {* timeless permission priority color *}
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="label" align="left" style="width: 20%;">{$form_data.timeless.label}</td>
                                <td class="data" align="left" style="width: 80%;" colspan="2">{$form_data.timeless.html}</td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.permission.label}</td>
                                <td class="data permission" align="left" colspan="2">
					<span class="error">{$form_data.permission.error}</span>
                                    {if $action=='view'}
                                        <div class="icon permission_{$raw_data.permission}"></div>
                                    {/if}
                                    {$form_data.permission.html}
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.priority.label}</td>
                                <td class="data priority" align="left" colspan="2">
					<span class="error">{$form_data.priority.error}</span>
                                    {if $action=='view'}
                                        <div class="icon priority_{$raw_data.priority}"></div>
                                    {/if}
                                    {$form_data.priority.html}
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.status.label}</td>
                                <td class="data status" align="left" colspan="2">
					<span class="error">{$form_data.status.error}</span>
                                    {if $action=='view'}
                                        <div class="icon status_{$raw_data.status}"></div>
                                    {/if}
                                    {$form_data.status.html}
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.recurrence_type.label}</td>
                                <td class="data" align="left" colspan="2">
                                    {$form_data.recurrence_type.html}
                                </td>
                            </tr>
			    {if isset($form_data.recurrence_start_date)}
				    <tr id="recurrence_start_date_row">
					<td class="label" align="left">{$form_data.recurrence_start_date.label}</td>
					<td class="data" align="left" colspan="2">
						<span id="recurrence_start_date_span">
							{$form_data.recurrence_start_date.html}
						</span>
					</td>
				    </tr>
			    {/if}
                            <tr id="recurrence_end_date_row">
                                <td class="label" align="left">{$form_data.recurrence_end.label}</td>
                                {if isset($form_data.recurrence_end_checkbox)}
									<td class="data" align="left" style="width:10px;">
										{$form_data.recurrence_end_checkbox.html}
									</td>
								<td class="data" align="left">
								{else}
                                <td class="data" align="left" colspan="2">
								{/if}
									<span id="recurrence_end_date_span">
										{$form_data.recurrence_end.html}
									</span>
                                </td>
                            </tr>
                            <tr id="recurrence_hash_row">
                                <td class="label" align="left">{$form_data.recurrence_hash.label}</td>
                                <td class="data" align="left" colspan="2">
					<span class="error">{$form_data.recurrence_hash.error}</span>
									<table>
										<tr>
											{if $fdow<=0}<td>{$form_data.recurrence_hash_0.label}</td>{/if}
											{if $fdow<=1}<td>{$form_data.recurrence_hash_1.label}</td>{/if}
											{if $fdow<=2}<td>{$form_data.recurrence_hash_2.label}</td>{/if}
											{if $fdow<=3}<td>{$form_data.recurrence_hash_3.label}</td>{/if}
											{if $fdow<=4}<td>{$form_data.recurrence_hash_4.label}</td>{/if}
											{if $fdow<=5}<td>{$form_data.recurrence_hash_5.label}</td>{/if}
											<td>{$form_data.recurrence_hash_6.label}</td>
											{if $fdow>0}<td>{$form_data.recurrence_hash_0.label}</td>{/if}
											{if $fdow>1}<td>{$form_data.recurrence_hash_1.label}</td>{/if}
											{if $fdow>2}<td>{$form_data.recurrence_hash_2.label}</td>{/if}
											{if $fdow>3}<td>{$form_data.recurrence_hash_3.label}</td>{/if}
											{if $fdow>4}<td>{$form_data.recurrence_hash_4.label}</td>{/if}
											{if $fdow>5}<td>{$form_data.recurrence_hash_5.label}</td>{/if}
										</tr>
										<tr>
											{if $fdow<=0}<td>{$form_data.recurrence_hash_0.html}</td>{/if}
											{if $fdow<=1}<td>{$form_data.recurrence_hash_1.html}</td>{/if}
											{if $fdow<=2}<td>{$form_data.recurrence_hash_2.html}</td>{/if}
											{if $fdow<=3}<td>{$form_data.recurrence_hash_3.html}</td>{/if}
											{if $fdow<=4}<td>{$form_data.recurrence_hash_4.html}</td>{/if}
											{if $fdow<=5}<td>{$form_data.recurrence_hash_5.html}</td>{/if}
											<td>{$form_data.recurrence_hash_6.html}</td>
											{if $fdow>0}<td>{$form_data.recurrence_hash_0.html}</td>{/if}
											{if $fdow>1}<td>{$form_data.recurrence_hash_1.html}</td>{/if}
											{if $fdow>2}<td>{$form_data.recurrence_hash_2.html}</td>{/if}
											{if $fdow>3}<td>{$form_data.recurrence_hash_3.html}</td>{/if}
											{if $fdow>4}<td>{$form_data.recurrence_hash_4.html}</td>{/if}
											{if $fdow>5}<td>{$form_data.recurrence_hash_5.html}</td>{/if}
										</tr>
									</table>
								</td>
                            </tr>
				{foreach key=k item=f from=$fields name=fields}
					{if (	$k!='title' &&
							$k!='customers' &&
							$k!='duration' &&
							$k!='employees' &&
							$k!='recurrence_type' &&
							$k!='recurrence_hash' &&
							$k!='recurrence_end' &&
							$k!='date' &&
							$k!='time' &&
							$k!='end_time' &&
							$k!='priority' &&
							$k!='status' &&
							$k!='permission')}
					<tr>
						<td class="label" align="left">{$f.label}{if $f.required}*{/if}</td>
						<td class="data" align="left" colspan="2">
							<span class="error">{$f.error}</span>
							{$f.html}
						</td>
					</tr>
					{/if}
				{/foreach}
                        </tbody>
                    </table>
                </td>
            </tr>
            <tr>
                <td colspan="2" style="vertical-align: top;">
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" style="border-right: 1px solid #b3b3b3;" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="label" style="width: 50%; border-bottom: none; border-right: 1px solid #b3b3b3;">{$form_data.employees.label}</td>
                                <td class="label" style="width: 50%; padding-right: 0px; border-bottom: none;"><div style="float: left; padding-top: 3px;">{$form_data.customers.label}</div></td>
                            </tr>
                            <tr>
                                <td class="data" style="vertical-align: top; border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.employees.error}</span>{$form_data.employees.html}</td>
                                <td class="data" style="vertical-align: top;"><span class="error">{$form_data.customers.error}</span>{$form_data.customers.html}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
            {if $action=='add'}
            <tr>
                <td style="width: 50%; vertical-align: top;" colspan=2>
                        <div id="alert" style="padding-top: 5px;">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.messenger_on.label}*</td>
                                        <td class="data" align="left" style="width: 70%;"><span class="error">{$form_data.messenger_on.error}</span>{$form_data.messenger_on.html}</td>
	                                </tr>
    	                        </tbody>
        	                </table>
            	            <div id="messenger_block">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.messenger_before.label}*</td>
                                        <td class="data" align="left" style="width: 70%;"><span class="error">{$form_data.messenger_before.error}</span>{$form_data.messenger_before.html}</td>
                                    </tr>
                                    <tr>
                                        <td class="label" align="left">{$form_data.messenger_message.label}*</td>
                                        <td class="data smalltext" align="left"><span class="error">{$form_data.messenger_message.error}</span>{$form_data.messenger_message.html}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                </td>
	   </tr>
          {/if}
        </tbody>
    </table>
</div>


{php}
	eval_js('focus_by_id(\'event_title\');');
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
