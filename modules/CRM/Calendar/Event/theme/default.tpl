{php}
	load_js('data/Base_Theme/templates/default/CRM_Calendar_Event__default.js');
{/php}

	{$form_open}

    <div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 740px;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">
    {* header *}
    {if $action == 'view'}
    <table name="CRMCalendar" class="form" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
    		<tr>
    			<td class="header-day">
                    <span class="green">{$event_info.start_day}</span>&nbsp;
                    <span class="dark-gray">&bull;</span>&nbsp;
                    <span class="blue">{$event_info.start_date}</span>&nbsp;
                    <span class="dark-gray">&bull;</span>&nbsp;
                    {if $event_info.start_time != "timeless"}
                        <span class="black">{$event_info.start_time}</span>&nbsp;
                        <span class="dark-gray">-</span>&nbsp;
                    {/if}
                    {if $event_info.start_date != $event_info.end_date}
                        <span class="blue">{$event_info.end_date}</span>&nbsp;
                        <span class="dark-gray">&bull;</span>&nbsp;
                    {/if}
                    {if $event_info.end_time != "timeless"}
                        <span class="black">{$event_info.end_time}</span>&nbsp;
                        <span class="dark-gray">&bull;</span>&nbsp;
                    {/if}
                    <span class="dark-gray">{$event_info.duration} hr(s)</span>
                </td>
    		</tr>
        </tbody>
    </table>
    {/if}
    {* title *}
    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			<tr>
				<td class="group_bottom label title" align="left" style="width: 10%">{$form_data.title.label}</td>
				<td class="group_bottom data title" align="left" style="width: 90%"><span class="error">{$form_data.title.error}</span>
                    {$form_data.title.html}
                </td>
			</tr>
        </tbody>
    </table>
    {* start - end *}
    {if $action != 'view'}
    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
                <tr>
                    <td class="label" style="border-right: 1px solid #b3b3b3; width: 50%; height: 21px;">{$form_data.date_s.label}</td>
                    <td class="label" style="width: 50%; padding-right: 0px; height: 21px; vertical-align: top;"><div style="float: left; margin-top: 3px;">{$form_data.duration.label} / {$form_data.date_e.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$toggle_duration}</div></td>
                </tr>
                <tr>
                    <td class="data" style="border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.date_s.error}</span><div style="float: left; width: 200px; border-bottom: 1px solid #b3b3b3;">{$form_data.date_s.html}</div><span id="time_s">{$form_data.time_s.html}</span></td>
                    <td class="data">
                        <div id="{$duration_block_id}"><span class="error">{$form_data.duration.error}</span><div style="float: left; width: 200px;">{$form_data.duration.html}</div></div>
                        <div id="{$event_end_block_id}"><span class="error">{$form_data.date_e.error}</span><div style="float: left; width: 200px; border-bottom: 1px solid #b3b3b3;">{$form_data.date_e.html}</div><span id="time_e">{$form_data.time_e.html}</span></div>
                    </td>
                </tr>
        </tbody>
    </table>
    {/if}
    {* description *}
    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
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
    {* employees customers *}
    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
            <tr>
	        	<td class="label" style="width: 50%; border-right: 1px solid #b3b3b3;">{$form_data.emp_id.label}</td>
        	    <td class="label" style="width: 50%; padding-right: 0px;"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$cus_click}</div></td>
			</tr>
            <tr>
				<td class="data arrows" style="border-right: 1px solid #b3b3b3; vertical-align: top;"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
				<td class="data arrows" style="vertical-align: top;"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</td>
			</tr>
        </tbody>
    </table>
    {* timeless access priority color *}
    <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if} no-border timeless_access_priority_color" cellspacing="0" cellpadding="0" border="0">
        <tbody>
			<tr>
			  	<td class="label" align="left">{$form_data.timeless.label}</td>
				<td class="data" align="left">{$form_data.timeless.html}</td>
				<td class="label" align="left">{$form_data.access.label}</td>
				<td class="data access" align="left">
                    {if $action=='view'}
                        <div class="icon access_{$access_id}"></div>
                    {/if}
                    {$form_data.access.html}
                </td>
			  	<td class="label" align="left">{$form_data.priority.label}</td>
				<td class="data priority" align="left">
                    {if $action=='view'}
                        <div class="icon priority_{$priority_id}"></div>
                    {/if}
                    {$form_data.priority.html}
                </td>
				<td class="label" align="left">{$form_data.color.label}</td>
				<td class="data" align="left">
                    <span class="color_{$color_id}">
                        {$form_data.color.html}
                    </span>
                </td>
			</tr>
        </tbody>
    </table>
    {if $action == 'view'}
        <div class="AdditionalInfoButton" onclick="additional_info_roll('{$theme_dir}')">
            Additional Info&nbsp;&nbsp;&nbsp;<img id="AdditionalInfoImg" src="{$theme_dir}/CRM_Calendar_Event__roll-down.png" width="14" height="14" alt="=" border="0">
        </div>
    {/if}
    <div id="AdditionalInfo" style="display: none;">
        <table name="CRMCalendar" class="form {if $action == 'view'}view{else}edit{/if}" cellspacing="0" cellpadding="0" border="0">
            <tbody>
                {* created by *}
                {if isset($form_data.created_by)}
                <tr>
                    <td class="label" style="width: 25%;">{$form_data.created_by.label}</td>
                    <td class="data" style="width: 25%;">{$form_data.created_by.html}</td>
                    <td class="label" style="width: 25%;">{$form_data.edited_by.label}</td>
                    <td class="data" style="width: 25%;">{$form_data.edited_by.html}</td>
                </tr>
                <tr>
                    <td class="label">{$form_data.created_on.label}</td>
                    <td class="data">{$form_data.created_on.html}</td>
                    <td class="label">{$form_data.edited_on.label}</td>
                    <td class="data">{$form_data.edited_on.html}</td>
                </tr>
                {/if}
            </tbody>
        </table>
    </div>
    {if isset($attachments) || isset($messages)}
        <div class="attachments_messages">
            <br><br>
            {$attachments|default:''}
            <br>
            {$messages|default:''}
        </div>
    {/if}

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

</form>
