{$form_open}
{if (isset($form_data.prev_week))}
	<table id="Utils_Planner__navigation">
		<tr>
			<td class="nav_button child_button">
				{$form_data.prev_week.html}
			</td>
			<td class="nav_button child_button">
				{$form_data.prev_day.html}
			</td>
			<td class="nav_button child_button">
				{$form_data.today.html}
			</td>
			<td class="nav_button child_button">
				{$form_data.next_day.html}
			</td>
			<td class="nav_button child_button">
				{$form_data.next_week.html}
			</td>
			<td>
				{$popup_calendar}
			</td>
		</tr>
	</table>
	{php}
	unset($this->_tpl_vars['form_data']['prev_week']);
	unset($this->_tpl_vars['form_data']['prev_day']);
	unset($this->_tpl_vars['form_data']['today']);
	unset($this->_tpl_vars['form_data']['next_day']);
	unset($this->_tpl_vars['form_data']['next_week']);
	{/php}
{/if}


		<div class="epesi_grey_board">
			<table cellpadding="0" cellspacing="0" border="0" id="Utils_Planner__table">
				<tr>
					<td style="vertical-align:top;width:700px;">
						<table id="Utils_Planner__grid">
							<tr>
								<td/>
								{foreach item=h key=k from=$headers}
									<td class="header child_button">
										<div>
											{$h}<br />
											<input type="button" value="{$select_all_label}" onclick="{$select_all.$k}" />
										</div>
									</td>
								{/foreach}
							</tr>
							{foreach item=gl key=gk from=$grid_legend}
								<tr>
									<td class="grid_legend epesi_label" nowrap >
										{$gl}
									</td>
									{foreach item=h key=k from=$headers}
										<td {$grid_attrs.$gk.$k}>
										</td>
									{/foreach}
								</tr>
							{/foreach}
						</table>
					</td>
					<td style="vertical-align:top;margin:5px;width:250px;min-width:250px;">
						<table id="Utils_Planner__resource_table" class="border-spacing" cellpadding="0" cellspacing="0" border="0">
							{foreach item=e key=k from=$form_data}
								{if is_array($e) && isset($e.label)}
									{if ($e.type=='automulti')}
										<tr>
											<td colspan="2" class="epesi_label top" nowrap="1">
											    {$e.label}
											</td>
										</tr>
										<tr>
											<td colspan="2" class="epesi_data">
											    <div style="position: relative;">
												    {$e.error}
												    {$e.html}
											    </div>
											</td>
										</tr>
									{else}
										<tr>
											<td class="epesi_label" nowrap="1">
											    {$e.label}
											</td>
											<td class="epesi_data">
											    <div style="position: relative;">
												{$e.error}
												{$e.html}
											    </div>
											</td>
										</tr>
									{/if}
								{/if}
							{/foreach}
							<tr>
								<td colspan="2" class="epesi_label top">{$time_frames.label}</td>
							</tr>
							<tr>
								<td colspan="2" class="data">{$time_frames.html}</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</div>

{$form_close}
