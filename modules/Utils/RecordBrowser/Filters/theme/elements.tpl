{$form_open}

<div id="recordbrowser_filters_{$filter_group.id}" class="Utils_RecordBrowser__Filter" {if !$filter_group.visible}style="display: none;"{/if}>
	<table border="0" cellpadding="0" cellspacing="0">
		<tr>
			{assign var=x value=0}
			{assign var=first value=1}
			{foreach item=f from=$filter_group.elements}
				{if $x==4}
					{if $first==1}
						<td class="buttons">{$form_data.submit.html}</td>
						{assign var=first value=0}
					{else}
						<td />
					{/if}
					{assign var=x value=0}
					</tr>
					<tr>
				{/if}
				<td class="label">{$form_data.$f.label}</td>
				<td class="data">{$form_data.$f.html}</td>
				{assign var=x value=$x+1}
			{/foreach}
			{if $first==1}
				<td class="buttons">{$form_data.submit.html}</td>
			{/if}
		</tr>
	</table>
</div>

{$form_close}
