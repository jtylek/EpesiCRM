
{if $view_style == 'new_event'}
	{$form_open}

    <div id="CRM_Calendar_Event_Personal">

<!-- SHADIW BEGIN
	<div class="layer" style="padding: 9px; width: 700px; height: 500px;">
		<div class="content_shadow">
 -->

    <table name="CRMCalendar" class="form" cellspacing="0" cellpadding="0" border="0">
    	<tbody>
			{if $form_data.title.error}
			<tr>
                      <td colspan="4"><span class="error">{$form_data.title.error}</span></td>
                  </tr>
			{/if}
			<tr>
				<td class="group_bottom label bold" align="left">{$form_data.title.label}</td>
				<td class="group_bottom data bold" align="left" colspan="3">{$form_data.title.html}</td>
			</tr>
			{if $repeatable == 0}
				{*** date and time for non-rep event ***}
				{math assign="span" equation="x" x=2}
				{if $form_data.date_s.error}
			<tr>
                      <td colspan="3"><span class="error">{$form_data.date_s.error}</span></td>
                  </tr>
				{/if}
				{if $form_data.date_e.error}
					{math assign="span" equation="x+1" x=$span}
				{/if}
                      <tr>
			  		<td class="nowrap label" align="left">{$form_data.date_s.label}</td>
			 		<td class="nowrap data" align="left">{$form_data.date_s.html}</td>
				  		{if $timeless == 0}
					  		<td class="nowrap data" align="left">{$form_data.time_s.html}</td>
					  		<td rowspan={$span} class="group_bottom data padd">{$form_data.timeless.html}{$form_data.timeless.label}</td>
						{else}
					  		<td colspan="2" rowspan={$span} class="group_bottom data padd">{$form_data.timeless.html}{$form_data.timeless.label}</td>
					  	{/if}
                      </tr>
				{if $form_data.date_e.error}
					<tr><td colspan="3"><span class="error">{$form_data.date_e.error}</span></td></tr>
				{/if}
				<tr>
			  		<td class="nowrap label">{$form_data.date_e.label}</td>
			  		<td class="nowrap data">{$form_data.date_e.html}</td>
				 	{if $timeless == 0}
				 		<td class="nowrap data">{$form_data.time_e.html}</td>
				 	{/if}
		 		</tr>
		 	{else}
		 		<tr>
		 			<td style="label" align="left">xxx11{$form_data.date_s.label}</td>
			 		<td style="data" align="left">xxx12{$form_data.date_s.html}</td>
		 		</tr>
		 		<tr>
			 		<td align="left">{$form_data.date_e.label}</td>
			 		{if $repeat_forever == 0}
				 		<td class="label" align="left">xxx13{$form_data.date_e.html}</td>
						<td class="data" align="left">xxx14{$form_data.repeat_forever.html}{$form_data.repeat_forever.label}</td>
					{else}
						<td colspan="2" align="left">xxx15{$form_data.repeat_forever.html}{$form_data.repeat_forever.label}</td>
				 	{/if}
		 		</tr>
			 	<tr>
				  	<td align="left">xxx16{$form_data.time_s.label}</td>
				  	{if $timeless == 0}
				  		<td align="left">xxx17{$form_data.time_s.html}</td>
			 			<td align="left" rowspan="2">xxx18{$form_data.timeless.html}{$form_data.timeless.label}</td>
			 		{else}
			 			<td align=left colspan=2 rowspan=2>{$form_data.timeless.html}{$form_data.timeless.label}</td>
			 		{/if}
			 	<tr>
					<td align=left>{$form_data.time_e.label}</td>
			 		{if $timeless == 0}
				 		<td align="left">{$form_data.time_e.html}</td>
					{/if}
			  	</tr>
			  	<tr>
					<td align="left">{$form_data.repeat_header.label}</td>
				</tr>
				<tr>
					<td class="group_bottom" colspan="3" align="right">
				  	<table>
                              <tbody>
					  	<tr>
							<td align=left>{$form_data.month_r.label}</td>
							<td align=left>{$form_data.day_m_r.label}</td>
							<td align=left>{$form_data.day_w_r.label}</td>
					  	</tr>
					  	<tr>
							<td align=left>{$form_data.month_r.html}</td>
							<td align=left>{$form_data.day_m_r.html}</td>
							<td align=left colspan=3>{$form_data.day_w_r.html}</td>
					  	</tr>
                              </tbody>
					</table>
					</td>
				</tr>
		 	{/if}
			{**********}
			<tr>
			  <td class="group_bottom label" align="left">{$form_data.act_id.label}</td>
			  <td class="group_bottom data" align="left" colspan="3">{$form_data.act_id.html}</td>
			</tr>
			{*********}
			<tr>
				  <td class="label" align="left">{$form_data.access.label}</td>
				  <td class="data" align="left">{$form_data.access.html}</td>
			  	<td class="label" align="left">{$form_data.priority.label}</td>
				<td class="data" align="left">{$form_data.priority.html}</td>
			</tr>
		</tr><tr>
			{if $edit_mode == 1}
				{$form_data.id.html}{$form_data.id.label}
				<tr>
					<input type="hidden" name="id" value={$event_id}>
					<input type="hidden" name="gid" value={$event_gid}>
					{$form_data.gid.html}
					<td class="group_bottom data" colspan="2">{$form_data.emp_id.html}</td>
					<td class="group_bottom data" colspan="2">{$form_data.cus_id.html}</td>
				</tr>
				<tr>
					<td class="label">{$form_data.created.label}</td>
					<td class="data">{$form_data.created.html}</td>
				</tr>
				<tr>
					<td class="label">{$form_data.edited.label}</td>
					<td class="data">{$form_data.edited.html}</td>
				</tr>
			{else}
				{if $form_data.emp_id.error || $form_data.cus_id.error}
				<tr>
                       <td colspan="2"><span class="error">{$form_data.emp_id.error}</span></td>
                       <td colspan="2"><span class="error">{$form_data.cus_id.error}</span></td>
                   </tr>
				{/if}
				<tr>
                       <td class="label" colspan="2"> {$form_data.emp_id.label}</td>
                       <td class="label" colspan="2"> {$form_data.cus_id.label}</td>
				</tr>
				<tr>
					<td class="data no-border arrows" colspan="2">{$form_data.emp_id.html}</td>
					<td class="data no-border arrows" colspan="2">{$form_data.cus_id.html}</td>
				</tr>
				<tr>
					<td class="data no-border arrows" colspan="2">{$emp_click}</td>
					<td class="data no-border arrows" colspan="2">{$cus_click}</td>
				</tr>
			{/if}
        {* description *}
		<tr><td class="bottom label no-border" colspan="4">{$form_data.description.label}</td></tr>
		<tr><td class="bottom data no-border" colspan="4">{$form_data.description.html}</td></tr>

    </tbody>
	</table>

<!-- SHADOW END
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
 -->

</form>

</div>

{elseif $view_style == details_event}

<!-- SHADIW BEGIN
	<div class="layer" style="padding: 9px; width: 700px; height: 500px;">
		<div class="content_shadow">
 -->

	<table>
    <tbody>
		<tr>
			<td align="right">Title:</td>
			<td align="left">{$event.title}</td>
		</tr>
		<tr>
			<td align="right">Starts:</td>
			<td align="left">{$event.date_s}</td>
		</tr>
		<tr>
			<td align="right">Ends:</td>
			<td align="left">{$event.date_e}</td>
		</tr>
		<tr>
			<td align="right">Related Company:</td>
			<td align="left">{$event.rel_com}</td>
		</tr>
		<tr>
			<td align="right">Related Person:</td>
			<td align="left">{$event.rel_emp}</td>
		</tr>
		<tr>
			<td align="right">Task:</td>
			<td align="left">{$event.activity}</td>
		</tr>
		<tr>
			<td align="right">Assigned Employees:</td>
			<td align="left">{$event.emps}</td>
		</tr>
		<tr>
			<td colspan="2" align="center">{$event.description}</td>
		</tr>
    </tbody>
	</table>

<!-- SHADOW END
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
 -->

{/if}
