{if !empty($captions)}
<div style="width:100%; display: flex;"><div style="width:200px;">
{assign var=level value=0}
<ul>
{foreach from=$captions key=i item=cap}
	{php}
		$level = $this->get_template_vars('level');
		$cap = $this->get_template_vars('cap');
		$cap_level = $cap['level'];
		while($level>$cap_level) {
			print('</ul>');
			$level--;
		}
		while($level<$cap_level) {
			print('<ul>');
			$level++;
		}
		$this->assign('level',$level);
	{/php}

	<li>
	{if $i==$active_caption_key}
		<b>{$cap.caption}</b>
	{else}
		{$cap.caption}
	{/if}
	</li>
{/foreach}
</ul>
</div><div style="flex: 1 1 auto; min-width: 0;">
{/if}
{$page}
{if !empty($captions)}
</div></div>
{/if}
