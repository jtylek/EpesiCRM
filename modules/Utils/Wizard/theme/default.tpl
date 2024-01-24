{if !empty($captions)}
<table style="width:100%"><tr><td style="width:200px;vertical-align:top;">
{assign var=level value=0}
<ul>
{foreach from=$captions key=i item=cap}

	{while $level > $cap.level}
		</ul>
		{$level--}
	{/while}
	{while $level < $cap.level}
		<ul>
		{$level++}
	{/while}

	<li>
	{if $i==$active_caption_key}
		<b>{$cap.caption}</b>
	{else}
		{$cap.caption}
	{/if}
	</li>
{/foreach}
</ul>
</td><td>
{/if}
{$page}
{if !empty($captions)}
</td></tr></table>
{/if}
