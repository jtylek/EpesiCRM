{* Base_User_Settings::display_adminlte() - one settings branch (e.g. "Quick
   Access", "Messenger Alerts", "Browsing records"). $sections is an ordered
   list of header-delimited groups (or, where two+ adjacent sections shared
   an identical option list - e.g. RecordBrowser's per-table "Automatically
   add to favorites"/"Automatically watch" selects - one merged
   select_matrix section listing each module once with a value column per
   header). Within an ordinary section: $matrix_rows/$matrix_captions hold
   a group that turned out to be a uniform row of checkboxes (re-split from
   PEAR QuickForm's own concatenated group html - see the PHP for why),
   rendered as a table with one shared header row; $rows holds everything
   else, rendered as plain label/value rows. *}
<div class="epesi-user-settings" id="Base_User_Settings">
	<h6 class="epesi-user-settings-title">{$branch}</h6>

	{$form_open}

	{foreach from=$sections item=section}
		{if $section.select_matrix}
			<div class="table-responsive">
				<table class="table epesi-settings-matrix">
					<thead>
						<tr>
							<th></th>
							{foreach from=$section.headers item=h}
								<th>{$h}</th>
							{/foreach}
						</tr>
					</thead>
					<tbody>
						{foreach from=$section.matrix_rows item=row}
							<tr>
								<td>{$row.label}</td>
								{foreach from=$row.cells item=cell}
									<td>{if $cell}{$cell}{else}<span class="text-muted">&mdash;</span>{/if}</td>
								{/foreach}
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		{else}
			{if $section.header}
				<h6 class="text-secondary mt-3 mb-2">{$section.header}</h6>
			{/if}

			{if $section.matrix_rows}
				<div class="table-responsive">
					<table class="table epesi-settings-matrix">
						<thead>
							<tr>
								<th></th>
								{foreach from=$section.matrix_captions item=cap}
									<th>{$cap}</th>
								{/foreach}
							</tr>
						</thead>
						<tbody>
							{foreach from=$section.matrix_rows item=row}
								<tr>
									<td>{$row.label}</td>
									{foreach from=$row.cells item=cell}
										<td>{$cell}</td>
									{/foreach}
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{/if}

			{if $section.rows}
				<div class="epesi-settings-fields">
					{foreach from=$section.rows item=f}
						<div class="epesi-settings-row row align-items-center">
							<label class="col-sm-4 col-form-label fw-semibold">{$f.label}{if $f.required}<span class="text-danger">*</span>{/if}</label>
							<div class="col-sm-8">
								{$f.html}
							</div>
						</div>
					{/foreach}
				</div>
			{/if}
		{/if}
	{/foreach}

	{$form_close}
</div>
