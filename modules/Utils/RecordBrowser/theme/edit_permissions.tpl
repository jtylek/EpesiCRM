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
			<td class="epesi_data static_field" style="padding: 1em;">
				{$form_data.qb_crits.error}
				{$form_data.qb_crits.html}
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
