<div>

{if (isset($custom_label) && $custom_label) || isset($form_data_search) || isset($expand_collapse)}
<div class="letters-search nonselectable">
			<!-- Custom label -->
			{if (isset($custom_label) && $custom_label)}
				<div class="letter_search_icon" {$custom_label_args}>
				<nobr>{$custom_label}</nobr>
				</div>
			{/if}
            <!-- Expand/Collapse -->
			<div class="expand_collapse">
				{if isset($expand_collapse)}
                    <a id="{$expand_collapse.e_id}" class="button" {$expand_collapse.e_href}><img src="{$theme_dir}/Base/ActionBar/icons/expand_big.png" />
                        <div style="display:inline-block;position:relative;top:-4px">
                            {$expand_collapse.e_label}
                        </div>
                    </a>
                    <a id="{$expand_collapse.c_id}" class="button" {$expand_collapse.c_href}><img src="{$theme_dir}/Base/ActionBar/icons/collapse_big.png" />
                        <div style="display:inline-block;position:relative;top:-4px">
                            {$expand_collapse.c_label}
                        </div>
                    </a>
				{/if}
				&nbsp;
			</div>
			<!-- Advanced / Simple Search -->
			{if isset($form_data_search)}
				<div style="width:470px; float:right">
					{$form_data_search.javascript}

					<form {$form_data_search.attributes}>
					{$form_data_search.hidden}
					{if isset($search_fields_hidden)}{$search_fields_hidden}{/if}
					{if isset($form_data_search.search)}
						<span class="advanced" style="float:right;">{$adv_search}</span>
						<span class="submit" style="float:right;">{$form_data_search.submit_search.html}</span>
						<span class="search-box" style="float:right;">{$form_data_search.search.html}</span>
						{if isset($form_data_search.show_all)}
							<span class="submit" style="float:right;">{$form_data_search.show_all.html}</span>
						{/if}
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
							<span class="advanced" style="float:right;">{$adv_search}</span>
							<span class="submit" style="float:right;">{$form_data_search.submit_search.html}</span>
							{if isset($form_data_search.show_all)}
								<span class="submit" style="float:right;">{$form_data_search.show_all.html}</span>
							{/if}
						{/if}
					{/if}
					</form>
				</div>
			{/if}
</div>
{/if}

{php}
	$cols = $this->get_template_vars('cols');
	foreach($cols as $k=>$v)
		$cols[$k]['label'] = '<span>'.$cols[$k]['label'].'</span>';
	$this->assign('cols',$cols);
{/php}

<div class="table">
	<div class="layer">
		<div class="css3_content_shadow">
			<div class="margin2px">
				{$table_prefix}
                {capture name="table_attr"}id="{$table_id}" cols_width_id="{$cols_width_id}" class="Utils_GenericBrowser" style="width:100%;table-layout:fixed;overflow:hidden;text-overflow:ellipsis;"{/capture}
				{html_grid_epesi table_attr=$smarty.capture.table_attr loop=$data cols=$cols row_attrs=$row_attrs}
				{$table_postfix}

				{if isset($form_data_paging)}
				{$form_data_paging.javascript}

				<form {$form_data_paging.attributes}>
				{$form_data_paging.hidden}
				{/if}
				{if isset($order) || $first || $prev || $summary || isset($form_data_paging.page) || isset($form_data_paging.per_page)}
					<div id="Utils_GenericBrowser__navigation" class="nonselectable">
						<div class="nav_background">
							<div style="text-align: left; white-space: nowrap;">
								{if isset($order)}
									{$order}&nbsp;&nbsp;&nbsp;<b>{$reset}</b>&nbsp;&nbsp;&nbsp;
								{/if}
							</div>
							{if isset($__link.first.open) || isset($__link.last.open)}
								<div class="nav_button" style="white-space:nowrap;">{if isset($__link.first.open)}{$__link.first.open}<div class="nav_left_arrow"><img src="{$theme_dir}/images/first.png"></div><div class="nav_left_label">{$__link.first.text}</div>{$__link.first.close}{/if}</div>
								<div class="nav_button" style="white-space:nowrap;">{if isset($__link.prev.open)}{$__link.prev.open}<div class="nav_left_arrow"><img border="0" src="{$theme_dir}/images/prev.png"></div><div class="nav_left_label">{$__link.prev.text}</div>{$__link.prev.close}{/if}</div>
							{/if}
							<div class="nav_summary" style="white-space:nowrap;">&nbsp;&nbsp;&nbsp;{$summary}&nbsp;&nbsp;&nbsp;</div>
							{if isset($__link.first.open) || isset($__link.last.open)}
								<div class="nav_button" style="white-space:nowrap;">{if isset($__link.next.open)}{$__link.next.open}<div class="nav_right_label">{$__link.next.text}</div><div class="nav_right_arrow"><img border="0" src="{$theme_dir}/images/next.png"></div>{$__link.next.close}{/if}</div>
								<div class="nav_button" style="white-space:nowrap;">{if isset($__link.last.open)}{$__link.last.open}<div class="nav_right_label">{$__link.last.text}</div><div class="nav_right_arrow"><img border="0" src="{$theme_dir}/images/last.png"></div>{$__link.last.close}{/if}</div>
							{/if}
							<div class="nav_pagin" style="text-align: right; white-space: nowrap;">
								{if isset($form_data_paging.page)}
									{$form_data_paging.page.label} {$form_data_paging.page.html}
								{/if}
							</div>
							<div class="nav_per_page" style="text-align: right; white-space: nowrap;">
								{if isset($form_data_paging.per_page)}
									{$form_data_paging.per_page.label} {$form_data_paging.per_page.html}
								{/if}
							</div>
						</div>
					</div>
				{/if}

				{if isset($form_data_paging)}
				</form>
				{/if}
			</div>
 		</div>
	</div>
</div>

</div>
