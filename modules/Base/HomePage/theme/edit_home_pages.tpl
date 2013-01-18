{$form_open}

<div style="max-width:800px; text-align:left;" class="epesi_grey_board Base_HomePage__edit_home_pages">
	<div class="epesi_caption">
		{$labels.caption}
	</div>
	
	<table>
		<tr>
			<td class="epesi_label" style="width:20%;">
				{$form_data.home_page.label}
			</td>
			<td class="epesi_data" style="width:80%;">
				<div style="position: relative;">
					{$form_data.home_page.error}
					&nbsp;&nbsp;{$form_data.home_page.html}
				</div>
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
				<div id="add_clearance" style="display: inline-block;" class="button" onclick="base_home_page__add_clearance();">{$labels.add_clearance}</div>
			</td>
		</tr>
	</table>

</div>

{$form_close}
