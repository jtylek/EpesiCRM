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
{if $orphaned}
<div class="mb-3">
	<h6>{'Additional modules'|t}</h6>
	<table class="table table-sm mb-1">
		<tbody>
{foreach from=$orphaned item=mod}
			<tr><td>{$mod}</td><td class="text-end"><span class="badge bg-warning text-dark">{'Code missing'|t}</span></td></tr>
{/foreach}
		</tbody>
	</table>
	<p class="text-muted small mb-0">{'These modules are installed but their code is not in this build (most likely premium/custom). Their data stays in the database; migrate them to this version to restore them.'|t}</p>
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
