</center>
<div class="epesi_caption">
	{$header}
</div>
<center>
<div id="shoutbox_big_container">
{$form_open}
<table border="0" width="100%">
    <tr>
        <td rowspan="2" class="epesi_label" style="width:80px;">{$form_data.post.label}</td>
        <td rowspan="2" class="epesi_data" style="width:50%">{$form_data.post.html}</td>
		<td rowspan="2" style="width:10px;"></td>
        <td class="epesi_label" style="width:25px;">{$form_data.to.label}</td>
        <td class="epesi_data" style="width:5%;">{$form_data.to.html}</td>
    </tr>
    <tr>
        <td colspan="2" class="child_button" style="text-align: center;">{$form_data.submit_button.html}</td>
    </tr>
</table>
{$form_close}
{$board}
</div>
