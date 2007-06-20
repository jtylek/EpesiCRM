<div style="width: 920px;">

{$form_data.javascript}

<form {$form_data.attributes}> 
{$form_data.hidden}

{if $form_data.search.label}
	<b>{$form_data.search.label}</b>{$form_data.search.html}
	{$form_data.submit_search.html}{$adv_search}
{else}
	{php}
		$cols = $this->get_template_vars('cols');
		$search_fields = $this->get_template_vars('search_fields');
		$i=0;
		foreach($cols as $k=>$v){
			$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$i];
			$i++;
		}
		$this->assign('cols',$cols);
	{/php}
	{$form_data.submit_search.html}{$adv_search}
{/if}

<div align="right" style="padding-bottom: 5px;">{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b></div>

{html_table_tcms table_attr='id="Utils_GenericBrowser" cellspacing="0" cellpadding="0"' loop=$data cols=$cols}

{php}
	load_js('data/Base/Theme/templates/default/Utils_GenericBrowser__default.js');
	eval_js('wait_while_null("utils_genericbrowser__scrolling_table_fix_cols", "utils_genericbrowser__scrolling_table_fix_cols(\'content\')");');
{/php}

<table id="Utils_GenericBrowser__navigation" border="0" cellspacing="0" cellpadding="0">
	<tr>
		<td class="per_page"></td>
		<td class="arrow">{if $first}<img src="data/Base/Theme/templates/epesi/images/first.png">{/if}</td><td class="first">{$first}</td>
		<td class="arrow">{if $prev}<img src="data/Base/Theme/templates/epesi/images/prev.png">{/if}</td><td class="prev">{$prev}</td>
		<td class="summary">{$summary}</td>
		<td class="next">{$next}</td><td class="arrow">{if $next}<img src="data/Base/Theme/templates/epesi/images/next.png"></td>{/if}
		<td class="last">{$last}</td><td class="arrow">{if $last}<img src="data/Base/Theme/templates/epesi/images/last.png">{/if}</td>
		<td class="per_page">{$form_data.per_page.label}&nbsp;{$form_data.per_page.html}</td>
	</tr>
</table>

</form>

</div>	