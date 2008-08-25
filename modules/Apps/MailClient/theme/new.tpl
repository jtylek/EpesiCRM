{$form_open}

<table>
<tr>
<td>{$form_data.from_addr.label}</td>
<td><span class="error">{$form_data.from_addr.error}</span>{$form_data.from_addr.html}</td>
</tr>
<tr>
<td>{$form_data.to_addr.label}</td>
<td><span class="error">{$form_data.to_addr.error}</span>{$form_data.to_addr.html}{$addressbook}</td>
</tr>
{if isset($addressbook)}
<tr>
<td colspan=2>
<div id="{$addressbook_area_id}">
{$addressbook_add_button}
{$form_data.to_addr_ex.html}
</div>
</td>
</tr>
{/if}
<tr>
<td>{$form_data.subject.label}</td>
<td><span class="error">{$form_data.subject.error}</span>{$form_data.subject.html}</td>
</tr>
<tr>
<td>{$form_data.body.label}</td>
<td><span class="error">{$form_data.body.error}</span>{$form_data.body.html}</td>
</tr>
</table>
