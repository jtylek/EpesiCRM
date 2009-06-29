{php}
	$theme_dir = $this->get_template_vars('theme_dir');
{/php}

<table class="CRM_Calendar_Event__header" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<td class="icon"><img src="{$theme_dir}/CRM/Calendar/icon.png" width="32" height="32" border="0"></td>
			<td class="name">{if $action == 'view'}View{else}Edit{/if} Event</td>
			<td class="required_fav_info">
                {if isset($subscribe_icon)}
                    &nbsp;&nbsp;&nbsp;{$subscribe_icon}
                {/if}
                {if isset($info_tooltip)}
                    &nbsp;&nbsp;&nbsp;{$info_tooltip}
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

{$form_open}

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
                                        {$event_info.duration} hr(s)
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
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="label" style="border-bottom: none; border-right: none;">{$form_data.description.label}</td>
                            </tr>
                            <tr>
                                <td class="data" style="vertical-align: top; border-right: none; padding: 3px 4px 3px 0px; height: {if $action == 'view'}53px;{else}142px;{/if}">
                                    {if $action == 'view'}<div style="height: {if $action == 'view'}53px;{else}142px;{/if} padding-left: 2px; white-space: normal; overflow: auto;">{/if}
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
                                    <td class="label" style="border-right: 1px solid #b3b3b3; width: 40%; height: 21px;">{$form_data.date_s.label}</td>
                                </tr>
                                <tr>
                                    <td class="data" style="border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.date_s.error}</span><div class="time_s" style="float: left; width: 200px; border-bottom: 1px solid #b3b3b3; text-align: center;">{$form_data.date_s.html}</div><span id="time_s">{$form_data.time_s.html}</span></td>
                                </tr>
                                <tr>
                                    <td class="label" style="width: 60%; padding-right: 0px; height: 21px; vertical-align: top;"><div style="float: left; margin-top: 3px;">{$form_data.duration.label} / {$form_data.time_e.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$toggle_duration}</div></td>
                                </tr>
                                <tr>
                                    <td class="data" style="height: 20px;">
                                        <div id="{$duration_block_id}"><span class="error">{$form_data.duration.error}</span><div style="float: left; width: 200px;"><span id="duration">{$form_data.duration.html}</span></div></div>
                                        <div id="{$event_end_block_id}"><span class="error">{$form_data.time_e.error}</span><span id="time_e">{$form_data.time_e.html}</span></div>
                                    </td>
                                </tr>
                        </tbody>
                    </table>
                    {/if}
                    {* timeless access priority color *}
                    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                        <tbody>
                            <tr>
                                <td class="label" align="left" style="width: 20%;">{$form_data.timeless.label}</td>
                                <td class="data" align="left" style="width: 80%;">{$form_data.timeless.html}</td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.access.label}</td>
                                <td class="data access" align="left">
                                    {if $action=='view'}
                                        <div class="icon access_{$access_id}"></div>
                                    {/if}
                                    {$form_data.access.html}
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.priority.label}</td>
                                <td class="data priority" align="left">
                                    {if $action=='view'}
                                        <div class="icon priority_{$priority_id}"></div>
                                    {/if}
                                    {$form_data.priority.html}
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.color.label}</td>
                                <td class="data" align="left">
                                    <span class="color_{$color_id}">
                                        {$form_data.color.html}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="label" align="left">{$form_data.status.label}</td>
                                <td class="data status" align="left">
                                    {if $action=='view'}
                                        <div class="icon status_{$status_id}"></div>
                                    {/if}
                                    {$form_data.status.html}
                                </td>
                            </tr>
                            {foreach item=f from=$custom_fields}
	                            <tr>
	                                <td class="label" align="left">{$form_data.$f.label}</td>
	                                <td class="data" align="left">
	                                    {$form_data.$f.html}
	                                </td>
	                            </tr>
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
                                <td class="label" style="width: 50%; border-bottom: none; border-right: 1px solid #b3b3b3;">{$form_data.emp_id.label}</td>
                                <td class="label" style="width: 50%; padding-right: 0px; border-bottom: none;"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div></td>
                            </tr>
                            <tr>
                                <td class="data arrows" style="vertical-align: top; border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
                                <td class="data" style="vertical-align: top;"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
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


<div style="padding-left: 20px; padding-right: 20px;">
    <table cellspacing="0" cellpadding="0" border="0" style="width: 100%;">
        <tbody>
            <tr>
                <td style="width: 50%; vertical-align: top;">
                    <div id="recurrence" style="padding-top: 5px;">
                        <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                            <tbody>
                                <tr>
                                    <td class="label" align="left" style="width: 30%;">{$form_data.recurrence.label}</td>
                                    <td class="data" align="left" style="width: 70%;">{$form_data.recurrence.html}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="{$recurrence_block}">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.recurrence_interval.label}</td>
                                        <td class="data" align="left" style="width: 70%;">{$form_data.recurrence_interval.html}</td>
                                    </tr>
                                    <tr>
                                        <td class="label" align="left">{$form_data.recurrence_no_end_date.label}*</td>
                                        <td class="data" align="left"><span class="error">{$form_data.recurrence_no_end_date.error}</span>{$form_data.recurrence_no_end_date.html}</td>
                                    </tr>
                                    <tr>
                                        <td class="label" align="left">{$form_data.recurrence_end_date.label}*</td>
                                        <td class="data" align="left"><span class="error">{$form_data.recurrence_end_date.error}</span>{$form_data.recurrence_end_date.html}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <span id="{$recurrence_custom_days}">{$form_data.custom_days.error}{$form_data.custom_days.html}</span>
                        </div>
                    </div>
                </td>
                <td style="width: 50%; vertical-align: top;">
                    {if $action=='new'}
                        <div id="alert" style="padding-top: 5px;">
                            <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" style="border-left: none;" cellspacing="0" cellpadding="0" border="0">
                                <tbody>
                                    <tr>
                                        <td class="label" align="left" style="width: 30%;">{$form_data.messenger_on.label}*</td>
                                        <td class="data" align="left" style="width: 70%;"><span class="error">{$form_data.messenger_on.error}</span>{$form_data.messenger_on.html}</td>
	                                </tr>
    	                        </tbody>
        	                </table>
            	            <div id="{$messenger_block}">
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
                    {/if}
                </td>
            </tr>
        </tbody>
    </table>
</div>


</div>


    {if isset($tabs)}
        <div class="attachments_messages">
		{$tabs}
        </div>
    {/if}

</form>
