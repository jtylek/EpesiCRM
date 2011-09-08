{php}
	load_js($this->get_template_vars('theme_dir').'/Utils/GenericBrowser/default.js');
{/php}

<div>

{if isset($form_data_search)}
	{$form_data_search.javascript}

	<form {$form_data_search.attributes}>
	{$form_data_search.hidden}
{/if}

{if (isset($custom_label) && $custom_label) || isset($letter_links) || isset($form_data_search)}
<table class="letters-search" border="0" cellpadding="0" cellspacing="0">
	<tbody>
		<tr>
			<!-- Custom label -->
			{if (isset($custom_label) && $custom_label)}
				<td class="letter_search_icon" {$custom_label_args}>
				<nobr>{$custom_label}</nobr>
				</td>
			{/if}
			<!-- QuickJump -->
			<td class="letters" width="65px">
				{if isset($letter_links)}
				<div class="abc" onclick="quick_jump_letters('{$id}');">ABC</div>
				<div id="quick_jump_letters_{$id}" class="quick_jump_letters" 
					{if $quickjump_to==''} 
						style="display: none;"
					{/if}
					>
					<div class="css3_content_shadow GenericBrowser_letters">
							{if isset($letter_links)}
							{foreach key=k item=link from=$letter_links}
							{$link}
							{/foreach}
							{/if}
					</div>
				</div>
				{/if}
			</td>
			<td>
			</td>
			<!-- Advanced / Simple Search -->
			{if isset($form_data_search)}
				{if isset($form_data_search.search)}
					{if isset($form_data_search.show_all)}
						<td class="submit" style="width:120px;">{$form_data_search.show_all.html}</td>
					{/if}
					<td style="width:110px;">{$form_data_search.search.html}</td>
					<td class="submit" style="width:112px;">{$form_data_search.submit_search.html}</td>
					<td class="advanced" style="width:151px;">{$adv_search}</td>
				{else}
					{php}
						$cols = $this->get_template_vars('cols');
						$search_fields = $this->get_template_vars('search_fields');
						foreach($cols as $k=>$v){
							if(isset($search_fields[$k]))
								$cols[$k]['label'] = $cols[$k]['label'].$search_fields[$k];
						}
						$this->assign('cols',$cols);
					{/php}
					{if isset($form_data_search.submit_search)}
						{if isset($form_data_search.show_all)}
							<td class="submit" style="width:112px;">{$form_data_search.show_all.html}</td>
						{/if}
						<td class="submit" style="width:112px;">{$form_data_search.submit_search.html}</td>
						<td class="advanced" style="width:151px;">{$adv_search}</td>
					{/if}
				{/if}
			{/if}
		</tr>
	</tbody>
</table>
{/if}

{if isset($form_data_search)}
	</form>
{/if}


<div class="table">
	<div class="layer">
		<div class="css3_content_shadow">
			<div class="margin2px">
				{$table_prefix}
				{html_table_epesi table_attr='class="Utils_GenericBrowser" cellspacing="0" cellpadding="0" style="width:100%;table-layout:fixed;overflow:hidden;text-overflow:ellipsis;"' loop=$data cols=$cols row_attrs=$row_attrs}
				{$table_postfix}

				{if isset($form_data_paging)}
				{$form_data_paging.javascript}

				<form {$form_data_paging.attributes}>
				{$form_data_paging.hidden}
				{/if}
				{if isset($order) || $first || $prev || $summary || isset($form_data_paging.page) || isset($form_data_paging.per_page)}
					<table id="Utils_GenericBrowser__navigation" border="0" cellspacing="0" cellpadding="0">
						<tr class="nav_background">
							<td style="text-align: left; width: 1px; white-space: nowrap;">
								{if isset($order)}
									{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b>&nbsp;&nbsp;&nbsp;
								{/if}
							</td>
							<td style="width:30%"></td>
							<td style="width:30%"></td>
							{if isset($__link.first.open) || isset($__link.last.open)}
								<td class="nav_button" nowrap>{if isset($__link.first.open)}{$__link.first.open}<div class="nav_div1"><img src="{$theme_dir}/images/first.png"></div><div class="nav_div2">{$__link.first.text}</div>{$__link.first.close}{/if}</td>
								<td class="nav_button" nowrap>{if isset($__link.prev.open)}{$__link.prev.open}<div class="nav_div1"><img border="0" src="{$theme_dir}/images/prev.png"></div><div class="nav_div2">{$__link.prev.text}</div>{$__link.prev.close}{/if}</td>
							{/if}
							<td class="nav_summary" nowrap>&nbsp;&nbsp;&nbsp;{$summary}&nbsp;&nbsp;&nbsp;</td>
							{if isset($__link.first.open) || isset($__link.last.open)}
								<td class="nav_button" nowrap>{if isset($__link.next.open)}{$__link.next.open}<div class="nav_div1">{$__link.next.text}</div><div class="nav_div2"><img border="0" src="{$theme_dir}/images/next.png"></div>{$__link.next.close}{/if}</td>
								<td class="nav_button" nowrap>{if isset($__link.last.open)}{$__link.last.open}<div class="nav_div1">{$__link.last.text}</div><div class="nav_div2"><img border="0" src="{$theme_dir}/images/last.png"></div>{$__link.last.close}{/if}</td>
							{/if}
							<td style="width:30%"></td>
							<td class="nav_pagin" nowrap style="width: 1px; text-align: right; white-space: nowrap;">
								{if isset($form_data_paging.page)}
									{$form_data_paging.page.label} {$form_data_paging.page.html}
								{/if}	
							</td>
							<td class="nav_per_page" nowrap style="width: 1px; text-align: right; white-space: nowrap;">
								{if isset($form_data_paging.per_page)}
									{$form_data_paging.per_page.label} {$form_data_paging.per_page.html}
								{/if}
							</td>
						</tr>
					</table>
				{/if}

				{if isset($form_data_paging)}
				</form>
				{/if}
			</div>
 		</div>
	</div>
</div>

</div>
