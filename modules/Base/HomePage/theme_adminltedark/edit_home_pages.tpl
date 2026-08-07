{$form_open}
<div class="card mb-3">
<div class="card-body">

<div class="epesi_label header epesi-qf-header">
	{$labels.caption}
</div>

<div class="epesi-qf-grid">
	<div class="epesi_label epesi-qf-grid-label">
		{$form_data.home_page.label}
	</div>
	<div class="epesi_data epesi-qf-grid-data">
		<div class="epesi-qf-field-wrap">
			{$form_data.home_page.error}
			{$form_data.home_page.html}
		</div>
	</div>

	<div class="epesi_label epesi-qf-grid-label">
		{$labels.clearance}
	</div>
	<div class="epesi_data static_field epesi-qf-grid-data">
		<div class="epesi-qf-field-wrap">
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
		</div>
	</div>
</div>

</div>
</div>
{$form_close}
