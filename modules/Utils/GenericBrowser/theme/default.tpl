{$form_data.javascript}

<form {$form_data.attributes}> 
{$form_data.hidden}

<b>{$form_data.per_page.label}</b>{$form_data.per_page.html}<br>
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

<div align=right>{$reset}</div>
<div align = right>{$order}</div>
{html_table_tcms table_attr='width="100%" id="browser"' loop=$data cols=$cols}
{$summary}
</form>

<table border="0" width="50%" align="center">
  <tr>
    <td width="25%" align="center">
		{$first}
    </td>
    <td width="25%" align="center">
		{$prev}
    <td width="25%" align="center">
		{$next}
    </td>
    <td width="25%" align="center">
		{$last}
    </td>
  </tr>
</table>