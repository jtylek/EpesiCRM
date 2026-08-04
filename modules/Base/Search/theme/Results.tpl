{if isset($links)}
<div id="Base_Search__Results">
	<div class="header">{$header}</div>
	{if isset($warning)}
		<div class="warning">{$warning}</div>
	{/if}
	{foreach key=key item=link from=$links}
	<div class="link">{$link}</div>
	<!-- $key holds name of the module -->
	{/foreach}
</div>
{/if}
