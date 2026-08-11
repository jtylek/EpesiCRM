{$form_open}
{if (isset($form_data.prev_week))}
	<div id="Utils_Planner__navigation" style="display: flex;">
			<div class="nav_button child_button">
				{$form_data.prev_week.html}
			</div>
			<div class="nav_button child_button">
				{$form_data.prev_day.html}
			</div>
			<div class="nav_button child_button">
				{$form_data.today.html}
			</div>
			<div class="nav_button child_button">
				{$form_data.next_day.html}
			</div>
			<div class="nav_button child_button">
				{$form_data.next_week.html}
			</div>
			<div>
				{$popup_calendar}
			</div>
	</div>
	{php}
	unset($this->_tpl_vars['form_data']['prev_week']);
	unset($this->_tpl_vars['form_data']['prev_day']);
	unset($this->_tpl_vars['form_data']['today']);
	unset($this->_tpl_vars['form_data']['next_day']);
	unset($this->_tpl_vars['form_data']['next_week']);
	{/php}
{/if}


		<div class="epesi_grey_board">
			{* Was a 2-column outer <table> (grid | resource form) wrapping two
			   inner <table>s - flex row instead, with the resource-panel
			   fields keeping their own label/data pairing (see below). *}
			<div id="Utils_Planner__table" style="display: flex;">
					<div style="vertical-align:top;width:700px;">
						{* Was a <table> (a genuine resource x time-slot grid) -
						   CSS Grid instead, column count set dynamically from
						   $headers (see AI-shared/adminlte-theme.md). *}
						<div id="Utils_Planner__grid" role="table" style="display: grid; grid-template-columns: auto repeat({$headers|@count}, 1fr);">
							<div role="row" style="display: contents;">
								<div role="presentation"></div>
								{foreach item=h key=k from=$headers}
									<div class="header child_button" role="columnheader">
										<div>
											{$h}<br />
											<input type="button" value="{$select_all_label}" onclick="{$select_all.$k}" />
										</div>
									</div>
								{/foreach}
							</div>
							{foreach item=gl key=gk from=$grid_legend}
								<div role="row" style="display: contents;">
									<div class="grid_legend epesi_label" role="rowheader" style="white-space: nowrap;">
										{$gl}
									</div>
									{foreach item=h key=k from=$headers}
										<div {$grid_attrs.$gk.$k} role="cell">
										</div>
									{/foreach}
								</div>
							{/foreach}
						</div>
					</div>
					<div style="vertical-align:top;margin:5px;width:250px;min-width:250px;">
						{* Was a <table> - now a flex column; automulti fields kept
						   their label-above-data stacking (full-width rows in the
						   original, via colspan=2), regular fields keep label
						   beside data (their own nested flex row). *}
						<div id="Utils_Planner__resource_table" style="display: flex; flex-direction: column; gap: 3px;">
							{foreach item=e key=k from=$form_data}
								{if is_array($e) && isset($e.label)}
									{if ($e.type=='automulti')}
										<div class="epesi_label top" style="white-space: nowrap; width: 100%;">
										    {$e.label}
										</div>
										<div class="epesi_data" style="width: 100%;">
										    <div style="position: relative;">
											    {$e.error}
											    {$e.html}
										    </div>
										</div>
									{else}
										<div style="display: flex;">
										<div class="epesi_label" style="white-space: nowrap;">
										    {$e.label}
										</div>
										<div class="epesi_data">
										    <div style="position: relative;">
											{$e.error}
											{$e.html}
										    </div>
										</div>
										</div>
									{/if}
								{/if}
							{/foreach}
							<div class="epesi_label top" style="width: 100%;">{$time_frames.label}</div>
							<div class="data" style="width: 100%;">{$time_frames.html}</div>
						</div>
					</div>
			</div>
		</div>

{$form_close}
