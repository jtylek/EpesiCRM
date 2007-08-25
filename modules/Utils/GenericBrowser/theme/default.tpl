<div style="width: 850px;">

{$form_data.javascript}

<form {$form_data.attributes}> 
{$form_data.hidden}

{if isset($form_data.search)}
	<b>{$form_data.search.label}</b>{$form_data.search.html}
	{$form_data.submit_search.html}{$adv_search}
{else}
	{php}
		$cols = $this->get_template_vars('cols');
		$search_fields = $this->get_template_vars('search_fields');
		$i=0;
		foreach($cols as $k=>$v){
			if(!isset($search_fields[$i])) continue;
			$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$i];
			$i++;
		}
		$this->assign('cols',$cols);
	{/php}
	{if isset($form_data.submit_search)}{$form_data.submit_search.html}{$adv_search}{/if}
{/if}

{if isset($order) || isset($letter_links)}
<table width=100%>
<tr>
	<td align="left" style="padding-bottom: 5px;">
		<b>
		{if isset($letter_links)}
		{foreach key=k item=link from=$letter_links}
		{$link}&nbsp;
		{/foreach}
		{/if}
		</b>
	</td>
	<td align="right" style="padding-bottom: 5px;">
		{if isset($order)}
		{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b>
		{/if}
	</td>
</tr>
</table>
{/if}
{html_table_epesi table_attr='id="Utils_GenericBrowser" cellspacing="0" cellpadding="0"' loop=$data cols=$cols}

<table id="Utils_GenericBrowser__navigation" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td class="per_page"></td>
		<td class="arrow">{if $first}<img src="{$theme_dir}/images/first.png">{/if}</td><td class="first">{$first}</td>
		<td class="arrow">{if $prev}<img src="{$theme_dir}/images/prev.png">{/if}</td><td class="prev">{$prev}</td>
		<td class="summary">{$summary}</td>
		<td class="next">{$next}</td><td class="arrow">{if $next}<img src="{$theme_dir}/images/next.png"></td>{/if}
		<td class="last">{$last}</td><td class="arrow">{if $last}<img src="{$theme_dir}/images/last.png">{/if}</td>
		{if isset($form_data.per_page)}
		<td class="per_page">{$form_data.per_page.label}&nbsp;{$form_data.per_page.html}</td>
		{/if}
	</tr>
</table>

</form>

</div>	