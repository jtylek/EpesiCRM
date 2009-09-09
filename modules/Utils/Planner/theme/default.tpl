<table cellpadding="0" cellspacing="0" border="0">
	<tr>
		<td style="vertical-align:top;">
			<table id="Utils_Planner__grid">
				<tr>
					<td/>
					{foreach item=h from=$headers}
						<td class="header">
							{$h}
						</td>
					{/foreach}
				</tr>
				{foreach item=gl key=gk from=$grid_legend}
					<tr>
						<td class="grid_legend">
							{$gl}
						</td>
						{foreach item=h key=k from=$headers}
							<td {$grid_attrs.$gk.$k}>
							</td>
						{/foreach}
						<br>
					</tr>
				{/foreach}
			</table>
		</td>
		<td style="vertical-align:top;margin:5px;">
			{$form_open}
				<table id="Utils_Planner__resource_table" cellpadding="0" cellspacing="0" border="0">
					{foreach item=e key=k from=$form_data}
						{if is_array($e) && isset($e.label)}
							{if ($e.type=='automulti')}
								<tr>
									<td colspan="2" class="label" nowrap="1">{$e.label}</td>
								</tr>
								<tr>
									<td colspan="2" class="data">{$e.html}</td>
								</tr>
							{else}
								<tr>
									<td class="label" nowrap="1">{$e.label}</td>
									<td class="data">{$e.html}</td>
								</tr>
							{/if}
						{/if}
					{/foreach}
					<tr>
						<td colspan="2" class="label">{$time_frames.label}</td>
					</tr>
					<tr>
						<td colspan="2" class="data">{$time_frames.html}</td>
					</tr>
				</table>
			{$form_close}
		</td>
	</tr>
</table>