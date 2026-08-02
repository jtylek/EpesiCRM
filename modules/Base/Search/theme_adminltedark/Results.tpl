{* $link (one per result) is pre-rendered HTML from Utils_RecordBrowser/Search.php::format_results() -
   a linked label plus "<span style="color:red">word</span> <span style="color:gray">(fields)</span>",
   both inline-styled directly in that shared PHP, not via a class - left as-is, only the
   surrounding list/card chrome is themed here. *}
{if isset($links)}
<div class="epesi-search-results card">
	<div class="card-header epesi-search-results-header">{$header}</div>
	{if isset($warning)}
		<div class="epesi-search-results-warning">{$warning}</div>
	{/if}
	<ul class="list-group list-group-flush">
		{foreach key=key item=link from=$links}
			<li class="list-group-item epesi-search-results-item">{$link}</li>
		{/foreach}
	</ul>
</div>
{/if}
