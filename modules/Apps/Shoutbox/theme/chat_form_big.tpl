</center>
<div class="epesi_caption">
	{$header}
</div>
<center>
<div id="shoutbox_big_container">
{$form_open}
{* Was a <table> with two <td rowspan="2"> cells (post label/data) sitting
   beside a to-field row and a submit row below - CSS Grid instead,
   grid-row:span 2 replacing the rowspans (see AI-shared/adminlte-theme.md). *}
<div role="table" style="display: grid; grid-template-columns: 80px 50% 10px 25px 20%; width: 100%;">
    <div class="epesi_label" role="cell" style="grid-row: span 2;">{$form_data.post.label}</div>
    <div class="epesi_data" role="cell" style="grid-row: span 2;">{$form_data.post.html}</div>
	<div role="presentation" style="grid-row: span 2;"></div>
    <div class="epesi_label" role="cell">{$form_data.shoutbox_to.label}</div>
    <div class="epesi_data" role="cell">{$form_data.shoutbox_to.html}</div>
    <div class="child_button" role="cell" style="grid-column: span 2; text-align: center;">{$form_data.submit_button.html}</div>
</div>
{$form_close}
{$board}
</div>
