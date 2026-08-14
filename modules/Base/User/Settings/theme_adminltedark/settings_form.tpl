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
			{* Was a <table class="table"> - CSS Grid instead, column count set
			   dynamically from $section.headers (see AI-shared/adminlte-theme.md). *}
			<div class="table-responsive">
				<div class="epesi-settings-matrix" role="table" style="display: grid; grid-template-columns: minmax(150px, 2fr) repeat({$section.headers|@count}, minmax(60px, 1fr));">
					<div role="row" style="display: contents;">
						<div class="border-bottom fw-semibold" role="columnheader"></div>
						{foreach from=$section.headers item=h}
							<div class="border-bottom fw-semibold text-center" role="columnheader">{$h}</div>
						{/foreach}
					</div>
					{foreach from=$section.matrix_rows item=row}
						<div role="row" style="display: contents;">
							<div class="border-bottom" role="cell">{$row.label}</div>
							{foreach from=$row.cells item=cell}
								<div class="border-bottom text-center" role="cell">{if $cell}{$cell}{else}<span class="text-muted">&mdash;</span>{/if}</div>
							{/foreach}
						</div>
					{/foreach}
				</div>
			</div>
		{else}
			{if $section.header}
				<h6 class="text-secondary mt-3 mb-2">{$section.header}</h6>
			{/if}

			{if $section.matrix_rows}
				<div class="table-responsive">
					<div class="epesi-settings-matrix" role="table" style="display: grid; grid-template-columns: minmax(150px, 2fr) repeat({$section.matrix_captions|@count}, minmax(60px, 1fr));">
						<div role="row" style="display: contents;">
							<div class="border-bottom fw-semibold" role="columnheader"></div>
							{foreach from=$section.matrix_captions item=cap}
								<div class="border-bottom fw-semibold text-center" role="columnheader">{$cap}</div>
							{/foreach}
						</div>
						{foreach from=$section.matrix_rows item=row}
							<div role="row" style="display: contents;">
								<div class="border-bottom" role="cell">{$row.label}</div>
								{foreach from=$row.cells item=cell}
									<div class="border-bottom text-center" role="cell">{$cell}</div>
								{/foreach}
							</div>
						{/foreach}
					</div>
				</div>
			{/if}

			{if $section.rows}
				<div class="epesi-settings-fields">
					{foreach from=$section.rows item=f}
						<div class="epesi-settings-row row align-items-center">
							<label class="col-sm-4 col-form-label fw-semibold">
								{if $f.hint}<span class="epesi-settings-hint-badge"><i class="bi {$f.hint.icon}"></i> {$f.hint.label}</span>{/if}
								{$f.label}{if $f.required}<span class="text-danger">*</span>{/if}
							</label>
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
