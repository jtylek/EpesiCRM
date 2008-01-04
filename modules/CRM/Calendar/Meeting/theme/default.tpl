	{$form_open}

    <div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 760px;">
		<div class="content_shadow">
<!-- -->

<div style="background-color: white; padding: 5px;">

    <table name="CRMCalendar" class="form no-border" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			<tr>
				<td class="group_bottom label bold" align="left">{$form_data.title.label}</td>
				<td class="group_bottom data bold" align="left" colspan="5"><span class="error">{$form_data.title.error}</span>
                {if !isset($focus) && $form_data.title.type == "text"}
                    {assign var = focus value = $form_data.title}
				{/if}
                {$form_data.title.html}
                </td>
			</tr>
			<tr>
			  	<td class="label" align="left">{$form_data.timeless.label}</td>
				<td class="data short_input" align="left">{$form_data.timeless.html}</td>
				<td class="label" align="left">{$form_data.access.label}</td>
				<td class="data" align="left">{$form_data.access.html}</td>
			  	<td class="label" align="left">{$form_data.priority.label}</td>
				<td class="data" align="left">{$form_data.priority.html}</td>
			</tr>
        </tbody>
    </table>
    <table name="CRMCalendar" class="form no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
			<tr>
			  	<td class="label" style="border-right: 1px solid #b3b3b3; width: 50%;">{$form_data.date_s.label}</td>
			  	<td class="label" style="width: 50%;">{$form_data.date_e.label}</td>
            </tr>
			<tr>
			 	<td class="data short_input" style="border-right: 1px solid #b3b3b3;"><span class="error">{$form_data.date_s.error}</span><div style="float: left">{$form_data.date_s.html}</div><span id="time_s">{$form_data.time_s.html}</span></td>
			 	<td class="data short_input"><span class="error">{$form_data.date_e.error}</span><div style="float: left">{$form_data.date_e.html}</div><span id="time_e">{$form_data.time_e.html}</span></td>
            </tr>
            <tr>
	        	<td class="label" style="border-right: 1px solid #b3b3b3;">{$form_data.emp_id.label}</td>
        	    <td class="label" style="padding-right: 0px;"><div style="float: left; padding-top: 3px;">{$form_data.cus_id.label}</div><div style="float: right; border-left: 1px solid #b3b3b3;">{$cus_click}</div></td>
			</tr>
            <tr>
				<td class="data arrows" style="border-right: 1px solid #b3b3b3; vertical-align: top;"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
				<td class="data arrows" style="vertical-align: top;"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}</td>
			</tr>
        </tbody>
    </table>
    <table name="CRMCalendar" class="form no-border" cellspacing="0" cellpadding="0" border="0">
        <tbody>
            {* description *}
        	<tr>
                <td class="label">{$form_data.description.label}</td>
            </tr>
        	<tr>
                <td class="data" colspan="4" style="height: 100px; vertical-align: top;">{$form_data.description.html}</td>
            </tr>
        </tbody>
    </table>
    <table name="CRMCalendar" class="form" cellspacing="0" cellpadding="0" border="0">
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
