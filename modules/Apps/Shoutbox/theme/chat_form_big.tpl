<div id="shoutbox_big_container">
{$form_open}
<table border="0" style="width:500px">
	<tr>
		<td class="epesi_label" style="width:70px;">
			{$form_data.to.label}
		</td>
		<td class="epesi_data">
			{$form_data.to.html}
		</td>
		<td class="child_button">
			{$form_data.submit_button.html}
		</td>
	</tr>
	<tr>
		<td class="epesi_label" style="width:70px;">
			{$form_data.post.label}
		</td>
		<td class="epesi_data" colspan="2">
			{$form_data.post.html}
		</td>
	</tr>
</table>
{$form_close}
{$board}
</div>