{$form_open}

<div style="width:98%; text-align:left;" class="epesi_grey_board RecordBrowser__edit_permissions">
	<div class="epesi_caption">
		{$labels.caption}
	</div>
	
	<table>
		<tr>
			<td class="epesi_label" style="width:20%;">
				{$form_data.action.label}
			</td>
			<td class="epesi_data" style="width:80%;">
				{$form_data.action.html}
			</td>
		</tr>
		<tr>
			<td class="epesi_label">
				{$labels.clearance}
			</td>
			<td class="epesi_data static_field">
				{section name=it loop=$counts.clearance} 
					{assign var=i value=$smarty.section.it.iteration-1} 
					{assign var=j value="clearance_$i"} 
					<span id="div_{$j}">
						{if $i!=0}
							{$labels.and}
						{/if}
						{$form_data.$j.html}
					</span>
				{/section}
				<div id="add_clearance" style="display: inline-block;" class="button" onclick="utils_recordbrowser__add_clearance();">{$labels.add_clearance}</div>
			</td>
		</tr>
		<tr>
			<td class="epesi_label">
				{$labels.crits}
			</td>
			<td class="epesi_data static_field RecordBrowser__edit_permissions_crits">
				{section name=it loop=$counts.ands}
					{assign var=i value=$smarty.section.it.iteration-1} 
					<div style="white-space:nowrap;" id="div_crits_row_{$i}">
						{if $i!=0}
							{$labels.and}
						{else}
							<span style="display:inline-block;width:30px;"></span>
						{/if}
						{section name=jt loop=$counts.ors} 
							{assign var=j value=$smarty.section.jt.iteration-1} 
							<span id="div_crits_or_{$i}_{$j}">
								
								{if $j!=0}
									{$labels.or}
								{/if}
								
								{assign var=u value="_"} 
								{assign var=field value="_field"} 
								{assign var=field value="crits_$i$u$j$field"} 
								{assign var=op value="_op"} 
								{assign var=op value="crits_$i$u$j$op"} 
								{assign var=value value="_value"} 
								{assign var=value value="crits_$i$u$j$value"} 
								{assign var=sub_value value="_sub_value"} 
								{assign var=sub_value value="crits_$i$u$j$sub_value"} 

								{$form_data.$field.html}{$form_data.$op.html}{$form_data.$value.html}{$form_data.$sub_value.html}
							</span>
						{/section}
						<div id="add_or_{$i}" class="button" style="display:inline-block;" onclick="utils_recordbrowser__add_or({$i});">{$labels.add_or}</div>
					</div>
				{/section}
				<div id="add_and" class="button" onclick="utils_recordbrowser__add_and();">{$labels.add_and}</div>
			</td>
		</tr>
		<tr>
			<td class="epesi_label">
				{$labels.fields}
			</td>
			<td colspan="2" class="epesi_data static_field">
				{foreach from=$fields key=f item=v}
					{assign var=f value="field_$f"} 
					<div class="field">{$form_data.$f.html}{$form_data.$f.label}</div>
				{/foreach}
			</td>
		</tr>
	</table>

</div>

{$form_close}
