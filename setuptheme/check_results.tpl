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
{if $orphaned}
<div class="mb-3">
	<h6>{'Additional modules'|t}</h6>
	<div class="list-group list-group-flush">
{foreach from=$orphaned item=mod}
		<div class="list-group-item d-flex justify-content-between align-items-center">{$mod}<span class="badge bg-warning text-dark">{'Code missing'|t}</span></div>
{/foreach}
	</div>
	<p class="text-muted small mb-0 mt-1">{'These modules are installed but their code is not in this build (most likely premium/custom). Their data stays in the database; migrate them to this version to restore them.'|t}</p>
</div>
{/if}
<div class="small text-muted mt-3">
	<div class="mb-1">{'Legend:'|t}</div>
	<div><span class="badge bg-success">{'OK'|t}</span> {'matches EPESI requirements'|t}</div>
	<div><span class="badge bg-warning text-dark">{'Warning'|t}</span> {"shouldn't prevent EPESI from running, but it's recommended to change the settings"|t}</div>
	<div><span class="badge bg-danger">{'Failed'|t}</span> {"check failed, it's necessary to change the settings"|t}</div>
</div>
{if $continue_url}
<div class="text-center mt-3"><a class="btn btn-primary" href="{$continue_url}">{$continue_label}</a></div>
{/if}
