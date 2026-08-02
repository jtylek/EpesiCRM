<h5>{'PHP environment check'|t}</h5>
{foreach from=$checks item=category}
<div class="mb-3">
	<h6>{$category.label}</h6>
	<table class="table table-sm mb-0">
		<tbody>
{foreach from=$category.tests item=test}
			<tr>
				<td>{$test.label}</td>
				<td class="text-end">
					<span class="badge {if $test.severity==0}bg-success{elseif $test.severity==1}bg-warning text-dark{else}bg-danger{/if}">{$test.status}</span>
				</td>
			</tr>
{/foreach}
		</tbody>
	</table>
</div>
{/foreach}

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
