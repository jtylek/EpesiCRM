	{$form_open}

    <div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN-->
	<div class="layer" style="padding: 9px; width: 990px; height: 500px;">
		<div class="content_shadow">
<!-- -->

    <table name="CRMCalendar" class="form" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			<tr>
				<td class="group_bottom label bold" align="left">{$form_data.title.label}</td>
				<td class="group_bottom data bold" align="left" colspan="7"><span class="error">{$form_data.title.error}</span>{$form_data.title.html}</td>
			</tr>
			<tr>
			  	<td class="label" align="left">{$form_data.timeless.label}</td>
				<td class="data" align="left">{$form_data.timeless.html}</td>
			  	<td class="data" colspan="2">&nbsp;</td>
				<td class="label" align="left">{$form_data.access.label}</td>
				<td class="data" align="left">{$form_data.access.html}</td>
			  	<td class="label" align="left">{$form_data.priority.label}</td>
				<td class="data" align="left">{$form_data.priority.html}</td>
			</tr>
			<tr>
			  	<td class="label" colspan="2" width="25%"> {$form_data.date_s.label}</td>
			  	<td class="label" colspan="2" width="25%"> {$form_data.date_e.label}</td>
	        	    	<td class="label" colspan="2" width="25%"> {$form_data.emp_id.label}</td>
        	        	<td class="label" colspan="2" width="25%"> {$form_data.cus_id.label}</td>
			</tr>
			<tr>
			 	<td class="data no-border no-wrap" colspan="2"><span class="error">{$form_data.date_s.error}</span>{$form_data.date_s.html}<span id="time_s">{$form_data.time_s.html}</span></td>
			 	<td class="data no-border no-wrap" colspan="2"><span class="error">{$form_data.date_e.error}</span>{$form_data.date_e.html}<span id="time_e">{$form_data.time_e.html}</span></td>
				<td class="data no-border arrows" colspan="2"><span class="error">{$form_data.emp_id.error}</span>{$form_data.emp_id.html}</td>
				<td class="data no-border arrows" colspan="2"><span class="error">{$form_data.cus_id.error}</span>{$form_data.cus_id.html}<br>{$cus_click}</td>
			</tr>
        {* description *}
		<tr><td class="bottom label no-border" colspan="8">{$form_data.description.label}</td></tr>
		<tr><td class="bottom data no-border" colspan="8">{$form_data.description.html}</td></tr>

    </tbody>
	</table>

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


