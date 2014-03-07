{$form_open}
<table style="width:100%">
	<tr>
		<td width="105px">
			{$form.save.html}
		</td>
		<td width="105px">
			{$form.cancel.html}
		</td>
		<td>
            {$form.note_title.label}
            {$form.note_title.html}
        </td>
        <td colspan="2" align="right">
            {if isset($form.crypted)}
                {$form.crypted.label}
                {$form.crypted.html}
                {$form.note_password.label}
                {$form.note_password.html}
                {$form.note_password.error}
                {$form.note_password2.label}
                {$form.note_password2.html}
            {else}
                <span style="margin-right: 15px; margin-left: 10px">{"Enable mcrypt extension to turn on notes encryption"|t}</span>
            {/if}
			{$form.sticky.label}
			{$form.sticky.html}
			{$form.permission.label}
			{$form.permission.html}
		</td>
	</tr>
	<tr>
		<td colspan="2" style="width:210px;">
			{$form.save.error}
		</td>
		<td>
			{$form.sticky.error}
		</td>
		<td>
			{$form.permission.error}
		</td>
		<td style="width:330px;">
			{$form.file.error}
		</td>
	</tr>
	<tr>
		<td colspan="5">
			{$form.note.error}
		</td>
	</tr>
	<tr>
		<td colspan="5">
			{$form.note.html}
		</td>
	</tr>
</table>
{$form_close}