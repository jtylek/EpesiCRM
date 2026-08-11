{$form_open}

<div class="epesi_grey_board" style="width:850px;">
{* Was a <table> with a <td rowspan="5"> recordsets multiselect box sitting
   beside a 4-column label/data form grid - CSS Grid instead, with
   grid-row:span 5 replacing the rowspan (see AI-shared/adminlte-theme.md).
   Note: rowspan="5" in the original assumes exactly 5 rows follow
   (end_date, new/edit, delete_restore/file, user, submit) - when
   $form_data.user isn't set, only 4 rows actually render, the same
   mismatch the original table markup already had; preserved as-is rather
   than "fixed" since it isn't malformed HTML, just a pre-existing quirk. *}
<div id="Apps_ActivityReport" role="table" style="display: grid; grid-template-columns: 400px 150px minmax(0, 1fr) 100px minmax(0, 1fr);">
	<div role="row" style="display: contents;">
		<div class="epesi_label top" role="cell">
			{$form_data.recordsets.label}
		</div>
		<div class="epesi_label" role="cell">
			{$form_data.start_date.label}
		</div>
		<div class="epesi_data" role="cell" style="grid-column: span 3;">
			{$form_data.start_date.html}
		</div>
	</div>
	<div role="row" style="display: contents;">
		<div class="epesi_data multiselect" role="cell" style="grid-row: span 5;">
			{$form_data.recordsets.html}
		</div>
		<div class="epesi_label" role="cell">
			{$form_data.end_date.label}
		</div>
		<div class="epesi_data" role="cell" style="grid-column: span 3;">
			{$form_data.end_date.html}
		</div>
	</div>
	<div role="row" style="display: contents;">
		<div class="epesi_label" role="cell">
			{$form_data.new.label}
		</div>
		<div class="epesi_data" role="cell">
			{$form_data.new.html}
		</div>
		<div class="epesi_label" role="cell">
			{$form_data.edit.label}
		</div>
		<div class="epesi_data" role="cell">
			{$form_data.edit.html}
		</div>
	</div>
	<div role="row" style="display: contents;">
		<div class="epesi_label" role="cell">
			{$form_data.delete_restore.label}
		</div>
		<div class="epesi_data" role="cell">
			{$form_data.delete_restore.html}
		</div>
		<div class="epesi_label" role="cell">
			{$form_data.file.label}
		</div>
		<div class="epesi_data" role="cell">
			{$form_data.file.html}
		</div>
	</div>
	{if isset($form_data.user)}
		<div role="row" style="display: contents;">
			<div class="epesi_label" role="cell">
				{$form_data.user.label}
			</div>
			<div class="epesi_data" role="cell" style="grid-column: span 3;">
				{$form_data.user.html}
			</div>
		</div>
	{/if}
	{if isset($form_data.submit)}
		<div role="row" style="display: contents;">
			<div class="child_button" role="cell" style="text-align:center; grid-column: span 4;">
				{$form_data.submit.html}
			</div>
		</div>
	{/if}
</div>
</div>
<br>
{$form_close}
