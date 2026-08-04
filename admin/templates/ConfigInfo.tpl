<h5>{'PHP environment check'|t}</h5>
{foreach from=$checks item=category}
<div class="mb-3">
	<h6>{$category.label}</h6>
	<div class="list-group list-group-flush">
{foreach from=$category.tests item=test}
		<div class="list-group-item d-flex justify-content-between align-items-center">
			{$test.label}
			<span class="badge {if $test.severity==0}bg-success{elseif $test.severity==1}bg-warning text-dark{else}bg-danger{/if}">{$test.status}</span>
		</div>
{/foreach}
	</div>
</div>
{/foreach}

<h5 class="mt-4">EPESI config.php</h5>
<div class="list-group list-group-flush">
	{foreach from=$config_rows item=row}
	<div class="list-group-item d-flex justify-content-between align-items-center">
		{$row.label}
		{if $row.strong}<strong class="{$row.class}">{$row.value}</strong>{else}<span class="{$row.class}">{$row.value}</span>{/if}
	</div>
	{/foreach}
</div>
