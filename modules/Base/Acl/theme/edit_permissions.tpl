{$form_open}

<div style="width:98%; text-align:left;" class="epesi_grey_board Base_Acl__edit_permissions">
	<div class="epesi_caption">
		{$labels.caption}
	</div>
	
	<div class="epesi-cp-rows">
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$form_data.permission.label}
			</div>
			<div class="epesi_data">
				<div style="position: relative;">
					{$form_data.permission.error}
					&nbsp;&nbsp;{$form_data.permission.html}
				</div>
			</div>
		</div>
		<div class="epesi-cp-row">
			<div class="epesi_label">
				{$labels.clearance}
			</div>
			<div class="epesi_data static_field">
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
			</div>
		</div>
	</div>

</div>

{$form_close}
