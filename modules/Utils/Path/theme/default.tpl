<div id="{$id}">
{$root.item}
{if $root.children_num > 0}
	{$root.children_open}
	{foreach from=$root.children item=child}
		{$child}
	{/foreach}
	{$root.children_close}
{/if}
{foreach from=$list item=element}
	&nbsp;<img border="0" src="{$theme_dir}/Utils/Path/arrow.gif" width="11" height="11">&nbsp;{$element.item}
	{if $element.children_num > 0}
		{$element.children_open}
		{foreach from=$element.children item=child}
			{$child}
		{/foreach}
		{$element.children_close}
	{/if}
{/foreach}
</div>