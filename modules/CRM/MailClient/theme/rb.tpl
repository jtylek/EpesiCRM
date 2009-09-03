{$form_open}

<table>
<tr>
<td>{$form_data.subject.label}</td>
<td>{$form_data.subject.html}</td>
</tr>
<tr>
<td>{$form_data.from.label}</td>
<td>{$form_data.from.html}</td>
</tr>
<tr>
<td>{$form_data.to.label}</td>
<td>{$form_data.to.html}</td>
</tr>
<tr>
<td colspan=2>{$form_data.header.record_header}</td>
</tr>
<tr>
<td colspan=2>
{if isset($record_add_button)}
{$record_add_button}
{/if}
{$form_data.records.error}
{$form_data.records.html}
</td>
</tr>
<tr>
<td colspan=2>{$form_data.header.notification_header}</td>
</tr>
<tr>
<td colspan=2>
{$addressbook_add_button}
{$form_data.to_addr_ex.html}
</td>
</tr>
</table>

{$form_close}
