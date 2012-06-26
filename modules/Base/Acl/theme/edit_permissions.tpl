{$form_open}

<div style="width:98%; text-align:left;" class="epesi_grey_board Base_Acl__edit_permissions">
	<div class="epesi_caption">
		{$labels.caption}
	</div>
	
	<table>
		<tr>
			<td class="epesi_label" style="width:20%;">
				{$form_data.permission.label}
			</td>
			<td class="epesi_data" style="width:80%;">
				&nbsp;&nbsp;{$form_data.permission.html}
			</td>
		</tr>
		<tr>
			<td class="epesi_label">
				{$labels.clearance}
			</td>
			<td class="epesi_data static_field">
				{section name=it loop=$counts} 
					{assign var=i value=$smarty.section.it.iteration-1} 
					{assign var=j value="clearance_$i"} 
					<span id="div_{$j}">
						{if $i!=0}
							{$labels.and}
						{/if}
						{$form_data.$j.html}
					</span>
				{/section}
				<div id="add_clearance" style="display: inline-block;" class="button" onclick="base_acl__add_clearance();">{$labels.add_clearance}</div>
			</td>
		</tr>
	</table>

</div>

{$form_close}
