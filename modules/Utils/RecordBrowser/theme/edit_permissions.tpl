{$form_open}

<div style="width:98%; text-align:left;" class="epesi_grey_board RecordBrowser__edit_permissions">
	<div class="epesi_caption">
		{$labels.caption}
	</div>
	
	<div class="epesi-cp-rows">
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$form_data.action.label}
			</div>
			<div class="epesi_data">
				{$form_data.action.html}
			</div>
		</div>
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$labels.clearance}
			</div>
			<div class="epesi_data static_field">
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
			</div>
		</div>
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$labels.crits}
			</div>
			<div class="epesi_data static_field" style="padding: 1em;">
				{$form_data.qb_crits.error}
				{$form_data.qb_crits.html}
			</div>
		</div>
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$labels.fields}
			</div>
			<div class="epesi_data field_permissions">
				{$form_data.blocked_fields.html}
			</div>
		</div>
	</div>

</div>

{$form_close}
