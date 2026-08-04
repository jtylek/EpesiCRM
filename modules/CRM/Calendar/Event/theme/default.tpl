{php}
	$theme_dir = $this->get_template_vars('theme_dir');
{/php}

<div class="CRM_Calendar_Event__header">
	<div class="icon"><img src="{$theme_dir}/CRM/Calendar/icon.png" width="32" height="32" border="0"></div>
	<div class="name">{if $action == 'view'}View{else}Edit{/if} Event</div>
	<div class="required_fav_info">
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
	</div>
</div>

{$form_open}

<div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 98%;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    {* Was a <table> with a <td rowspan="2"> (header-new date box) sitting
       beside the LEFT/RIGHT columns and the emp_id/cus_id row below them -
       flex instead: the date box is a flex sibling of a second flex item
       that itself stacks the LEFT+RIGHT row and the emp_id/cus_id row
       vertically, same visual result, no rowspan equivalent needed. *}
    <div style="display: flex; align-items: flex-start; width: 100%;">
                {if $action == 'view'}
                <!-- NEW HEADER -->
                <div style="flex: 0 0 auto;">
                    <div class="header-new">
                        <div class="weekday green">{$day_details.start.weekday}</div>
                        <div></div>
                        <div class="weekday green">{if $event_info.start_date != $event_info.end_date}{$day_details.end.weekday}{/if}</div>
                        <div class="day black">{$day_details.start.day}</div>
                        <div class="day black">{if $event_info.start_date != $event_info.end_date}&nbsp;-&nbsp;{/if}</div>
                        <div class="day black">{if $event_info.start_date != $event_info.end_date}{$day_details.end.day}{/if}</div>
                        <div class="month blue">{$day_details.start.month}&nbsp;{$day_details.start.year}</div>
                        <div></div>
                        <div class="month blue">{if $event_info.start_date != $event_info.end_date}{$day_details.end.month}&nbsp;{$day_details.start.year}{/if}</div>
                        <div class="time black" style="grid-column: 1 / -1;">
                            {if $event_info.start_time != "timeless"}
                                {$event_info.start_time}&nbsp;-&nbsp;{$event_info.end_time}
                            {else}
                                timeless
                            {/if}
                        </div>
                        <div class="duration dark-gray" style="grid-column: 1 / -1;">
                            {if $event_info.start_time != "timeless"}
                                {$event_info.duration} hr(s)
                            {/if}
                        </div>
                    </div>
                </div>
                {/if}
                <div style="flex: 1 1 auto; min-width: 0;">
                <div style="display: flex; align-items: flex-start;">
                <!-- LEFT -->
                <div style="width: 50%; vertical-align: top;">
                    {* title *}
                    <div class="form">
                            <div class="form-row">
                                <div class="label group_bottom title" style="width: 20%;">{$form_data.title.label}</div>
                                <div class="data group_bottom title" style="width: 80%"><span class="error">{$form_data.title.error}</span>
                                    {$form_data.title.html}
                                </div>
                            </div>
                    </div>
                    {* description *}
                    <div class="form no-border">
                            <div class="form-row solo">
                                <div class="label" style="border-bottom: none; border-right: none;">{$form_data.description.label}</div>
                            </div>
                            <div class="form-row solo">
                                <div class="data" style="vertical-align: top; border-right: none; padding: 3px 4px 3px 0px; height: {if $action == 'view'}53px;{else}142px;{/if}">
                                    {if $action == 'view'}<div style="height: {if $action == 'view'}53px;{else}142px;{/if} padding-left: 2px; white-space: normal; overflow: auto;">{/if}
                                        {$form_data.description.html}
                                    {if $action == 'view'}</div>{/if}
                                </div>
                            </div>
                    </div>
                </div>
                <!-- -->
                <!-- RIGHT -->
                <div style="width: 50%; vertical-align: top;">
                    {* start - end *}
                    {if $action != 'view'}
                    <div class="form no-border">
                                <div class="form-row solo">
                                    <div class="label" style="border-right: 1px solid #b3b3b3; width: 40%; height: 21px;">{$form_data.date_s.label}</div>
                                </div>
                                <div class="form-row solo">
                                    <div class="data" style="border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.date_s.error}</span><div class="time_s" style="float: left; width: 200px; border-bottom: 1px solid #b3b3b3; text-align: center;">{$form_data.date_s.html}</div><span id="time_s">{$form_data.time_s.html}</span></div>
                                </div>
                                <div class="form-row solo">
                                    <div class="label" style="width: 60%; padding-right: 0px; height: 21px; vertical-align: top;"><div style="float: left; margin-top: 3px;">{$form_data.duration.label} / {$form_data.time_e.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$toggle_duration}</div></div>
                                </div>
                                <div class="form-row solo">
                                    <div class="data" style="height: 20px;">
                                        <div id="{$duration_block_id}"><span class="error">{$form_data.duration.error}</span><div style="float: left; width: 200px;"><span id="duration">{$form_data.duration.html}</span></div></div>
                                        <div id="{$event_end_block_id}"><span class="error">{$form_data.time_e.error}</span><span id="time_e">{$form_data.time_e.html}</span></div>
                                    </div>
                                </div>
                    </div>
                    {/if}
                    {* timeless access priority color *}
                    <div class="form" style="border-left: none;">
                            <div class="form-row">
                                <div class="label" style="width: 20%;">{$form_data.timeless.label}</div>
                                <div class="data" style="width: 80%;">{$form_data.timeless.html}</div>
                            </div>
                            <div class="form-row">
                                <div class="label">{$form_data.access.label}</div>
                                <div class="data access">
                                    {if $action=='view'}
                                        <div class="icon access_{$access_id}"></div>
                                    {/if}
                                    {$form_data.access.html}
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="label">{$form_data.priority.label}</div>
                                <div class="data priority">
                                    {if $action=='view'}
                                        <div class="icon priority_{$priority_id}"></div>
                                    {/if}
                                    {$form_data.priority.html}
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="label">{$form_data.color.label}</div>
                                <div class="data">
                                    <span class="color_{$color_id}">
                                        {$form_data.color.html}
                                    </span>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="label">{$form_data.status.label}</div>
                                <div class="data status">
                                    {if $action=='view'}
                                        <div class="icon status_{$status_id}"></div>
                                    {/if}
                                    {$form_data.status.html}
                                </div>
                            </div>
                            {foreach item=f from=$custom_fields}
	                            <div class="form-row">
	                                <div class="label">{$form_data.$f.label}</div>
	                                <div class="data">
	                                    {$form_data.$f.html}
	                                </div>
	                            </div>
	                         {/foreach}
                    </div>
                </div>
                </div>
                <div class="form no-border" style="border-right: 1px solid #b3b3b3;">
                        <div class="form-row">
                            <div class="label" style="width: 50%; border-bottom: none; border-right: 1px solid #b3b3b3;">{$form_data.emp_id.label}</div>
                            <div class="label" style="width: 50%; padding-right: 0px; border-bottom: none;"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div></div>
                        </div>
                        <div class="form-row">
                            <div class="data arrows" style="vertical-align: top; border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</div>
                            <div class="data" style="vertical-align: top;"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</div>
                        </div>
                </div>
                </div>
    </div>
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
    <div style="display: flex; align-items: flex-start; width: 100%;">
                <div style="width: 50%; vertical-align: top;">
                    <div id="recurrence" style="padding-top: 5px;">
                        <div class="form" style="border-left: none;">
                                <div class="form-row">
                                    <div class="label" style="width: 30%;">{$form_data.recurrence.label}</div>
                                    <div class="data" style="width: 70%;">{$form_data.recurrence.html}</div>
                                </div>
                        </div>
                        <div id="{$recurrence_block}">
                            <div class="form" style="border-left: none;">
                                    <div class="form-row">
                                        <div class="label" style="width: 30%;">{$form_data.recurrence_interval.label}</div>
                                        <div class="data" style="width: 70%;">{$form_data.recurrence_interval.html}</div>
                                    </div>
                                    <div class="form-row">
                                        <div class="label">{$form_data.recurrence_no_end_date.label}*</div>
                                        <div class="data"><span class="error">{$form_data.recurrence_no_end_date.error}</span>{$form_data.recurrence_no_end_date.html}</div>
                                    </div>
                                    <div class="form-row">
                                        <div class="label">{$form_data.recurrence_end_date.label}*</div>
                                        <div class="data"><span class="error">{$form_data.recurrence_end_date.error}</span>{$form_data.recurrence_end_date.html}</div>
                                    </div>
                            </div>
                            <span id="{$recurrence_custom_days}">{$form_data.custom_days.error}{$form_data.custom_days.html}</span>
                        </div>
                    </div>
                </div>
                <div style="width: 50%; vertical-align: top;">
                    {if $action=='new'}
                        <div id="alert" style="padding-top: 5px;">
                            <div class="form" style="border-left: none;">
                                    <div class="form-row">
                                        <div class="label" style="width: 30%;">{$form_data.messenger_on.label}*</div>
                                        <div class="data" style="width: 70%;"><span class="error">{$form_data.messenger_on.error}</span>{$form_data.messenger_on.html}</div>
	                                </div>
        	                </div>
            	            <div id="{$messenger_block}">
                            <div class="form" style="border-left: none;">
                                    <div class="form-row">
                                        <div class="label" style="width: 30%;">{$form_data.messenger_before.label}*</div>
                                        <div class="data" style="width: 70%;"><span class="error">{$form_data.messenger_before.error}</span>{$form_data.messenger_before.html}</div>
                                    </div>
                                    <div class="form-row">
                                        <div class="label">{$form_data.messenger_message.label}*</div>
                                        <div class="data smalltext"><span class="error">{$form_data.messenger_message.error}</span>{$form_data.messenger_message.html}</div>
                                    </div>
                            </div>
                        </div>
                    {/if}
                </div>
    </div>
</div>


</div>


    {if isset($tabs)}
        <div class="attachments_messages">
		{$tabs}
        </div>
    {/if}

</form>
