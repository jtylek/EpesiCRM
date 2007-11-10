{if $view_style == 'new_event'}
	{$form_open}
    <div>
    <table name=CRMCalendar class=form cellspacing=0>
    	<tr>
    		<td style='border-bottom: 1px double black; border-right: 1px double black; width: 50%' style='padding: 10;'>
	    		<table cellspacing=0>
					{if $form_data.title.error}
						<tr><td colspan=4><span class=error>{$form_data.title.error}</span></td></tr>
					{/if}
					<tr>
						<td class=group_bottom align=left>{$form_data.title.label}</td>
						<td class=group_bottom align=left colspan=3>
						{$form_data.title.html}
						</td>
					</tr>
					{if $repeatable == 0}
						{*** date and time for non-rep event ***} 
						{math assign="span" equation="x" x=2}
						{if $form_data.date_s.error}
							<tr><td colspan=3><span class=error>{$form_data.date_s.error}</span></td></tr>
						{/if}
						{if $form_data.date_e.error}
							{math assign="span" equation="x+1" x=$span}
						{/if}
						<tr>
					  		<td class=nowrap align=left>{$form_data.date_s.label}</td>
					 		<td class=nowrap align=left>{$form_data.date_s.html}</td>
						  		{if $timeless == 0}
							  		<td class=nowrap align=left>{$form_data.time_s.html}</td>
							  		<td rowspan={$span} class=group_bottom>{$form_data.timeless.html}{$form_data.timeless.label}</td>
								{else}
							  		<td colspan=2 rowspan={$span} class=group_bottom>{$form_data.timeless.html}{$form_data.timeless.label}</td>
							  	{/if}
						</tr>
						{if $form_data.date_e.error}
							<tr><td colspan=3><span class=error>{$form_data.date_e.error}</span></td></tr>
						{/if}
						<tr>
					  		<td class=nowrap style='border-bottom: 1px double black;' align=left>{$form_data.date_e.label}</td>
					  		<td class=nowrap style='border-bottom: 1px double black;' align=left>{$form_data.date_e.html}</td>
						 	{if $timeless == 0}
						 		<td class=nowrap style='border-bottom: 1px double black;'>{$form_data.time_e.html}</td>
						 	{/if}
				 		</tr>
				 	{else}
				 		<tr>
				 			<td align=left>{$form_data.date_s.label}</td>
					 		<td align=left>{$form_data.date_s.html}</td>
				 		</tr>
				 		<tr>
					 		<td align=left>{$form_data.date_e.label}</td>
					 		{if $repeat_forever == 0}
						 		<td align=left>{$form_data.date_e.html}</td>
								<td align=left>{$form_data.repeat_forever.html}{$form_data.repeat_forever.label}</td>
							{else}
								<td colspan=2 align=left>{$form_data.repeat_forever.html}{$form_data.repeat_forever.label}</td>
						 	{/if}
				 		</tr>
					 	<tr>
						  	<td align=left>{$form_data.time_s.label}</td>
						  	{if $timeless == 0}
						  		<td align=left>{$form_data.time_s.html}</td>
					 			<td align=left rowspan=2>{$form_data.timeless.html}{$form_data.timeless.label}</td>
					 		{else}	
					 			<td align=left colspan=2 rowspan=2>{$form_data.timeless.html}{$form_data.timeless.label}</td>
					 		{/if}
					 	<tr>
							<td align=left>{$form_data.time_e.label}</td>
					 		{if $timeless == 0}
						 		<td align=left>{$form_data.time_e.html}</td>
							{/if}
					  	</tr>
					  	<tr>
							<td align=left>{$form_data.repeat_header.label}</td>
						</tr>
						<tr>
							<td class=group_bottom colspan=3 align=right>
						  	<table width=90%>
							  	<tr>
									<td width=33% align=left>{$form_data.month_r.label}</td>
									<td width=33% align=left>{$form_data.day_m_r.label}</td>
									<td width=33% align=left>{$form_data.day_w_r.label}</td>
							  	</tr>
							  	<tr>
									<td align=left>{$form_data.month_r.html}</td>
									<td align=left>{$form_data.day_m_r.html}</td>
									<td align=left colspan=3>{$form_data.day_w_r.html}</td>
							  	</tr>
							</table>
							</td>
						</tr>
				 	{/if}
					{**********}
					<tr>
					  <td class=group_bottom align=left>{$form_data.act_id.label}</td>
					  <td class=group_bottom align=left colspan=3>{$form_data.act_id.html}</td>
					</tr>
					{*********}
					<tr>
					  <td align=left>{$form_data.access.0.label}</td>
					  <td align=left colspan=3>{$form_data.access.0.html}{$form_data.access.1.html}{$form_data.access.2.html}</td>
					</tr>
					<tr>
					  	<td align=left width=15%>{$form_data.priority.0.label}</td>
						<td align=left colspan=3>{$form_data.priority.0.html}{$form_data.priority.1.html}{$form_data.priority.2.html}</td>
					</tr>
				</table>
			</td>
			<td style='border-bottom: 1px double black; vertical-align: top;'>
				<table cellspacing=0 width=100%>
					<tr>
					  <td align=left>{$form_data.rel_com_id.label}</td>
					  <td align=left colspan=3>
					 	{$form_data.rel_com_id.html}
					  </td>
					</tr>
					{if $form_data.emp_id.error}
						<tr><td colspan=2><span class=error>{$form_data.emp_id.error}</span></td></tr>
					{/if}
					<tr>
						<td align=left colspan=2> {$form_data.emp_id.label}</td>
					</tr>
					
					{if $edit_mode == 1}
						{$form_data.id.html}{$form_data.id.label}
						<tr>
							<input type=hidden name=id value={$event_id}>
							<input type=hidden name=gid value={$event_gid}>
							{$form_data.gid.html}
							
							<td class=group_bottom align=left colspan=2>{$form_data.emp_id.html}</td>
						</tr>
						<tr>
							<td align=left>{$form_data.created.label}</td>
							<td align=left>{$form_data.created.html}</td>
						</tr>
						<tr>
							<td align=left>{$form_data.edited.label}</td>
							<td align=left>{$form_data.edited.html}</td>
						</tr>
					{else}
						<tr>
							<td align=left colspan=2>{$form_data.emp_id.html}</td>
						</tr>
					{/if}
				</table>
			</td>
		</tr>
	  {* description *}
		<tr>
		  <td colspan=2>
		    {$form_data.description.label}
		  </td>
		</tr>
		<tr>
		  <td colspan=2>
		    {$form_data.description.html}
		  </td>
		</tr>
		{* buttons *}
		{*<tr>
		  <td align="center" colspan=2>
		    {$form_data.cancel_button.html}&nbsp;{$form_data.submit_button.html}
		  </td>
		</tr>*}
	</table>
    </div>
  </form>
{elseif $view_style == details_event}
	<table>
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
			<td colspan=2 align="center">{$event.description}</td>
		</tr>
	</table>	
{/if}