{* Base_User_Settings::display_adminlte() - one settings branch (e.g. "Quick
   Access", "Messenger Alerts"). $matrix_rows/$matrix_captions hold groups
   that turned out to be a uniform row of checkboxes (re-split from PEAR
   QuickForm's own concatenated group html - see the PHP for why), rendered
   as a real table with one shared header row. $rows holds everything else,
   rendered as plain label/value rows. *}
<div class="epesi-user-settings" id="Base_User_Settings">
	<h6 class="epesi-user-settings-title">{$branch}</h6>

	{$form_open}

	{foreach from=$extra_headers item=h}
		<h6 class="text-secondary mt-3 mb-2">{$h}</h6>
	{/foreach}

	{if $matrix_rows}
		<div class="table-responsive">
			<table class="table epesi-settings-matrix">
				<thead>
					<tr>
						<th></th>
						{foreach from=$matrix_captions item=cap}
							<th>{$cap}</th>
						{/foreach}
					</tr>
				</thead>
				<tbody>
					{foreach from=$matrix_rows item=row}
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

	{if $rows}
		<div class="epesi-settings-fields">
			{foreach from=$rows item=f}
				<div class="epesi-settings-row row align-items-center">
					<label class="col-sm-4 col-form-label fw-semibold">{$f.label}{if $f.required}<span class="text-danger">*</span>{/if}</label>
					<div class="col-sm-8">
						{$f.html}
					</div>
				</div>
			{/foreach}
		</div>
	{/if}

	{$form_close}
</div>
