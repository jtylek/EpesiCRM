<h4 class="mb-3">{'EPESI Shell'|t}</h4>
{if $disabled}
<p>{$disabled_message}</p>
<p>{$disabled_note}</p>
<div class="text-center"><a href="./index.php" class="btn btn-outline-secondary btn-sm">{'MAIN MENU'|t}</a></div>
{else}
<p class="text-muted">{'Place "return" statement to see returned value'|t}</p>
{if $has_output}
<p class="fw-semibold mb-1">{'Output:'|t}</p>
<div class="border rounded p-2 mb-3 bg-body-tertiary">{$output}</div>
<p class="fw-semibold mb-1">{'Returned value:'|t}</p>
<div class="border rounded p-2 mb-3 bg-body-tertiary" style="overflow:auto"><pre class="mb-0">{$returned_dump|escape}</pre></div>
{/if}
<form method="post">
	<label class="form-label fw-semibold">{'Command:'|t}</label>
	<textarea name="cmd" class="form-control mb-2" rows="8">{$cmd|escape}</textarea>
	<button type="submit" class="btn btn-success">{'Execute'|t}</button>
</form>
{/if}
