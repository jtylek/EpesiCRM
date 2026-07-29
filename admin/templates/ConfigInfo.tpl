<h5>{'PHP environment check'|t}</h5>
<table class="table table-striped table-sm">
	<tbody>
		{foreach from=$env_rows item=row}
		<tr>
			<td>{$row.label}</td>
			<td>{if $row.strong}<strong class="{$row.class}">{$row.value}</strong>{else}<span class="{$row.class}">{$row.value}</span>{/if}</td>
		</tr>
		{/foreach}
	</tbody>
</table>

<h5 class="mt-4">EPESI config.php</h5>
<table class="table table-striped table-sm">
	<tbody>
		{foreach from=$config_rows item=row}
		<tr>
			<td>{$row.label}</td>
			<td>{if $row.strong}<strong class="{$row.class}">{$row.value}</strong>{else}<span class="{$row.class}">{$row.value}</span>{/if}</td>
		</tr>
		{/foreach}
	</tbody>
</table>
